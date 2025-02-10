<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\SA\Assets\Products\Product;
use App\Models\Users\Investor\Investor;
use Illuminate\Http\Request;

class AssetOutstandingController extends AppController
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
                $api = $this->api_ws(['sn' => 'InvestorAsset', 'val' => [$inv->cif]])->original['data'];
                
                if (!empty($api))
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
        	$product    = Product::where([['ext_code', $a->productCode], ['is_active', 'Yes']])->first();        
        	if (!empty($inv->investor_id) && !empty($product->product_id))
        	{
            		$out_id = $this->getOutstandingID($inv->investor_id, $product->product_id, $a->accountNo);
            		$act    = empty($out_id->outstanding_id) ? 'cre' : 'upd';
            		$data   = [
                		'investor_id'           => $inv->investor_id,
                		'product_id'            => $product->product_id,
                		'account_no'            => !empty($a->accountNo) ? $a->accountNo : null,
                		'outstanding_date'      => $this->app_date(),
                		'subscription_date'     => !empty($a->balanceDate) ? $a->balanceDate : null,
                		'due_date'              => !empty($a->maturityDate) ? $a->maturityDate : null,
                		'outstanding_unit'      => !empty($a->unitOutstanding) ? $a->unitOutstanding : null,
                		'balance_amount'        => !empty($a->balanceAmount) ? $a->balanceAmount : null,
                		'balance_amount_wms'    => !empty($a->balanceAmount) ? $a->balanceAmount : null,
                		'total_subscription'    => !empty($a->totalInvestmentAmount) ? $a->totalInvestmentAmount : null,
                		'total_unit'            => !empty($a->totalInvestmentUnit) ? $a->totalInvestmentUnit : null,
                		'return_amount'         => !empty($a->returnAmount) ? $a->returnAmount : null,
                		'return_percentage'     => !empty($a->returnPercentage) ? $a->returnPercentage : null,
                		// 'deposit'            => !empty($a->depositAzorig) ? $a->depositAzorig : null,
                		// 'deposit_amount'        => !empty($a->depositAmount) ? $a->depositAmount : null,
                		// 'deposit_Mud_Tenor'     => !empty($a->depositMudTenor) ? $a->depositMudTenor : null,
                		// 'deposit_Mud_Bilyet'    => !empty($a->depositMudBilyet) ? $a->depositMudBilyet : null,
                		// 'deposit_Mud_Nisbah'    => !empty($a->depositMudNisbah) ? $a->depositMudNisbah : null,
                		// 'deposit_Arooption'     => !empty($a->depositArooption) ? $a->depositArooption : null,
                		// 'deposit_Value_Date'    => !empty($a->depositValueDate) ? $a->depositValueDate : null,
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
                ->where([['is_active', 'Yes'], ['investor_id', $inv_id], ['product_id', $prod_id], ['account_no', $acc_no], ['outstanding_date', $this->app_date()]])
                ->first();
        return $out_id;
    }
}