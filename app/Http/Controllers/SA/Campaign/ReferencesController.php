<?php

namespace App\Http\Controllers\SA\Campaign;

use App\Http\Controllers\AppController;
use App\Models\SA\Campaign\Reference;
use Illuminate\Http\Request;
use App\Models\SA\Assets\Portfolio\Models;
use Illuminate\Support\Facades\DB;

class ReferencesController extends AppController
{
    public $table = 'SA\Campaign\Reference';

    public function index(Request $request)
    {
        return $this->db_result(['where' => [['campaign_ref_type', $this->refUri($request)]]]);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function refAttribute()
    {
        try
        {
            $attr = [];
            $data = Reference::where([['is_attribute', 'Yes'], ['is_active', 'Yes']])->whereIn('campaign_ref_type', ['cart-item', 'investor', 'product'])->get();
            foreach ($data as $dt)
            {
                $attr[$dt->campaign_ref_type][] = ['campaign_ref_id' => $dt->campaign_ref_id, 'campaign_ref_name' => $dt->campaign_ref_name];
            }
            return $this->app_response('Campaign Attribute', $attr);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function refUri($request)
    {
        $uri = explode('/', $request->getRequestUri());
        return $uri[4];
    }

    public function save(Request $request, $id = null)
    {
        $attr = in_array($this->refUri($request), ['cart-item', 'product', 'investor']) ? 'Yes' : 'No';
        $request->request->add(['campaign_ref_type' => $this->refUri($request), 'is_attribute' => $attr]);
        return $this->db_save($request, $id);
    }

    public function get_campaign_ref($id) 
    {
        $data = Reference::where([['campaign_ref_id', $id],['is_active', 'Yes']])->first();
        return $this->app_response('campaign_ref', $data);
    }

    public function list($id) 
    {
        $data = Reference::where([['campaign_ref_id', $id], ['is_active', 'Yes']])->first();
        if(!empty($data) && $data->model_name!=null)
        {
            $model = strtolower($data->model_name);  
            $model = str_replace(";", ",", $model);
            $f = explode(',', $model);  
            
            $data = DB::table($f[0])->selectRaw($f[1]." as id, ".$f[2]." as text")
                    ->where('is_active', 'Yes')->get();  

            return $this->app_response($id, $data);
        } else {
            return $this->app_response($id, "no model with id $id or null ");
        }
    }

    public function attr_list() 
    {
        $list = [];
        $data = Reference::select(['campaign_ref_id','model_name'])->where([['campaign_ref_ui','select'],['is_active', 'Yes']])->get();
        foreach($data as $dt) {
            if (!empty($dt->model_name)) {
                $model = strtolower($dt->model_name);  
                $model = str_replace(";", ",", $model);
                $f = explode(',', $model);  
                if (!isset($f[1]) or !isset($f[2]) ) continue;
                $dat = DB::table($f[0])->selectRaw($f[1]." as id, ".$f[2]." as text")
                    ->where('is_active', 'Yes')->get();  
                $list[$dt->campaign_ref_id] = $dat;
            }
        }
        return $this->app_response('attr_list', $list);
    }
    
}