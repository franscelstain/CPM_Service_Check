<?php

namespace App\Http\Controllers\SA\Assets;

use App\Models\SA\Assets\AssetClass;
use App\Models\SA\Reference\Group;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class AssetClassController extends AppController
{
    public $table = 'SA\Assets\AssetClass';

    public function index()
    {
        try
        {
            $data = AssetClass::select('m_asset_class.*', 'asset_category_name')
                    ->leftJoin('m_asset_categories as b', function($qry) {
                        $qry->on('m_asset_class.asset_category_id', '=', 'b.asset_category_id')->where('b.is_active', 'Yes');
                    })
                    ->where('m_asset_class.is_active', 'Yes')->get();
            return $this->app_response('Asset Class', ['key' => 'asset_class_id', 'list' => $data]);
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

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
    
    public function total_assetclass()
    {
        try
        {
            $total  = AssetClass::join('m_asset_categories','m_asset_categories.asset_category_id','=','m_asset_class.asset_category_id')
                    ->where([['m_asset_categories.is_active', 'Yes'], ['m_asset_class.is_active', 'Yes'], ['asset_category_name', 'not ilike', 'index']])
                    ->count();
            return $this->app_response('Total Asset Class', $total);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $grp    = Group::where([['group_name', 'ProductCategory'], ['is_active', 'Yes']])->first();
            if (!empty($grp->group_id))
            {
                $api = $this->api_ws(['sn' => 'ReferenceGroupDetail', 'val' => [$grp->ext_code]])->original['data'];
                foreach ($api as $a)
                {                
                    $qry    = AssetClass::where([['asset_class_code', $a->code]])->first();
                    $id     = !empty($qry->asset_class_id) ? $qry->asset_class_id : null;
                    $request->request->add([
                        'asset_class_name'  => $a->name,
                        'asset_class_code'  => $a->code,
                        'is_data'           => !empty($id) ? $qry->is_data : 'WS',
                        '__update'          => !empty($id) ? 'Yes' : ''
                    ]);
                    $this->db_save($request, $id, ['validate' => true]);

                    if (empty($id))
                        $insert++;
                    else
                        $update++;
                }
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}