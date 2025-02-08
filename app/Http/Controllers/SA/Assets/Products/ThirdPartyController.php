<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Products\ThirdParty;
use Illuminate\Http\Request;

class ThirdPartyController extends AppController
{
    public $table = 'SA\Assets\Products\ThirdParty';

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
        return $this->db_save($request, $id);
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $api = $this->api_ws(['sn' => 'ThirdParty'])->original['data'];
            foreach ($api as $a)
            {                
                $qry    = ThirdParty::where([['ext_code', $a->code]])->first();
                $id     = !empty($qry->thirdparty_id) ? $qry->thirdparty_id : null;
                $request->request->add([
                    'thirdparty_name'   => $a->name,
                    'ext_code'          => $a->code,
                    'is_data'           => !empty($id) ? $qry->is_data : 'WS',
                    '__update'          => !empty($id) ? 'Yes' : ''
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