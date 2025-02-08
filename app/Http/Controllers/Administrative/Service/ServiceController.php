<?php

namespace App\Http\Controllers\Administrative\Service;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Config\Config;
use Illuminate\Http\Request;

class ServiceController extends AppController
{
    public $table = 'Administrative\Config\Config';
    
    public function index(Request $request)
    {
        try
        {
            $data = Config::where([['config_name', $request->config], ['is_active', 'Yes']])->first();
            $conf = !empty($data->config_id) ? $data : ['config_value' => '', 'config_img' => ''];
            return $this->app_response('Succes get detail', $conf);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id=null)
    {
        try
        {
            $val = $request->input('config_value');
            if (!empty($val))
            {
                $data   = Config::where([['config_name', $request->input('config_name')], ['is_active', 'Yes']])->first();
                $id     = !empty($data->config_id) ? $data->config_id : null;
                $path   = $request->input('config_name') == 'TermsConditions' ? 'terms' : 'policy';
                return $this->db_save($request, $id, ['path' => 'service/'. $path .'/img']);
            }
            return $this->app_response('Data successfully saved !', []);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}