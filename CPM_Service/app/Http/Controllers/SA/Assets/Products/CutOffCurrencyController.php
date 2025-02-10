<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Products\Currency;
use App\Models\SA\Assets\Products\CutOffCurrency;
use Illuminate\Http\Request;

class CutOffCurrencyController extends AppController
{
    public $table = 'SA\Assets\Products\CutOffCurrency';

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
    
    // public function ws_data(Request $request)
    // {
    //     try
    //     {
    //         $insert = $update = 0;
    //         $data   = [];
    //         $currency = Currency::where('is_active', 'Yes')->get();
    //         foreach ($currency as $cur) 
    //         {
    //             if(!empty($cur->currency_id))
    //             {
    //                 $qry    = CutOffCurrency::where('is_active', 'Yes')->first();
    //                 $api    = $this->api_ws(['sn' => 'CutOff'])->original['data'];
    //                 $id     = !empty($cur->cut_off_time_id) ? $cur->cut_off_time_id : null;
    //                 $act    = empty($qry->cut_off_time_id) ? 'cre' : 'upd';
    //                 $request->request->add([
    //                     'currency_id'        => $cur->currency_id,
    //                     'ext_code'           => $api->tconfigId,
    //                     'cut_off_time_name'  => $cur->currency_name,  
    //                     'cut_off_time_value' => $api->value,
    //                     'is_data'            => 'WS',
    //                     'is_active'         => 'Yes',
    //                     $act.'ated_by'       => 'System',
    //                     $act.'ated_host'     => '::1'
    //                 ]);
    //                 // return $id;
    //                 $this->db_save($request, $id);

    //                 if (empty($cur->currency_id))
    //                     $insert++;
    //                 else
    //                     $update++;
    //             }

    //         }
    //         // return $request;
    //         return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
    //     }
    //     catch (\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     }
    // }

    public function ws_data(Request $request)
    {
        try
        {
            $insert = $update = 0;
            $data   = [];
            $currency = Currency::where('is_active', 'Yes')->get();
            foreach ($currency as $cur) 
            {
                    $qry    = CutOffCurrency::where('currency_id', $cur->currency_id)->first();
                    $api    = $this->api_ws(['sn' => 'CutOff'])->original['data'];
                    $id     = !empty($cur->cut_off_time_id) ? $cur->cut_off_time_id : null;
                    $act    = empty($qry->cut_off_time_id) ? 'cre' : 'upd';	 

                    $data = [
                        'currency_id'        => $cur->currency_id,
                        'ext_code'           => $api->tconfigId,
                        'cut_off_time_name'  => $cur->currency_name,  
                        'cut_off_time_value' => $api->value,
                        'is_data'            => 'WS',
                        'is_active'          => 'Yes',
                       	$act.'ated_by' 						=> 'System',
	    				$act.'ated_host'					=> '::1'
                    ];
					// return CutOffCurrency::create($data);
                     $save = empty($qry->cut_off_time_id) ? CutOffCurrency::create($data) : CutOffCurrency::where('currency_id',$qry->cut_off_time_id)->update($data);
           

            }
            return $this->app_response('Transaction WMS', $save);
            // return $data;
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}