<?php
namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\AppController;
use App\Models\Transaction\TransactionInstallment;
use App\Models\Users\Investor\Investor;
use App\Models\SA\Assets\Products\Product;
use App\Models\Users\Investor\Account;
use Illuminate\Http\Request;
use Auth;

class InstallmentController extends AppController
{
    public function index(Request $request)
    {
        try
        {
            $user   = $this->auth_user()->usercategory_name == 'Investor' ? 'b.investor_id' : 'b.sales_id'; 
            $limit  = !empty($request->limit) ? $request->limit : 10;
            $page   = !empty($request->page) ? $request->page : 1;
            $offset = ($page-1)*$limit;
            $data   = TransactionInstallment::select('t_installments.*', 'b.fullname', 'b.photo_profile', 'b.cif', 'c.*', 'd.asset_class_name', 'h.issuer_logo', 'i.account_no as bank_account_no', 'i.account_name as bank_account_name','j.asset_category_name')
                    ->join('u_investors as b', 't_installments.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_installments.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { return $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as h', function($qry) { return $qry->on('c.issuer_id', '=', 'h.issuer_id')->where('h.is_active', 'Yes'); })
                    ->leftJoin('u_investors_accounts as i', function($qry) { return $qry->on('t_installments.investor_account_id', '=', 'i.investor_account_id')->where('i.is_active', 'Yes'); })
                    ->leftJoin('m_asset_categories as j', function($qry) { return $qry->on('d.asset_category_id', '=', 'j.asset_category_id')->where('j.is_active', 'Yes'); })
                    ->where([[$user, $this->auth_user()->id], ['t_installments.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->orderBy('t_installments.trans_installment_id', 'desc');
            
            if (!empty($request->search))
            {
               $data  = $data->where(function($qry) use ($request) {
                            $qry->where('c.product_name', 'ilike', '%'. $request->search .'%')
                                ->orWhere('t_installments.portfolio_id', 'ilike', '%'. $request->search .'%')
                                ->orWhere('t_installments.account_no', 'ilike', '%'. $request->search .'%');
                        });
            }
            if (!empty($request->balance_minimum))
                $data = $data->where('t_installments.investment_amount', '>=', $request->balance_minimum);
            if (!empty($request->balance_maximum))
                $data = $data->where('t_installments.investment_amount', '<=', $request->balance_maximum);

            return $this->app_response('Portfolio Installment', $data->paginate($limit, ['*'], 'page', $page));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }   

     public function detail(Request $request)
    {
        try
        {
            $user   = $this->auth_user()->usercategory_name == 'Investor' ? 'b.investor_id' : 'b.sales_id'; 
            $data =  TransactionInstallment::select('t_installments.*', 'b.fullname', 'b.photo_profile', 'b.cif', 'c.*', 'd.asset_class_name', 'h.issuer_logo', 'i.account_no as bank_account_no', 'i.account_name as bank_account_name','j.asset_category_name')
                    ->join('u_investors as b', 't_installments.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_installments.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { return $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as h', function($qry) { return $qry->on('c.issuer_id', '=', 'h.issuer_id')->where('h.is_active', 'Yes'); })
                    ->leftJoin('u_investors_accounts as i', function($qry) { return $qry->on('t_installments.investor_account_id', '=', 'i.investor_account_id')->where('i.is_active', 'Yes'); })
                    ->leftJoin('m_asset_categories as j', function($qry) { return $qry->on('d.asset_category_id', '=', 'j.asset_category_id')->where('j.is_active', 'Yes'); })
                    ->where([[$user, $this->auth_user()->id], ['t_installments.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->orderBy('t_installments.trans_installment_id', 'desc')
                    ->where('t_installments.trans_installment_id', $request->trans_installment_id)->first();
              
          return $this->app_response('Transaction', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    } 

    function inactive(Request $request)
    {
        try
        {

            if ($this->auth_user()->usercategory_name == 'Investor')
            {
                $cif        = $this->auth_user()->cif;

            }
            else
            {
                $investor   = Investor::where([['investor_id', $request->investor_id], ['is_active', 'Yes'], ['valid_account', 'Yes']])->first();
                $cif        = !empty($investor->cif) ? $investor->cif : '';  
            }

            if(!empty($cif))
            {
                $api = $this->api_ws(['sn' => 'TransactionInstallmentInactive', 'val' => [$cif,  $request->RegisterID]]);  

                if (!empty($api->original['message']))
                {
                    if(!empty($api->original['message']->Result))
                    {
                        $result     = $api->original['message']->Result;
                        $register   = $result->RegisterID;
                        $remarks    = $result->Remarks; 
                        $message    = $api->original['message'];
                        $message    = $api->original['message']->Message;                                              
                        $isSuccess  = $api->original['message']->IsSuccess;
                    }
                    if($api->original['message']->IsSuccess)
                    {
                        TransactionInstallment::where([['is_active', 'Yes'], ['registered_id',  $request->RegisterID]])->update(['status' => 'BREAK']);
                    }
                    else{
                      TransactionInstallment::where([['is_active', 'Yes'], ['registered_id',  $request->RegisterID]])->update(['wms_message' => $api->original['message']->Message]);
                    }
                }

            }

            return $this->app_response('Inactive', ['result' => $result, 'register_id' => $register, 'remarks' => $remarks, 'message' => $api->original['message'], 'isSuccess' => $api->original['message']->IsSuccess]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }  

    function checkout_save(Request $request)
    {
        try
        { 
            if ($this->auth_user()->usercategory_name == 'Investor')
                $investor_id = $this->auth_user()->investor_id;
            else
                $investor_id = $request->investor_id;
            
            $data                           = [];
            $manager                        = $this->db_manager($request);
            $data['investor_id']            = $investor_id;
            $data['product_id']             = $request->product_id;
            $data['investor_account_id']    = $request->investor_account_id;
            $data['portfolio_id']           = !empty($request->portfolio_id) ? $request->portfolio_id : null;
            $data['account_no']             = $request->account_no;
            $data['registered_id']          = !empty($request->registered_id) ? $request->registered_id : '';
            $data['debt_date']              = !empty($request->debt_date) ? $request->debt_date : null;
            $data['tenor_month']            = !empty($request->time_period) ? $request->time_period * 12 : null;
            $data['investment_amount']      = !empty($request->investment_amount) ? floatval($request->investment_amount) : 0;
            $data['fee_amount']             = !empty($request->fee_amount) ? round(floatval($request->fee_amount), 10) : 0;
            $data['tax_amount']             = !empty($request->tax_amount) ? floatval($request->tax_amount) : 0;
            $data['status']                 = 'ACTIVE';
            $data['start_date']             = $this->app_date();
            $data['created_by']             = $manager->user;
            $data['created_host']           =  $manager->ip ;
            $status_save                    = false;
            $status_error_message           = 'error: no message error from wms';
            
            if ($trans = TransactionInstallment::create($data))
            {
                if (!empty($trans->trans_installment_id))
                {
                    $investor   = Investor::where([['investor_id', $investor_id], ['is_active', 'Yes'], ['valid_account', 'Yes']])->first();
                    $product    = Product::where([['m_products.is_active', 'Yes'],['product_id',$request->product_id]])->first();

                    $sales_code = Investor::select('user_code')
                              ->join('u_users as u', 'u.user_id', '=', 'u_investors.sales_id')
                              ->where([['u_investors.is_active', 'Yes'], ['u.is_active', 'Yes'], ['investor_id', $investor_id]])->first();

                    $wms    = $this->api_ws(['sn' => 'SalesWaperd', 'val' => [$sales_code->user_code]])->original;
                  
                    if(!empty($wms['data']->agentCode) && !empty($wms['data']->agentWaperdExpDate) && $wms['data']->agentWaperdExpDate >  $this->app_date() && !empty($wms['data']->agentWaperdNo))
                    {
                        $sales_wap  = $wms['data']->agentCode;
                    }else
                    {
                        $sales_wap  = $wms['data']->dummyAgentCode;
                    }

                    if ((!empty($investor->cif)) && (!empty($product->product_code)) && (!empty($request->account_no)))
                    {
                        $cif                = $investor->cif;
                        $custAccountNo      = $request->account_no;
                        $ProductCode        = $product->product_code;
                        $amount_oms_wms     = ($request->investment_amount * 100);
                        $financialPlanning  = '---';
                        $refNo              = $trans->trans_installment_id;
                        $tenor              = !empty($request->tenor_month) ? $request->tenor_month * 12 : 0;
                        $api                = $this->api_ws(['sn' => 'TransactionInstallmentSave', 'val' => [$cif,$custAccountNo,$ProductCode,$amount_oms_wms,$tenor,$request->debt_date,$financialPlanning,$refNo,$sales_wap]])->original; 
                        
                        if (!empty($api['message']->IsSuccess) && ($api['message']->IsSuccess == true))
                        {
                            $status_save = true;
                            TransactionInstallment::where('trans_installment_id', $trans->trans_installment_id)->update(['registered_id' => $api['message']->Result->RegisterID]);
                            return $this->app_partials(1, 0, ['data' => $api]);    
                        }
                        else
                        {
                            if (!empty($api['message']->Message))
                                $status_error_message = 'error: '.$api['message']->Message;
                            
                            TransactionInstallment::where('trans_installment_id', $trans->trans_installment_id)->update(['is_active' => 'No', 'wms_message' => $status_error_message]);
                        }
                    }
                    else
                    {
                        $status_error_message = 'validatin CPM Service : cif, product code or accout no not found';
                        TransactionInstallment::where('trans_installment_id', $trans->trans_installment_id)->update(['is_active'=>'No','wms_message' => $status_error_message]);                    
                    }
                }
            }

            if (!$status_save)
            {            
                return $this->app_partials(0, 1, ['data' => $api]);    
            }
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }  

    public function edit(Request $request)
    {
      try
        {
            //return $this->app_response('xx', $request->input());
          if ($this->auth_user()->usercategory_name == 'Investor')
          {
            $investor_id = $this->auth_user()->investor_id;
          }
          else
          {
            $investor_id = $request->investor_id;
          }
          $manager    = $this->db_manager($request);
          $data = [];

          $trans = TransactionInstallment::where([['investor_id',$investor_id], ['is_active', 'Yes']]);
          if(empty($request->registered_id))
          {
            $trans->where(function($qry) use($request) {
              $qry->whereNull('registered_id')->orWhere('registered_id', '');
            });
          }else{
            $trans->where('registered_id', $request->registered_id);
          }
          $trans = $trans->first();
          
          $tenor_month = !empty($trans->tenor_month) ? $trans->tenor_month : 0;
          $data['investor_id']          = $investor_id;
          $data['product_id']           = $request->product_id;
          $data['investor_account_id']  = $request->investor_account_id;
          $data['account_no']           = $request->account_no;
          // $data['debt_date']            = $request->time_add;
          $data['debt_date']            = $request->debt_date;
          // $data['tenor_month']          = $tenor_month + ($request->debt_date * 12);
          $data['tenor_month']          = $tenor_month + ($request->time_add * 12);
          $data['investment_amount']    = $request->amount_add;
          $data['status']               = 'ACTIVE';
          $data['created_by']           = $manager->user;
          $data['created_host']         =  $manager->ip ;
          
          $status_save = false;
          $status_error_message = 'error: no message error';
          //return $this->app_response('xxx',$trans);
          // $trans = TransactionInstallment::where([['investor_id',$investor_id], ['registered_id', $request->registered_id], ['is_active', 'Yes']])->first();
          if(!empty($trans->trans_installment_id)) 
          {
            $investor   = Investor::where([['investor_id', $investor_id], ['is_active', 'Yes'], ['valid_account', 'Yes']])->first();
            $product  = Product::where([['m_products.is_active', 'Yes'], ['product_id', $request->product_id]])->first();
            $account_number = Account::where([['investor_account_id', $request->investor_account_id], ['is_active', 'Yes']])->first();

            if( (!empty($investor->cif)) && (!empty($product->product_code))) {
              $cif                = $investor->cif;
              $custAccountNo      = !empty($account_number) ? $account_number->account_no : null;;
              $ProductCode        = $product->product_code;
              $amount_oms_wms     = ($request->amount_add * 100);
              $financialPlanning  = '---';
              $refNo              = $trans->trans_installment_id;
              $newTenor       = $data['tenor_month'];
              $user         = $data['created_by'];
              $host         = $data['created_host'];

              $api = $this->api_ws(['sn' => 'TransactionInstallmentEdit', 'val' => [$request->registered_id, $custAccountNo, $amount_oms_wms, $newTenor, $request->debt_date, $refNo, $user, $host]])->original;
              // $api = $this->api_ws(['sn' => 'TransactionInstallmentEdit', 'val' => [$request->registered_id, $custAccountNo, $amount_oms_wms, $request->debt_date, $request->time_add, $refNo]])->original;
              // $api = $this->api_ws(['sn' => 'TransactionInstallmentEdit', 'val' => ["4450", "73000147 12 CRISPR", "100000000", "24", "5", "17147"]])->original;  
              // return $this->app_response('installment checkout save test', $api);
              if(!empty($api['message']->IsSuccess) && ($api['message']->IsSuccess == true)) 
              {
                 // return $this->app_response('installment checkout save test', $api);
                $status_save = true;
                $update =TransactionInstallment::where([['investor_id',$investor_id], ['registered_id', $request->registered_id], ['is_active', 'Yes']])->update($data);
                return $this->app_response('installment edit success save',['result' => $api['message']] );                    
              } else {             
                if(!empty($api['message']->Message)) {
                   $status_error_message  = 'error: '.$api['message']->Message;
                }
                TransactionInstallment::where('trans_installment_id', $trans->trans_installment_id)->update(['is_active'=>'Yes','wms_message' => $status_error_message]);
                // TransactionInstallment::where([['investor_id',$investor_id], ['registered_id', $request->registered_id], ['is_active', 'Yes']])->update([['wms_message', $status_error_message], ['is_active', 'Yes']]); 

                return $this->app_response('installment edit gagal save',['result' => $api['message']] ); 
              } 
            }
          }

          if(!$status_save) 
          {         
            return $this->app_response('installment checkout save', ['result' => $status_error_message] );
          }
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    } 

    // public function status(Request $request)
    // {
    //     try
    //     {
    //         $type = !empty($request->type) ? $request->type : 'Status';
    //         $data = TransactionInstallment::select('trans_installment_id', 'status')
    //                 ->where([['status', $type], ['is_active', 'Yes']])
    //                 ->orderBy('status')
    //                 ->get();
    //         return $this->app_response('Status', $data);
    //     }
    //     catch (\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     }   
    // }
}
