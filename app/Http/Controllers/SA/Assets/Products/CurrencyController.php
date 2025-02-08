<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Products\Currency;
use App\Models\SA\Reference\Group;
use Illuminate\Http\Request;

class CurrencyController extends AppController
{
    public $table = 'SA\Assets\Products\Currency';

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
            $grp    = Group::where([['group_name', 'Currency'], ['is_active', 'Yes']])->first();
            if (!empty($grp->group_id))
            {
                $api    = $this->api_ws(['sn' => 'ReferenceGroupDetail', 'val' => [$grp->ext_code]])->original['data'];
                foreach ($api as $a)
                {                
                    $qry    = Currency::where([['currency_code', $a->code]])->first();
                    $id     = !empty($qry->currency_id) ? $qry->currency_id : null;
                    $request->request->add([
                        'currency_name' => $a->name,
                        'currency_code' => $a->code,
                        'is_data'       => !empty($id) ? $qry->is_data : 'WS',
                        '__update'      => !empty($id) ? 'Yes' : ''
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