<?php

namespace App\Http\Controllers;

use App\Mail\Email;
use App\Models\Administrative\Api\Service;
use App\Models\Administrative\Api\ServiceIndex;
use App\Models\Administrative\Api\ServiceParam;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Auth;
use Mail;

class CpmController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
 
    }

    /*
     * API FUNCTION
     *
     * List function of API :
     * - api_catch($e)
     * - api_response($msg[string], $data[array], $errors[error_code,error_msg])
     * - api_validate($request, $rules[array], msg[array])
     * - api_partials($success[int],$fail[int],$details[id,msg])
     *
     */

    protected function api_catch($e)
    {
        //Catch Error
        return $this->api_response('Response Failed', [], ['error_code' => 500, 'error_msg' => [$e->getMessage()]]);
    }

    protected function api_partials($success, $fail, $details)
    {
        $data   = ['success' => $success, 'fail' => $fail, 'details' => $details];
        $msg    = $success == 0 ? 'All data failed' : 'Success partial';
        return $this->api_response($msg, $data);
    }

    protected function api_response($msg, $data = [], $errors = [])
    {
        //Generate API response
        $success    = empty($errors) ? true : false;
        $data       = empty($errors) ? $data : [];
        $response   = ['success' => $success, 'message' => $msg, 'data' => $data];
        if (!$success)
        {
            $response = array_merge($response, ['errors' => $errors]);
        }
        return response()->json($response);
    }

    protected function api_validate($request, $rules = [], $partials = false)
    {
        //Validation proccess
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails())
        {
            $errors     = $validator->errors();
            $response   = $this->api_response('Validation Failed', [], ['error_code' => 422, 'error_msg' => $errors->all()]);
            return $partials ? $response : $response->send();
        }
    }
    
    protected function api_ws($request, $sn, $val, $save=false)
    {
        try
        {
            $data   = $ws = [];
            $srv    = Service::join('c_api as b', 'c_api_services.api_id', '=', 'b.api_id')
                    ->where([['service_name', $sn], ['c_api_services.is_active', 'Yes'], ['b.is_active', 'Yes']])->first();
            if (!empty($srv->service_id))
            {
                $n      = 0;
                $dKey   = $srv->data_key;
                $param  = ServiceParam::where([['service_id', $srv->service_id], ['is_active', 'Yes']])->orderBy('sequence_to'); 
                if ($param->count() > 0)
                {
                    foreach ($param->get() as $prm)
                    {
                        $value  = !empty($prm->param_value) || !empty($val) ? !empty($prm->param_value) ? $prm->param_value : $val[$n] : [];
                        $data   = !empty($value) ? array_merge($data, [$prm->index_key => $prm->param_key, $prm->index_value => $value]) : $data;
                        $n++;
                    }
                }
                $curl   = __api(['ch' => $srv->url, 'ct' => $srv->content_type, 'url' => $srv->service_path, 'req' => $srv->method, 'data' => $data]);
                $ws     = !empty($curl) ? is_array($curl) ? $curl[$dKey] : $curl->$dKey : [];
                
                if ($save && !empty($ws))
                {
                    $this->api_ws_save($ws, $srv, $request->input('ip'));
                }
            }
            return $this->api_response('Get WS', $ws);
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }
    
    private function api_ws_save($ws, $srv, $ip='')
    {
        try
        {
            $skey       = $srv->service_key;
            $model      = $this->qry_table();
            $col        = $model->getFillable();
            $pKey       = $model->getKeyName();
            $save_by    = !empty($this->cpm_auth()) ? $this->cpm_auth()->usercategory_name .':'. $this->cpm_auth()->id .':'. $this->cpm_auth()->fullname : 'System';
            $host       = !empty($ip) ? $ip : '::1';
            $index      = ServiceIndex::where([['service_id', $srv->service_id], ['is_active', 'Yes']])->get();
            if ($index->count() > 0)
            {
                foreach ($ws as $w)
                {
                    $arr    = [];
                    $n      = 0;
                    foreach ($index as $idx)
                    {
                        $fn             = $idx->index_name;
                        $val            = is_array($w) ? $w[$fn] : $w->$fn;
                        $arr[$col[$n]]  = !empty($val) ? $val : '-';
                        $n++;
                    }
                    $wcode  = is_array($w) ? $w[$skey] : $w->$skey;
                    $row    = $model::where('ext_code', $wcode)->first();
                    $save   = empty($row->$pKey) ? 'created' : 'updated';
                    $data   = array_merge($arr, ['is_data' => 'WS', 'is_active' => 'Yes', $save.'_by' => $save_by, $save.'_host' => $host]);
                    $qry    = $save == 'created' ? $model::create($data) : $model::where($pKey, $row->$pKey)->update($data);
                }
            }
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }

    /*
     * CPM FUNCTION
     *
     */
    
    public function cpm_auth()
    {
        return Auth::id() ? Auth::user() : Auth::guard('admin')->user();
    }

    public function cpm_date($date='', $fmt='Y-m-d')
    {
        if ($fmt == '.net')
        {
            preg_match('/([\d]{9})/', $date, $dt);
            return date('Y-m-d', $dt[0]);
        }
        else
        {
            return !empty($date) ? date($fmt, strtotime($date)) : date($fmt);
        }
    }

    public function cpm_datetime($date='', $fmt='Y-m-d H:i:s')
    {
        return !empty($date) ? date($fmt, strtotime($date)) : date($fmt);
    }

    protected function cpm_list($filter = [], $tbl = '')
    {
        return $this->qry_result($filter, $tbl, '', 'list');
    }

    protected function cpm_row($field = [], $filter = [], $tbl = '')
    {
        return $this->qry_result($filter, $tbl, $field, 'row');
    }

    /*
     * CPM QUERY
     *
     */

    protected function cpm_detail($request)
    {
        try
        {
            $model  = $this->qry_table();
            $key    = $model->getKeyName();
            $id     = $request->input('id');
            $spec   = $request->input('specific');
            $filter = !empty($request->input('filter')) ? [$request->input('filter')] : [];
            $whr    = !empty($spec) ? $spec : [$key, $id]; 
            $data   = $id > 0 || (!empty($spec) && is_array($spec)) ? $model::where(array_merge([$whr, ['is_active', 'Yes']], $filter))->first() : array_fill_keys($this->qry_column($model), '');

            if (empty($id) && array_key_exists('sequence_to', $data)) $data['sequence_to'] = $this->qry_sequence($filter);

            return $this->api_response('Succes get detail', $data);
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }
    
    protected function cpm_import($request, $prm=[])
    {   
        try
        {
            if (!empty($this->api_validate($request, ['file_import' => 'required|mimes:csv,xls,xlsx'])))
            {
                exit();
            }
            
            $fail       = $success = 0;
            $details    = [];
            $usrtyp     = !empty($this->cpm_auth()) ? $this->cpm_auth()->usercategory_name : 'Visitor';
            $usrnm      = !empty($this->cpm_auth()) ? $this->cpm_auth()->fullName : 'User';
            $usrid      = !empty($this->cpm_auth()) ? $this->cpm_auth()->id : 0;
            $ip         = !empty($request->input('ip')) ? $request->input('ip') : $request->ip();            
            $import     = 'App\Imports\\' . $this->table . 'Import';
            $model      = $this->qry_table();            
            $pKey       = $model->getKeyName();
            $file       = $request->file('file_import');
            
            $file->move(storage_path('import'), $file->getClientOriginalName());
            
            $excel  = Excel::toArray(new $import, storage_path('import') .'/'. $file->getClientOriginalName());
            foreach ($excel[0] as $ex)
            {
                $n      = 0;
                $data   = [];
                foreach ($this->qry_column($model) as $fn)
                {
                    switch ($fn)
                    {
                        case 'created_by'   : $data[$fn] = $usrtyp .':'. $usrid .':'. $usrnm; break;
                        case 'created_host' : $data[$fn] = $ip; break;
                        default             : 
                            if (!empty($ex[$n]))
                                $data[$fn] = $ex[$n];
                            elseif (!empty($prm[$fn]))
                                $data[$fn] = $prm[$fn];
                    }
                    $n++;
                }
                if ($qry = $model::create($data))
                {
                    array_push($details, ['id' => $qry->$pKey]);
                    $success++;
                }
                else
                {
                    $fail++;
                }
            }
            
            unlink(storage_path('import') .'/'. $file->getClientOriginalName());
            return $this->api_partials($success, $fail, $details);
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }

    protected function cpm_save($request, $id='', $path='', $mdl='', $resId='No', $response='')
    {
        try
        {
            $method     = $request->method();
            $model      = $this->qry_table($mdl);
            $act        = $method != 'DELETE' ? $method == 'POST' ? 'created' : 'updated' : 'deleted';
            $fail       = $success = 0;
            $details    = [];

            if ($method != 'DELETE' && !empty($this->api_validate($request, $model::rules($id, $request))))
            {
                exit();
            }
            
            $pKey   = $model->getKeyName();
            $data   = $this->qry_input($request, $model);
            $qry    = $method == 'POST' && empty($id) ? $model::create($data) : $model::where($pKey, $id)->update($data);
            
            if ($qry)
            {
                $id = $method == 'POST' && empty($id) ? $qry->$pKey : $id;
                if (!empty($request->file()))
                {
                    $client = new Client();
                    foreach ($request->file() as $file_k => $file_v)
                    {
                        if (in_array($file_k, $this->qry_column($model)))
                        {
                            $filename = $id .'_'. md5($file_k . $this->cpm_datetime()) .'.'. $file_v->getClientOriginalExtension();
                            $client->post(env('API_UPLOAD') . 'upload', [
                                'multipart' => [
                                    ['name' => 'file', 'contents' => file_get_contents($file_v), 'filename' => $filename],
                                    ['name' => 'path', 'contents' => $path . '/' . $id]
                                ]
                            ]);
                            $model::where($pKey, $id)->update([$file_k => $filename]);
                        }
                    }
                }

                array_push($details, ['save' => $qry, 'status' => '']);
                $success++;

                if (method_exists($this, 'table_child'))
                {
                    $res_dtl    = $this->qry_save_detail($request, $this->table_child(), $pKey, $id, $details, $fail, $success);
                    $details    = $res_dtl['details'];
                    $fail       = $res_dtl['fail'];
                    $success    = $res_dtl['success'];
                }
            }
            $response = !empty($response) ? array_merge($response, $details) : $details;
            return $resId == 'Yes' ? $id : $this->api_partials($success, $fail, $response);
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }

    public function cpm_sendmail($mail)
    {
        try
        {
            $model  = new \App\Models\Administrative\Email\EmailContent;
            $data   = $model::join('c_email_layouts as b', 'c_email_contents.layout_id', '=', 'b.layout_id')->where([['c_email_contents.is_active', 'Yes'], ['b.is_active', 'Yes'], ['email_content_name', $mail['content']]])->first();
            if (!empty($data->email_content_id))
            {
                $send = (object) array('from' => env(MAIL_FROM_NAME,'noreplay@bankbsi.co.id'), 'subject' => $data->email_subject, 'data' => $data, 'mail' => $mail);
                return Mail::to($mail['to'])->send(new Email($send));
            }
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }

    public function qry_column($model='')
    {
        return !empty($model->getFillable()) ? $model->getFillable() : array_diff(\Schema::getColumnListing($model->getTable()), $model->getGuarded());
    }

    private function qry_filter($filter, $model, $select, $tbl)
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

    private function qry_input($request, $model, $arr='N', $arr_n=0)
    {
        $data   = [];
        $act    = $request->method() == 'POST' ? 'created' : 'updated';
        $usrtyp = !empty($this->cpm_auth()) ? $this->cpm_auth()->usercategory_name : 'Visitor';
        $usrnm  = !empty($this->cpm_auth()) ? $this->cpm_auth()->fullname : 'User';
        $usrid  = !empty($this->cpm_auth()) ? $this->cpm_auth()->id : 0;
        $ip     = !empty($request->input('ip')) ? $request->input('ip') : $request->ip();
        
        if ($request->method() != 'DELETE')
        {
            $foreignKey = !empty($request->input('foreignKey')) ? $request->input('foreignKey') : '';
            $fid        = !empty($foreignKey) && !empty($foreignKey['id']) ? $foreignKey['id'] : '&^%';
            foreach ($this->qry_column($model) as $fn)
            {
                switch ($fn)
                {
                    case $fid           : $data[$fn]          = $foreignKey['val']; break;
                    case 'is_data'      : if ($request->method == 'POST') { $data[$fn] = 'APPS'; } break;
                    case 'created_by'   : $data[$act.'_by']   = $usrtyp .':'. $usrid .':'. $usrnm; break;
                    case 'created_host' : $data[$act.'_host'] = $ip; break;
                    default             : 
                        if (!empty($request->input($fn)))
                        {
                            $inp        = $request->input($fn);
                            $val        = $arr == 'Y' ? $inp[$arr_n] : $inp;
                            $data[$fn]  = $val;
                        }
                }
            }
        }
        else
        {
            $data['updated_by']      = $usrtyp .':'. $usrid .':'. $usrnm;
            $data['updated_host']    = $ip;
            $data['is_active']       = 'No';
        }

        return $data;
    }

    private function qry_result($filter, $table='', $field='', $res_typ='')
    {
        try
        {
            $model  = $this->qry_table($table);
            $key    = empty($field) ? $model->getKeyName() : $field;
            $tbl    = $model->getTable();
            $select = !empty($field) ? is_array($field) ? $field : [$tbl.'.'.$field] : [$tbl.'.*'];
            $data   = !empty($filter) && is_array($filter) ? $this->qry_filter($filter, $model, $select, $tbl) : $model::where('is_active', 'Yes');
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
                    $parse = !empty($result) ? $result : array_fill_keys($field, '');
                }
                else
                {
                    $parse = !empty($result->$key) ? $result->$key : '';
                }
            }

            return $this->api_response('Success get data', $parse);
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }

    private function qry_save_detail($request, $table_child, $pKey, $id, $details, $fail, $success, $lvl1=0, $lvl2=0)
    {
        $tbl_cld    = $table_child[$lvl1];
        $prtl       = $tbl_cld['partial'];
        $model      = $this->qry_table($tbl_cld['model']);
        $prtl_id    = $request->input($prtl['id']);
        $pKey_cld   = $model->getKeyName();
        $add_req    = is_array($prtl_id) ? ['foreignKey' => ['id' => $pKey, 'val' => $id]] : [$pKey => $id];

        $request->request->add($add_req);

        $dt_prtl    = is_array($prtl_id) ? $prtl_id[$arr_n] : $prtl_id;
        $where      = ['where' => [$prtl['id'], $dt_prtl], [$pKey, $id]];
        $id_cld     = $this->cpm_row($pKey_cld, $where, $tbl_cld['model'])->original['data'];
        $act        = empty($id_cld) ? 'created': 'updated';
        $is_arr     = is_array($prtl_id) ? 'Y' : 'N';
        $data       = $this->qry_input($request, $model, $is_arr, $lvl2);
        $req_cld    = new \Illuminate\Http\Request();

        $req_cld->replace($data);

        $validate = $this->api_validate($req_cld, $model::rules($data), true);
        if (!empty($validate))
        {
            $error_arr  = $validate->original['errors']['error_msg'];
            $error_msg  = count($error_arr) < 1 ? $error_arr : $validate->original['errors']['error_msg'][0];
            array_push($details, ['id' => '['. $prtl['id'] .']' . $data[$prtl['id']], 'status' => $error_msg]);
            $fail++;
        }
        else
        {
            $qry = $act == 'created' ? $model::create($data) : $model::where($pKey_cld, $id_cld)->update($data);
            array_push($details, ['id' => $qry->$pKey_cld, 'status' => '']);
            $success++;
        }
        if ((is_array($prtl_id) && (count($prtl_id)-1) == $lvl2) || !is_array($prtl_id)) { $lvl1++; }
        return $lvl1 == count($table_child) ? ['details' => $details, 'fail' => $fail, 'success' => $success] : $this->qry_save_detail($request, $table_child, $pKey, $id, $details, $fail, $success, $lvl++);
    }

    public function qry_sequence($filter)
    {
        $model  = $this->qry_table();
        $sqto   = $model::where(array_merge([['is_active', 'Yes']], $filter))->max('sequence_to');
        return !empty($sqto) ? $sqto + 1 : 1;
    }

    private function qry_table($tbl='')
    {
        $tbl = !empty($tbl) ? $tbl : $this->table;
        $mdl = 'App\Models\\'. $tbl;
        return new $mdl;
    }
}