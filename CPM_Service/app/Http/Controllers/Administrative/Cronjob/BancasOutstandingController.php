<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\SA\Assets\Products\Product;
use App\Models\Users\Investor\Investor;
use Illuminate\Http\Request;

class BancasOutstandingController extends AppController
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
            $investor   = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])->get();
            
	        foreach ($investor as $inv)
            {
                $api = $this->api_ws(['sn' => 'InvestorBancass', 'val' => [$inv->cif]])->original['message']->Result->ProductList;
                if(!empty($api))
                {
                    foreach ($api as $a)
                    { 
                        $save = $this->save($request, $inv, $a);
                        
                        if (!$save->success)
                        {
                            $asset[] = ['investor_id' => $inv->investor_id, 'message' => $save->message];
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
        	$data       = [];
            $save       = [];
        	$product    = Product::where([['ext_code', trim($a->ProductName)], ['is_active', 'Yes']])->first();    
    
        	if (!empty($inv->investor_id) && !empty($product->product_id))
        	{
            		$out_id = $this->getOutstandingID($inv->investor_id, $product->product_id, $a->ProductCode);
                    $act    = empty($out_id->outstanding_id) ? 'cre' : 'upd';
            		$data   = [
                		'investor_id'           => $inv->investor_id,
                		'product_id'            => $product->product_id,
                		'account_no'            => !empty($a->ProductCode) ? $a->ProductCode : null,
                		'outstanding_date'      => $this->app_date(),
                		'subscription_date'     => $this->app_date(),
                		'balance_amount'        => !empty($a->TotalUnits) ? $a->TotalUnits : null,
                		'total_subscription'    => !empty($a->TotalAmount) ? $a->TotalAmount : null,
                		'total_unit'            => !empty($a->TotalUnits) ? $a->TotalUnits : null,
                        'regular_payment'       => !empty($a->UGL) ? $a->UGL : null,
                		$act.'ated_by'          => 'System',
                		$act.'ated_host'        => '::1'
            		];
            
            		$request->request->replace($data);
            
            		$save = empty($out_id->outstanding_id) ? AssetOutstanding::create($data) : AssetOutstanding::where('outstanding_id', $out_id->outstanding_id)->update($data);
        	}

         	return (object)['success' => true, 'data' => $data]; 
	}
        catch(\Exception $e)
        {
            return (object)['success' => false, 'message' => $e->getMessage()];
        }
       
    }

    function getOutstandingID ($inv_id, $prod_id, $acc_no)
    {
        $out_id = AssetOutstanding::select('outstanding_id')
                ->where([['is_active', 'Yes'], ['investor_id', $inv_id], ['product_id', $prod_id], ['account_no', $acc_no]])
                ->first();
        return $out_id;
    }
}