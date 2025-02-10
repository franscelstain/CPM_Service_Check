<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Products\Issuer;
use Illuminate\Http\Request;

class IssuerController extends AppController
{
    public $table = 'SA\Assets\Products\Issuer';

    public function index()
    {
        return $this->db_result();
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id, ['path' => 'issuer/img']);
    }
    
    public function total_issuer()
    {
        try
        {
            return $this->app_response('Total Issuer', Issuer::where('is_active', 'Yes')->count());
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
            $api = $this->api_ws(['sn' => 'Issuer'])->original['data'];
            foreach ($api as $a)
            {                
                $qry    = Issuer::where([['ext_code', $a->code]])->first();
                $id     = !empty($qry->issuer_id) ? $qry->issuer_id : null;
                $request->request->add([
                    'issuer_name'   => $a->name,
                    'ext_code'      => $a->code,
                    'is_data'       => !empty($id) ? $qry->is_data : 'WS',
                    '__update'      => !empty($id) ? 'Yes' : ''
                ]);
                $this->db_save($request, $id);

                if (empty($id))
                    $insert++;
                else
                    $update++;
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}