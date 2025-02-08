<?php

namespace App\Http\Controllers\SA\Reference\KYC;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\Group;
use App\Models\SA\Reference\KYC\Region;
use Illuminate\Http\Request;

class RegionsController extends AppController
{
    public $table = 'SA\Reference\KYC\Region';

    public function index(Request $request)
    {
        try
        {
            $reg_type = $this->region_type($request);
            if ($reg_type != 'Provinsi')
            {
                $filter = !empty($request->parent_code) ? [['m_regions.parent_code', $request->parent_code]] : [];
                $data   = Region::select('m_regions.*', 'b.region_name as parent1')->join('m_regions as b', 'm_regions.parent_code', '=', 'b.region_code')->where('b.is_active', 'Yes');
                if ($reg_type == 'Kecamatan')
                    $data = $data->select('m_regions.*', 'b.region_name as parent1', 'c.region_name as parent2')->join('m_regions as c', 'b.parent_code', '=', 'c.region_code')->where('c.is_active', 'Yes');
            }
            $region = $reg_type == 'Provinsi' ? Region::where([['m_regions.region_type', $reg_type], ['m_regions.is_active', 'Yes']]) : $data->where(array_merge([['m_regions.region_type', $reg_type], ['m_regions.is_active', 'Yes']], $filter));
            return $this->app_response('Regions', ['key' => 'region_id', 'list' => $region->get()]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function import(Request $request)
    {
        return $this->app_import($request, ['region_type' => $this->region_type($request)]);
    }

    public function detail(Request $request, $id)
    {
        return $this->db_detail($id, ['filter' => [[['region_type' => $this->region_type($request)]]]]);
    }
    
    public function region_type($request)
    {
        $path = explode('/', $request->path());
        switch ($path[2])
        {
            case 'city'             : $rt = 'Kota / Kab.'; break;
            case 'sub-district'     : $rt = 'Kecamatan'; break;
            case 'urban-village'    : $rt = 'Kelurahan'; break;
            default                 : $rt = 'Provinsi';
        }
        return $rt;
    }

    public function save(Request $request, $id = null)
    {
        $type = $this->region_type($request);
        $code = $type != 'Provinsi' ? $request->input('parent_code') .'.'. $request->input('region_code') : $request->input('region_code');
        $request->request->add(['region_type' => $type, 'region_code' => $code]);
        return $this->db_save($request, $id);
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $grp    = Group::where([['group_name', 'Region'], ['is_active', 'Yes']])->first();
            if (!empty($grp->group_id))
            {
                $code   = !empty($grp->ext_code) ? $grp->ext_code : 0;
                $api    = $this->api_ws(['sn' => 'ReferenceGroupDetail', 'val' => [$code]])->original['data'];
                foreach ($api as $a)
                {
                    $c_code = count(explode('.', $a->code));
                    switch ($c_code)
                    {
                        case 1  : $parent = null; $type = 'Provinsi'; break;
                        case 2  : $parent = substr($a->code, 0, 2); $type = 'Kota / Kab.'; break;
                        case 3  : $parent = substr($a->code, 0, 5); $type = 'Kecamatan'; break;
                        default : $parent = null; $type = 'Kelurahan';
                    }
                    $qry    = Region::where([['region_code', $a->code]])->first();
                    $id     = !empty($qry->region_id) ? $qry->region_id : null;
                    $request->request->add([
                        'region_name'   => $a->name,
                        'region_code'   => $a->code,
                        'parent_code'   => $parent,
                        'region_type'   => $type,
                        'is_data'       => !empty($id) ? $qry->is_data : 'WS',
                        '__update'      => !empty($id) ? 'Yes' : ''
                    ]);
                    $this->db_save($request, $id);

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