<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\Transaction\TransactionFeeOutstanding;
use App\Models\Users\Investor\Investor;
use App\Models\SA\Assets\Products\Product;
use App\Models\Users\User;
use Illuminate\Http\Request;

class LiabilitiesOutstandingController extends AppController
{
    /**
     * @return void
     */
    public function getData()
    {
        try
        {
            ini_set('max_execution_time', 14400); 
            $liabilities    = [];
            $investor       = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])->get();
            foreach ($investor as $inv)
            {
                $api = $this->api_ws(['sn' => 'InvestorLiabilities', 'val' => [$inv->cif]])->original['data'];
                foreach ($api as $a)
                {
                    $data = [
                        'investor_id'           => $inv->investor_id,
                        'liabilities_id'        => !empty($a->liabilitiesID) ? $a->liabilitiesID : null,
                        'liabilities_name'      => !empty($a->liabilitiesName) ? $a->liabilitiesName : null,
                        'outstanding_date'      => !empty($a->outstandingDate) ? $a->outstandingDate : null,
                        'account_id'            => !empty($a->accountID) ? $a->accountID : null,
                        'outstanding_balance'   => !empty($a->outstandingBalance) ? $a->outstandingBalance : null,
                        'due_date'              => !empty($a->dueDate) ? $a->dueDate : null,
                        'tenor'                 => !empty($a->tenor) ? $a->tenor : null,
                        'created_by'            => 'System',
                        'created_host'          => '::1'
                    ];
                    
                    $liab           = LiabilityOutstanding::where([['investor_id', $inv->investor_id], ['outstanding_date', $a->outstandingDate], ['liabilities_id', $a->liabilitiesID]])->first();
                    $save           = empty($liab) ? LiabilityOutstanding::create($data) : LiabilityOutstanding::where([['investor_id', $inv->investor_id], ['outstanding_date', $a->outstandingDate], ['liabilities_id', $a->liabilitiesID], ['is_active', 'Yes']])->update($data);
                    $liabilities[]  = $data;
                }
            }
            return $this->app_response('Liabilities Outstanding', $liabilities);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }

    public function fee_outstanding(Request $request)
    {
        try
        {
            $liabilities    = [];
            $success        = $fails = 0;
            $investor       = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])->get();

            foreach ($investor as $inv)
            {
                $api = $this->api_ws(['sn' => 'FeeBaseIncome', 'val' => [$inv->cif, $this->app_date()]])->original['data'];
                
                if (!empty($api))
                {
                    foreach ($api as $a) 
                    {
                        $save           = $this->save_fee_outstanding($request, $inv, $a);
                        $liabilities[]  = $save;
                        
                        if (!$save)
                            $fails++;
                        else
                            $success++;
                    }  
                }        
            }
            return $this->app_partials($success, $fails, $liabilities);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }

    public function save_fee_outstanding($request, $inv, $a)
    {
        $data               = [];
        $product            = Product::where([['product_code', $a->productCode], ['is_active', 'Yes']])->first();
        $user               = User::where('user_code', $a->salesCode)->first();
        $sales              = !empty($user->user_id) ? ['user_id', $user->user_id] : ['sales_code', $a->salesCode];
        $fee_outstanding    = TransactionFeeOutstanding::where([['fee_date', $a->date], ['fee_category', $a->feeCategory], ['product_id', $product->product_id], ['investor_id', $inv->investor_id], ['is_active', 'Yes'], $sales])->first();
        $act                = empty($fee_outstanding->fee_outstanding_id) ? 'cre' : 'upd';
        
        if (!empty($product->product_id))
        {
            $data = [
                'investor_id'       => $inv->investor_id,
                'product_id'        => $product->product_id,
                'user_id'           => !empty($user->user_id) ? $user->user_id : null,
                'fee_date'          => !empty($a->date) ? $a->date : null,
                'fee_category'      => !empty($a->feeCategory) ? $a->feeCategory : null,
                'fee_amount'        => !empty($a->feeAmount) ? $a->feeAmount : null,
                'sales_code'        => !empty($a->salesCode) ? $a->salesCode : null,
                'is_active'         => 'Yes',
                $act.'ated_by'      => 'System',
                $act.'ated_host'    => '::1'
            ];

            $save = empty($fee_outstanding->fee_outstanding_id) ? TransactionFeeOutstanding::create($data) : TransactionFeeOutstanding::where('fee_outstanding_id', $fee_outstanding->fee_outstanding_id)->update($data);

            return $save ? $data : $save;
        }
        return $data;
    }
}