<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Transaction\StagingAsset;
use App\Models\Users\Investor\Investor;
use Illuminate\Http\Request;
use DB;

class SukukMiddlewareController extends AppController
{
    /**
     * @return void
     */
    public function getData(Request $request)
    {
        try
        {
	       ini_set('max_execution_time', '14400');
            $asset      = [];
            $success    = $fails = 0;
            $investor   = Investor::where('is_active', 'Yes')->get();
            
	        foreach ($investor as $inv)
            {
                $api = $this->api_ws(['sn' => 'InvestorAsset', 'val' => [$inv->cif]])->original['data'];
                if (!empty($api))
                {
                    foreach ($api as $a)
                    {
                        $save = $this->save($request, $inv, $a);
                        if (!isset($save->success) || !$save->success)
                        {
                            $message = !empty($save->message) ? $save->message : $save->error_msg;
                            $asset[] = ['investor_id' => $inv->investor_id, 'message' => $message];
                            $fails++;
                        }
                        else
                        {
                            $asset[] = $save;
                            $success++;
                        }
                    }
                }
            }

            DB::statement('SELECT move_stg_asset_sukuk()');
            
            return $this->app_partials($success, $fails, $asset);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function save($request, $inv, $a)
    {
	    try
        {
            ini_set('max_execution_time', '14400');
            $data = [
                'cif'                   => $a->cif ?? null,
                'fullname'              => $inv->fullname ?? null, 
                'product_code'          => $a->productCode ?? null,
                'product_name'          => $a->productName ?? null, 
                'product_type'          => 'SUKUK', 
                'account_no'            => $a->accountNo ?? null, 
                'outstanding_date'      => $a->balanceDate ?? null, 
                'outstanding_unit'      => $a->unitOutstanding ?? null,
                'currency'              => $a->currencyCode ?? null,
                'placement_amt'         => $a->balanceAmount ?? null, 
                'created_by'            => 'MDW', 
                'created_host'          => 'MDW'
            ];
                     
            $save = StagingAsset::create($data);
        	return (object) ['success' => true, 'data' => $data]; 
	    }
        catch(\Exception $e)
        {
            return (object) ['success' => false, 'message' => $e->getMessage()];
        }       
    }
}