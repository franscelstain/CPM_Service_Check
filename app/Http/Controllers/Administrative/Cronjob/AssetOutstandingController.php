<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\SA\Assets\Products\Product;
use App\Models\Users\Investor\Investor;
use Carbon\Carbon;
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
                        if (!isset($save->success) || !$save->success)
                        {
                             $message = '';
                            if(!empty($save->message))
                            {
                                $message = $save->message;
                            }elseif(!empty($save->error_msg)){
                                $message = $save->error_msg;
                            }
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
                		'total_subscription'    => !empty($a->totalSubscriptionAmount) ? $a->totalSubscriptionAmount : null,
                		'total_unit'            => !empty($a->totalInvestmentUnit) ? $a->totalInvestmentUnit : null,
                		'return_amount'         => !empty($a->returnAmount) ? $a->returnAmount : null,
                		'return_percentage'     => !empty($a->returnPercentage) ? $a->returnPercentage : null,
                        'avg_unit_cost'         => !empty($a->avgUnitCost) ? $a->avgUnitCost : null,
                        'investment_amount'     => !empty($a->totalInvestmentAmount) ? $a->totalInvestmentAmount : null,
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
        ini_set('max_execution_time', '14400');
        $out_id = AssetOutstanding::select('outstanding_id')
                ->where([['is_active', 'Yes'], ['investor_id', $inv_id], ['product_id', $prod_id], ['account_no', $acc_no], ['outstanding_date', $this->app_date()]])
                ->first();
        return $out_id;
    }

    public function deleteOldAssetOutstandings()
    {
        // Mendapatkan tanggal satu tahun yang lalu
        $lastDateBeforePeriod = Carbon::now()->subMonths(12)->startOfMonth()->format('Y-m-d');

        // 1. Menghapus semua data sebelum tanggal satu tahun yang lalu
        AssetOutstanding::where('outstanding_date', '<', $lastDateBeforePeriod)
            ->delete();

        // 2. Mendapatkan data dari bulan lalu hingga satu tahun terakhir (misalnya Maret 2024 hingga Februari 2025)
        $endPeriod = Carbon::now()->subMonths(1)->endOfMonth()->format('Y-m-d');
        
        $assetsToKeep = AssetOutstanding::whereBetween('outstanding_date', [$lastDateBeforePeriod, $endPeriod])
            ->selectRaw('MAX(outstanding_date) as last_date, EXTRACT(YEAR FROM outstanding_date) as year, EXTRACT(MONTH FROM outstanding_date) as month')
            ->groupByRaw('EXTRACT(YEAR FROM outstanding_date), EXTRACT(MONTH FROM outstanding_date)')
            ->get();

        $currentDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        AssetOutstanding::where('outstanding_date', '<', $currentDate)
            ->whereNotIn('outstanding_date', $assetsToKeep->pluck('last_date'))
            ->chunk(100, function ($assets) {
                foreach ($assets as $asset) {
                    $asset->delete();
                }
            });
        
        return response()->json(['message' => 'Old asset outstanding data cleaned up successfully.']);
    }
}