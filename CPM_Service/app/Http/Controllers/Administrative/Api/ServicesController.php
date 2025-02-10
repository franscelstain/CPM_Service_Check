<?php

namespace App\Http\Controllers\Administrative\Api;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Api\ServiceIndex;
use App\Models\Administrative\Api\ServiceParam;
use Illuminate\Http\Request;

class ServicesController extends AppController
{
    public $table = 'Administrative\Api\Service';

    public function index()
    {
        return $this->db_result(['join' => [['tbl' => 'c_api', 'key' => 'api_id', 'select' => ['api_name', 'slug']]]]);
    }
    
    private function child($request, $model, $fk, $sort = '')
    {
        try
        {
            $model = new $model;
            $param = [];
            if (!empty($request->id))
            {
                $arr    = [];
                $data   = $model::where([[$fk, $request->id], ['is_active', 'Yes']]);
                $data   = !empty($sort) ? $data->orderBy($sort) : $data;  
                foreach ($data->get() as $dt)
                {
                    foreach ($model->getFillable() as $m)
                        $arr = array_merge($arr, [$m => $dt->$m]);
                    $param[] = $arr;
                }
            }
            if (empty($param))
                $param[] = array_fill_keys($model->getFillable(), '');
            
            return $this->app_response('Relation Data', $param);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function param(Request $request)
    {
        return $this->child($request, 'App\Models\Administrative\Api\ServiceParam', 'service_id', 'sequence_to');
    }

    public function save(Request $request, $id = null)
    {
        try
        {
            $success        = 1;
            $id             = $this->db_save($request, $id, ['res' => 'id']);
            $param_key      = $request->input('param_key');
            $param_value    = $request->input('param_value');
            $param_type     = $request->input('param_type');
            $sequence_to    = $request->input('sequence_to');
            
            ServiceParam::where('service_id', $id)->update(['is_active' => 'No']);
            if (!empty($param_key))
            {
                for ($i = 0; $i < count($param_key); $i++)
                {
                    if (!empty($param_key[$i]))
                    {
                        $param  = ServiceParam::where([['service_id', $id], ['param_key', $param_key[$i]]])->first();
                        $st     = empty($param->param_id) ? 'cre' : 'upd';
                        $data   = ['service_id'     => $id,
                                   'param_key'      => $param_key[$i],
                                   'param_value'    => !empty($param_value[$i]) || is_numeric($param_value[$i]) ? $param_value[$i] : null,
                                   'param_type'     => !empty($param_type[$i]) ? $param_type[$i] : null,
                                   'sequence_to'    => $sequence_to[$i],
                                   'is_active'      => 'Yes',
                                   $st.'ated_by'    => $this->auth_user()->id,
                                   $st.'ated_host'  => $request->input('ip')
                                  ];
                        $save   = empty($param->param_id) ? ServiceParam::create($data) : ServiceParam::where('param_id', $param->param_id)->update($data);
                        $success++;
                    }
                }
            }
            return $this->app_partials($success, 0, ['id' => $id]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}