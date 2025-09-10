<?php

namespace App\Traits;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;
use Schema;

trait DBQuery
{
    public function db_column($model='')
    {
        return !empty($model->getFillable()) ? $model->getFillable() : array_diff(Schema::getColumnListing($model->getTable()), $model->getGuarded());
    }
    
    protected function db_detail($id=null, $ele=[])
    {
        try
        {
            $model  = $this->db_model();
            $spec   = isset($ele['specific']) ? $ele['specific'] : [];
            $filter = isset($ele['filter']) ? $ele['filter'] : [];
            $whr    = !empty($spec) ? $spec : [$model->getKeyName(), $id]; 
            $data   = $id > 0 || (!empty($spec) && is_array($spec)) ? $model::where(array_merge([$whr, ['is_active', 'Yes']], $filter))->first() : array_fill_keys($this->db_column($model), '');

            if (empty($id) && array_key_exists('sequence_to', $data)) $data['sequence_to'] = $this->db_sequence($filter);

            return $this->app_response('Succes get detail', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function db_filter($filter, $model, $select, $tbl)
    {
        $where = [[$tbl.'.is_active', 'Yes']];
        if (!empty($filter['join']))
        {
            foreach ($filter['join'] as $join)
            {
                $as     = !empty($join['as']) ? $join['as'] : $join['tbl'];
                $fkey   = !empty($join['fkey']) ? $join['fkey'] : $join['key'];
                $jtbl   = !empty($as) ? $join['tbl'] .' as '. $as : $join['tbl'];
                $model  = $model->join($jtbl, $tbl.'.'.$join['key'], '=', $as.'.'.$fkey);
                array_push($where, [$as.'.is_active', 'Yes']);
                if (!empty($join['select']))
                {
                    $select = array_merge($select, array_map(function($slc) use ($as) { return $as .'.'. $slc; }, $join['select']));
                }
            }
        }
        if (!empty($filter['where'])) { foreach ($filter['where'] as $whr) { array_push($where, $whr); }}
        $data = $model->select($select)->where($where);
        if (!empty($filter['where_in'])) { foreach ($filter['where_in'] as $whr => $in) { $data = $data->whereIn($whr, $in); }}
        if (!empty($filter['whereNotIn'])) { foreach ($filter['whereNotIn'] as $whr => $in) { $data = $data->whereNotIn($whr, $in); }}
        if (!empty($filter['order'])) { foreach ($filter['order'] as $ofn => $osort) { $data = $data->orderBy($ofn, $osort); }}
        return $data;
    }
    
    protected function db_manager($request)
    {
        $usrtyp     = !empty($this->auth_user()) ? $this->auth_user()->usercategory_name : 'Visitor';
        $usrnm      = !empty($this->auth_user()) ? $this->auth_user()->fullname : 'User';
        $usrid      = !empty($this->auth_user()) ? $this->auth_user()->id : 0;
        $ip         = !empty($request->input('ip')) ? $request->input('ip') : $request->ip();
        return (object) ['user' => $usrtyp . ':' . $usrid . ':' . $usrnm, 'ip' => $ip];
    }
    
    protected function db_model($tbl='')
    {
        $tbl = !empty($tbl) ? $tbl : $this->table;
        $mdl = 'App\Models\\'. $tbl;
        return new $mdl;
    }

    protected function db_result($filter = [], $tbl = '')
    {
        return $this->db_select($filter, $tbl, '', 'list');
    }

    protected function db_row($field = [], $filter = [], $tbl = '')
    {
        return $this->db_select($filter, $tbl, $field, 'row');
    }
    
    protected function db_save($request, $id=null, $form_ele=[])
    {
        try
        {
            $tbl        = isset($form_ele['table']) ? $form_ele['table'] : '';
            $method     = $request->method();
            $model      = $this->db_model($tbl);
            $audit      = $model->getTable() . '_audit';
            $act        = $method != 'DELETE' ? $method == 'POST' || (!empty($request->__update) && $request->__update != 'Yes') ? 'created' : 'updated' : 'deleted';
            $fail       = $success = 0;
            $details    = [];
            $validate   = empty($form_ele['validate']) || !$form_ele['validate'] ? $model::rules($id, $request) : [];

            if ($method != 'DELETE' && !empty($this->app_validate($request, $validate)))
            {
                exit();
            }
            
            $pKey   = $model->getKeyName();
            $data   = $this->form_input($request, $model, $id);
            $qry    = $method == 'POST' && empty($id) ? $model::create($data['data']) : $model::where($pKey, $id)->update($data['data']);
            
            if ($qry)
            {
                $id = $method == 'POST' && empty($id) ? $qry->$pKey : $id;
                if (!empty($request->file()))
                {
                    $client = new Client();
                    foreach ($request->file() as $file_k => $file_v)
                    {
                        if (in_array($file_k, $this->db_column($model)))
                        {
                            $filename = $id .'_'. md5($file_k . $this->app_date('', 'Y-m-d H:i:s')) .'.'. $file_v->getClientOriginalExtension();
                            $client->post(env('API_UPLOAD') . 'upload', [
                                'multipart' => [
                                    ['name' => 'file', 'contents' => file_get_contents($file_v), 'filename' => $filename],
                                    ['name' => 'path', 'contents' => $form_ele['path'] . '/' . $id]
                                ]
                            ]);
                            
                            $u_img              = $file_k .' : '. $filename;
                            $data['audit'][]    = $u_img;
                            $model::where($pKey, $id)->update([$file_k => $filename]);
                        }
                    }
                }
                
                if (Schema::hasTable($audit))
                {
                    $status = $method != 'DELETE' ? $method == 'POST' ? 'Insert' : 'Update' : 'Delete';
                    $msg    = $status == 'Delete' ? 'Update data ' : $status . ' data ';
                    
                    DB::table($audit)->insert([
                        'pk_id'         => $id,
                        'user_id'       => !empty($this->auth_user()->id) ? $this->auth_user()->id : '',
                        'user_name'     => !empty($this->auth_user()->fullname) ? $this->auth_user()->fullname : '',
                        'user_type'     => !empty($this->auth_user()->usercategory_name) ? $this->auth_user()->usercategory_name : 'System',
                        'description'   => $msg . implode(', ', $data['audit']),
                        'status'        => $status,
                        'created_at'    => $this->app_date('', 'Y-m-d H:i:s')
                    ]);
                }
                array_push($details, [$qry]);
                $success++;
            }
            return isset($form_ele['res']) && $form_ele['res'] == 'id' ? $id : $this->app_partials($success, $fail, $details);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function db_select($filter, $table='', $field='', $res_typ='')
    {
        try
        {
            $model  = $this->db_model($table);
            $key    = empty($field) ? $model->getKeyName() : $field;
            $tbl    = $model->getTable();
            $select = !empty($field) ? is_array($field) ? $field : [$tbl.'.'.$field] : [$tbl.'.*'];
            $data   = !empty($filter) && is_array($filter) ? $this->db_filter($filter, $model, $select, $tbl) : $model::where('is_active', 'Yes');
            $data   = !empty($field) && !is_array($filter) ? $data->where($model->getKeyName(), $filter) : $data;

            if (empty($field) && $res_typ != 'row')
            {
                $parse = ['key' => $key, 'list' => $data->get()];
            }
            else
            {
                $result = $data->first();
                if (is_array($field) || empty($field))
                {
                    $parse = !empty($result) ? $result : array_fill_keys($field, null);
                }
                else
                {
                    $parse = !empty($result->$key) ? $result->$key : null;
                }
            }

            return $this->app_response('Success get data', $parse);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function db_sequence($filter)
    {
        $model  = $this->db_model();
        $sqto   = $model::where(array_merge([['is_active', 'Yes']], $filter))->max('sequence_to');
        return !empty($sqto) ? $sqto + 1 : 1;
    }

    private function form_input($request, $model, $id)
    {
        $data       = $audit = [];
        $act        = ($request->method() == 'POST' && empty($id)) || (!empty($request->__update) && $request->__update != 'Yes') ? 'created' : 'updated';
        $manager    = $this->db_manager($request);
        $ip         = $manager->ip;
        
        if ($request->method() != 'DELETE')
        {
            $dt         = $act == 'updated' ? $model::find($id) : null;
            $foreignKey = !empty($request->input('foreignKey')) ? $request->input('foreignKey') : '';
            $fid        = !empty($foreignKey) && !empty($foreignKey['id']) ? $foreignKey['id'] : '&^%';
            $data       = ['is_active' => 'Yes'];
            $column     = $this->db_column($model);
            foreach ($column as $fn)
            {
                switch ($fn)
                {
                    case $fid           : $data[$fn]          = $foreignKey['val']; break;
                    case 'created_by'   : $data[$act.'_by']   = !empty($request->input('created_by')) ? $request->input('created_by') : $manager->user; break;
                    case 'created_host' : $data[$act.'_host'] = $ip; break;
                    default             :

                        $inp_data = false;
                        if (in_array('is_data', $column)) {
                            if (!is_null($request->input($fn))) {
                                $inp_data = true;
                            }
                        }
                        else if ($request->has($fn)) {
                            $inp_data = true;
                        }

                        if ($inp_data)
                        {
                            $data[$fn]  = (!empty($request->input($fn)) || is_numeric($request->input($fn))) ? $request->input($fn) : null;
                            $input      = !is_array($request->input($fn)) ? $request->input($fn) : implode(',', $request->input($fn));

                            if ($request->method() == 'POST') {
                                $audit[] = $fn . ': ' . $input;
                            } elseif (is_object($dt) && (empty($dt->$fn) || (!empty($dt->$fn) && $dt->$fn != $request->input($fn)))){
                                $field      = !is_array($dt->$fn) ? $dt->$fn : implode(',', $dt->$fn);
                                $audit[]    = $fn .' from "'. $field .'" to "'. $input .'"';
                            }
                        }
                }
            }
        }
        else
        {
            $data['updated_by']         = $manager->user;
            $data['updated_host']       = $ip;
            $data['is_active']          = 'No';
            $audit                      = ['is_active From Yes To No'];
        }

        return ['data' => $data, 'audit' => $audit];
    }
}