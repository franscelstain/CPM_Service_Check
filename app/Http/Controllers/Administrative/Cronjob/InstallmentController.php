<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Transaction\TransactionInstallment;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Transaction\Reference;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\Account;
use Illuminate\Http\Request;

class InstallmentController extends AppController
{
    public function getData(Request $request)
    {
        try
        {
        	ini_set('max_execution_time', '14400');
		    $trans      = [];
		    $success    = $fails = 0;
		    $product    = Product::where('is_active', 'Yes')->get();
		  
		    foreach ($product as $prd)
		    {
		    	$product_code   = !empty($prd->ext_code) || !empty($prd->product_code) ? !empty($prd->ext_code) ? $prd->ext_code : $prd->product_code : '';
		        $api = $this->api_ws(['sn' => 'TransactionInstallment', 'val' => [$product_code]])->original['message']->Result->InstallmentInquiries; 
		        if (!empty($api))
                {
                    foreach ($api as $a)
                    {
                        $save = $this->save($prd, $a);
                        if (!$save->success)
                        {
                            $trans[] = ['product_code' => $prd->ext_code, 'message' => $save->message];
                            $fails++;
                        }
                        else
                        {
                            $trans[] = $save;
                            $success++;
                        }
                    }
                }
		        	
		    }
			return $this->app_partials($success, $fails, $trans);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }	

    private function save($prd, $a)
    {
		try
        {
        	$data       = [];
        	$save       = [];
			$investor   = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes'], ['cif', $a->CIF]])->first();
			
			if (!empty($investor->investor_id) && !empty($prd->product_id))
        	{
        		$acc = Account::where([['account_no', $a->BankAccountNo], ['investor_id', $investor->investor_id], ['is_active', 'Yes']])->first();
        		$ins_id = $this->getInstallmentID($investor->investor_id, $prd->product_id, $a->RegisterID);
                
				$act    = empty($ins_id->trans_installment_id) ? 'cre' : 'upd';
				$data   =[
					'investor_id'			=> $investor->investor_id,
					'product_id'    		=> !empty($prd->product_id) ? $prd->product_id : null,
                	'investor_account_id'	=> !empty($acc->investor_account_id) ? $acc->investor_account_id : null,
					'account_no'			=> !empty($a->InvestmentAccountNo) ? $a->InvestmentAccountNo : null,
					'investment_amount'		=> !empty($a->InvestmentAmount) ? $a->InvestmentAmount : null,
					'registered_id'			=> !empty($a->RegisterID) ? $a->RegisterID : null,
					'start_date'			=> !empty($a->StartDate) ? $a->StartDate : null,
					'tenor_month'			=> !empty($a->Tenor) ? $a->Tenor : null,
					'debt_date'				=> !empty($a->DebitDate) ? $a->DebitDate : null,
					'status'				=> !empty($a->Status) ? $a->Status : null,
					'is_active'             => 'Yes',
					$act.'ated_by'  		=> 'System',
                    $act.'ated_host'		=> '::1'
				];

				// $request->request->replace($data);
            	// $data_tmp[] = $data;
            	$save = empty($ins_id->trans_installment_id) ? TransactionInstallment::create($data) : TransactionInstallment::where('trans_installment_id', $ins_id->trans_installment_id)->update($data);
        	}
        	return (object)['success' => true, 'data' => $save]; 
		}
        catch(\Exception $e)
        {
              return (object)['success' => false, 'message' => $e->getMessage()];
        }   
    }

    function getInstallmentID ($inv_id, $prod_id, $registered_id)
    {
        $instal_id = TransactionInstallment::select('trans_installment_id')
                ->where([['is_active', 'Yes'], ['investor_id', $inv_id], ['product_id', $prod_id]]);
        if(empty($registered_id))
        {
            $instal_id->where(function($qry){
                $qry->whereNull('registered_id')->orWhere('registered_id', '');
            });
        }else{
            $instal_id->where('registered_id', $registered_id);
        }
                
        return $instal_id->first();
    }
}