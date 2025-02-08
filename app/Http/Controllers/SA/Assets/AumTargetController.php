<?php

namespace App\Http\Controllers\SA\Assets;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\AssetCategory;
use App\Models\SA\Assets\AumTarget;
use Illuminate\Http\Request;

class AumTargetController extends AppController
{
    public $table = 'SA\Assets\AumTarget';

    public function index()
    {
        try
        {
            $aum    = [];
            $asset  = $this->asset_category();
            $data   = AumTarget::where('is_active', 'Yes')->get();
            foreach ($data as $dt)
            {
                $aum[] = [
                    'asset_category'    => implode(', ', array_map(function($id) use ($asset) { return !empty($asset[$id]) ? $asset[$id] : ''; }, $dt->asset_category)),
                    'effective_date'    => $dt->effective_date,
                    'id_aum_target'     => $dt->id_aum_target,
                    'status_active'     => $dt->status_active,
                    'target_aum'        => number_format($dt->target_aum)
                ];
            }
            return $this->app_response('Target AUM', ['key' => 'id_aum_target', 'list' => $aum]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
        return $this->db_result();
    }
    
    private function asset_category()
    {
        $cat    = [];
        $data   = AssetCategory::where('is_active', 'Yes')->get();
        foreach ($data as $dt)
        {
            $cat[$dt->asset_category_id] = $dt->asset_category_name; 
        }
        return $cat;
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
        $request->request->add(['target_aum' => str_replace(',', '', $request->target_aum)]);
        return $this->db_save($request, $id);
    }
}