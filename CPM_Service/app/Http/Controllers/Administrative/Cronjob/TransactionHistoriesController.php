<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\AssetFreeze;
use App\Models\SA\Reference\KYC\Holiday;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Transaction\Reference;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Transaction\TransactionInstallment;
use App\Models\Users\Investor\Investor;
use Illuminate\Http\Request;

class TransactionHistoriesController extends AppController
{
    public $table = 'Transaction\TransactionHistory';
    
    /**
     * @return void
     */
    public function getData(Request $request)
    {
        try
        {
            ini_set('max_execution_time', 14400);  

            $trans      = [];
            $success    = $fails = 0;
            $investor   = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])->get();

            foreach ($investor as $inv)
            {

                $api = $this->api_ws(['sn' => 'TransactionHistories', 'val' => [$inv->cif, date('Y-m-d', strtotime('-7 days')), date('Y-m-d')]])->original['data'];
	           
                if (!empty($api))
                {
                    foreach ($api as $a)
                    {
                        $save = $this->save($request, $inv, $a);

                        // if (!$save->success)
                        if (!isset($save->success) || !$save->success)
                        {
                            $message = '';
                            if(!empty($save->message))
                            {
                                $message = $save->message;
                            }elseif(!empty($save->error_msg)){
                                $message = $save->error_msg;
                            }
                            $trans[] = ['investor_id' => $inv->investor_id, 'message' => $message];
                            $fails++;
                        }                    
                        else
                        {
                            $trans[] = $save;
                            $success++;
                        }
                    }
                }
                
                TransactionHistoryDay::where([['investor_id', $inv->investor_id], ['history_date', $this->app_date()]])->update(['is_active' => 'No']);
                $this->save_history_data($inv->investor_id);
                $this->save_history_current($inv->investor_id);
            }
            return $this->app_partials($success, $fails, $trans);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }
    
    private function save($request, $inv, $dt)
    {
        try
        {
            $data       = $data_freez = [];
            $product    = Product::where([['ext_code', $dt->productCode], ['is_active', 'Yes']])->first();        
            if (!empty($inv->investor_id) && !empty($product->product_id))
            {
                if($dt->transactionTypeCode == 'SWTOT' || $dt->transactionTypeCode == 'SWTIN')
                {
                    $ts_switching = Reference::where([['reference_type', 'Transaction Type'], ['is_active', 'Yes'],['reference_code', $dt->transactionTypeCode]])->first();
                    $save       = !empty($dt->referenceNo) ? TransactionHistory::where([['reference_no', $dt->referenceNo], ['type_reference_id', $ts_switching->trans_reference_id], ['is_active', 'Yes'] ])->first() : [];
                }else{
                    $save       = !empty($dt->referenceNo) ? TransactionHistory::where([['reference_no', $dt->referenceNo],['is_active', 'Yes']])->first() : [];
                }
                
                $dtl_hist   = $save; 
                $ts_id      = Reference::whereJsonContains('reference_ext', ucwords(strtolower($dt->status)))->where([['reference_type', 'Transaction Status'], ['is_active', 'Yes']])->first();
                $code_ref = Reference::where([['reference_code', $dt->transactionTypeCode],['is_active', 'Yes']])->first();
                
                if(!empty($code_ref->reference_code))
                {
                    $act        = empty($save->trans_history_id) ? 'cre' : 'upd';
                    $data       = ['investor_id'        => $inv->investor_id,
                                   'product_id'         => $product->product_id,
                                   'trans_reference_id' => !empty($ts_id->trans_reference_id) ? $ts_id->trans_reference_id : null,
                                   'type_reference_id'  => $this->db_row('trans_reference_id', ['where' => [['reference_code', $dt->transactionTypeCode], ['reference_type', 'Transaction Type']]], 'SA\Transaction\Reference')->original['data'],
                                   'account_no'         => !empty($dt->accountNo) ? $dt->accountNo : null,
                                   'transaction_date'   => !empty($dt->transactionDate) ? $dt->transactionDate : null,
                                   'price_date'         => !empty($dt->priceDate) ? $dt->priceDate : null,
                                   'settle_date'        => !empty($dt->settleDate) ? $dt->settleDate : null,
                                   'booking_date'       => !empty($dt->bookingDate) ? $dt->bookingDate : null,
                                   'maturity_date'      => !empty($dt->maturityDate) ? $dt->maturityDate : null,
                                   'amount'             => !empty($dt->amount) ? $dt->amount : null,
                                   'price'              => !empty($dt->price) ? $dt->price : null,
                                   'net_amount'         => !empty($dt->netAmount) ? $dt->netAmount : null,
                                   'unit'               => !empty($dt->units) ? $dt->units : null,
                                   'percentage'         => !empty($dt->percentage) ? $dt->percentage : null,
                                   'fee_amount'         => !empty($dt->feeAmount) ? $dt->feeAmount : null,
                                   'fee_unit'           => !empty($dt->feeUnit) ? $dt->feeUnit : null,
                                   'tax_amount'         => !empty($dt->feeTax) ? $dt->feeTax : null,
                                   'charge'             => !empty($dt->charges) ? $dt->charges : null,
                                   'approve_amount'     => !empty($dt->approvedAmount) ? $dt->approvedAmount : null,
                                   'approve_unit'       => !empty($dt->approvedUnits) ? $dt->approvedUnits : null,
                                   'payment_method'     => !empty($dt->paymentMethod) ? $dt->paymentMethod : null,
                                   //'remark'          => !empty($dt->remark) ? $dt->remark : null,
                                   'reference_no'       => !empty($dt->referenceNo) ? $dt->referenceNo : null,
                                   'wms_remark'         => !empty($dt->remark) ? $dt->remark : null,
                                   'wms_status'         => !empty($dt->status) ? $dt->status : null,
                                   'is_active'          => 'Yes',
                                   'send_wms'           => !empty($ts_id->trans_reference_id) ? 'true' : 'false',
                                   $act.'ated_by'       => 'System',
                                   $act.'ated_host'     => '::1'
                                  ];
                    // if (empty($dt->referenceNo) && $dt->generatorId == 7 && $dt->entryUser == 'AUTOGENERATE')
                    if ($dt->generatorId == 7 && $dt->entryUser == 'AUTOGENERATE')
                    {
                        $genDt       = explode(',', $dt->generatorData1);
                        $regId       = !empty($genDt[0]) ? $genDt[0] : 'xxx';
                        $installment = TransactionInstallment::where([['registered_id', $regId], ['is_active', 'Yes']])->first();
                        if (!empty($installment->portfolio_id))
                            $data['portfolio_id'] = $installment->portfolio_id;
                    }
            
                    $request->request->replace($data);
                
                    if ($validate = $this->app_validate($request, TransactionHistory::rules(), true))
                        return $validate;
                
                    if (empty($save->trans_history_id))
                        $save = TransactionHistory::where([['investor_id', $inv->investor_id], ['product_id', $product->product_id], ['account_no', $dt->accountNo], ['transaction_date', $dt->transactionDate], ['is_active', 'Yes']])->first();
                    
                    $save               = empty($save->trans_history_id) ? TransactionHistory::create(array_merge($data)) : TransactionHistory::where('trans_history_id', $save->trans_history_id)->update(array_merge($data));

                    if(!empty($dtl_hist)) {    
                        if($ts_id->reference_code == 'Done' ||  $ts_id->reference_code == 'Paid' ||  $ts_id->reference_code == 'Canceled')
                        {
                            $freeze             = AssetFreeze::where([['investor_id', $inv->investor_id], ['product_id', $product->product_id], ['portfolio_id', $dtl_hist->portfolio_id],['account_no', $dt->accountNo]])->first();
                            $act_freez          = empty($freeze->asset_freeze_id) ? 'cre' : 'upd';
                            $redeem_freeze_unit = 0;
                            //$dt->units        = 90;
                            
                            if (!empty($dt->units))
                            {
                                $redeem_freeze_unit = $freeze->freeze_unit - $dt->units;
                                if ($redeem_freeze_unit < 0)
                                {
                                    $redeem_freeze_unit = 0;
                                }
                            }

                            $data_freez = [
                                'investor_id'           => $inv->investor_id,
                                'product_id'            => $product->product_id,
                                'portfolio_id'          => $dtl_hist->portfolio_id,
                                'account_no'            => !empty($dt->accountNo) ? $dt->accountNo : null,
                                $act_freez.'ated_by'    => 'System',
                                $act_freez.'ated_host'  => '::1'
                            ];

                            if(!empty($dt->units))
                            {
                               $data_freez=  array_merge($data_freez, ['freeze_unit' => $redeem_freeze_unit]);
                            }
                            $freeze = (empty($freeze->asset_freeze_id)) ? AssetFreeze::create($data_freez) : AssetFreeze::where('asset_freeze_id', $freeze->asset_freeze_id)->update($data_freez);                    
                        }
                    }        

                    if (!empty($save->portfolio_id))
                    {
                        $ct_save    = TransactionHistory::select()
                                    ->join('m_trans_reference as b', 't_trans_histories.status_reference_id', '=', 'b.trans_reference_id')
                                    ->where([['t_trans_histories.investor_id', $inv->investor_id], ['t_trans_histories.is_active', 'Yes'], ['t_trans_histories.portfolio_id', $save->portfolio_id], ['b.is_active', 'Yes']])
                                    ->whereNotIn('b.reference_name', 'Canceled')
                                    ->get();
                        if (count($ct_save) <= 0)
                        {
                            $status     = Reference::where([['is_active', 'Yes'], ['reference_code', 'OD']])->first();
                            $upd_inv    = Investment::where([['portfolio_id', $ct_save->portfolio_id], ['is_active', 'Yes'], ['investor_id', $ct_save->investor_id]])->update(['status_id' => $status->trans_reference_id]);
                        }
                    } 
                }
                
            }
            return (object)['success' => true, 'data' => $data]; 
        }
        catch(\Exception $e)
        {
            return (object)['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function save_history_data($inv_id)
    {
        $trans  = $sub_amt = $sub_unit = [];
        $hist   = TransactionHistory::select('t_trans_histories.*', 'c.reference_code', 'f.diversification_account')
                ->join('m_trans_reference as b', 't_trans_histories.trans_reference_id', '=', 'b.trans_reference_id')
                ->leftJoin('m_trans_reference as c', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Transaction Type'], ['c.is_active', 'Yes']]); })
                ->join('m_products as d', 't_trans_histories.product_id', '=', 'd.product_id')
                ->leftJoin('m_asset_class as e', function($qry) { $qry->on('d.asset_class_id', '=', 'e.asset_class_id')->where('e.is_active', 'Yes'); })
                ->leftJoin('m_asset_categories as f', function($qry) { $qry->on('e.asset_category_id', '=', 'f.asset_category_id')->where('f.is_active', 'Yes'); })
                ->where([['investor_id', $inv_id], ['t_trans_histories.is_active', 'Yes'], ['b.reference_type', 'Transaction Status'], ['b.reference_code', 'Done'], ['b.is_active', 'Yes'], ['d.is_active', 'Yes']])
                ->whereRaw("LEFT(portfolio_id, 1) IN ('2', '3')")
                ->get();

        foreach ($hist as $h)
        {
            $id     = md5($inv_id . $h->product_id . $h->portfolio_id . $h->account_no);
            $unit   = !empty($h->approve_unit) ? in_array($h->reference_code, ['SUB', 'TOPUP', 'SWTIN', 'ADJUP', 'CDIV']) ? $h->approve_unit : $h->approve_unit * -1 : 0;
            
            if (in_array($h->reference_code, ['SUB', 'TOPUP', 'SWTIN', 'ADJUP', 'CDIV']))
            {
                $sub_amt[$id]   = !empty($h->net_amount) ? !empty($sub_amt[$id]) ? $sub_amt[$id] + $h->net_amount : floatval($h->net_amount) : 0;
                $sub_unit[$id]  = !empty($h->approve_unit) ? !empty($sub_unit[$id]) ? $sub_unit[$id] + $h->approve_unit : floatval($h->approve_unit) : 0;
            }
            
            if (in_array($id, array_keys($trans)))
            {
                $trans[$id]['unit']             += $unit;
                $trans[$id]['current_balance']  += $unit * $trans[$id]['price'];
            }
            else
            {
                $price      = Price::where([['product_id', $h->product_id], ['price_date', '<=', $this->app_date()], ['is_active', 'Yes']])->orderBy('price_date', 'DESC')->limit(1)->first();
                $curr_blc   = !empty($price->price_value) ? $unit * $price->price_value : 0;
                $trans[$id] = [
                    'product_id'                => $h->product_id,
                    'portfolio_id'              => $h->portfolio_id,
                    'account_no'                => $h->account_no,
                    'unit'                      => floatval($unit),
                    'price'                     => !empty($price->price_value) ? floatval($price->price_value) : 0,
                    'current_balance'           => $curr_blc,
                    'diversification_account'   => $h->diversification_account,
                    'is_active'                 => $h->is_active
                ];
            }
        }

        if (!empty($trans))
        {
            foreach ($trans as $t_key => $t_val)
            {
                $avg_nav    = isset($sub_amt[$t_key]) && isset($sub_unit[$t_key]) && $sub_unit[$t_key] != 0 ? $sub_amt[$t_key] / $sub_unit[$t_key] : 0;
                $amt        = $avg_nav * $t_val['unit'];
                $earnings   = $t_val['current_balance'] - $amt;

                $data = [
                    'investor_id'               => $inv_id,
                    'product_id'                => $t_val['product_id'],
                    'portfolio_id'              => $t_val['portfolio_id'],
                    'account_no'                => $t_val['account_no'],
                    'history_date'              => date('Y-m-d'),
                    'unit'                      => $t_val['unit'],
                    'avg_nav'                   => $avg_nav,
                    'current_balance'           => $t_val['current_balance'],
                    'investment_amount'         => $amt,
                    'earnings'                  => $earnings,
                    'returns'                   => $amt != 0 ? $earnings / $amt * 100 : 0,
                    'total_sub_amount'          => isset($sub_amt[$t_key]) ? $sub_amt[$t_key] : null,
                    'total_sub_unit'            => isset($sub_unit[$t_key]) ? $sub_unit[$t_key] : null,
                    'diversification_account'   => $t_val['diversification_account'],
                    'is_active'                 => 'Yes'
                ];

                $save = TransactionHistoryDay::where([['investor_id', $inv_id], ['product_id', $t_val['product_id']], ['portfolio_id', $t_val['portfolio_id']], ['account_no', $t_val['account_no']], ['history_date', date('Y-m-d')]])->first();
                
                if (empty($save->trans_history_day_id))
                    TransactionHistoryDay::create($data);
                else
                    TransactionHistoryDay::where('trans_history_day_id', $save->trans_history_day_id)->update($data);
            }
        }
    }
    
    private function save_history_current($inv_id)
    {
        $prd    = [];
        $asset  = AssetOutstanding::selectRaw('account_no, b.product_id, d.diversification_account, regular_payment, SUM(outstanding_unit) as unit, SUM(total_subscription) as total_sub, SUM(total_unit) as total_unit, SUM(balance_amount) as balance')
                ->join('m_products as b', 't_assets_outstanding.product_id', '=', 'b.product_id')
                ->leftJoin('m_asset_class as c', function($qry) { $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                ->leftJoin('m_asset_categories as d', function($qry) { $qry->on('c.asset_category_id', '=', 'd.asset_category_id')->where('d.is_active', 'Yes'); })
                ->where([['investor_id', $inv_id], ['t_assets_outstanding.is_active', 'Yes'], ['outstanding_date', $this->app_date()], ['b.is_active', 'Yes']])
                ->groupBy(['account_no', 'b.product_id', 'd.diversification_account', 'regular_payment'])
                ->get();
        foreach ($asset as $a)
        {
            $save   = true;
            $avg    = $amt = $current = $earnings = $returns = $unit = null;
            if ($a->diversification_account)
            {
                if (!empty($a->unit))
                {
                    if (!in_array($a->product_id, $prd))
                    {
                        $price = Price::where([['product_id', $a->product_id], ['price_date', '<=', $this->app_date()], ['is_active', 'Yes']])->orderBy('price_date', 'DESC')->limit(1)->first();
                        $prd[$a->product_id] = !empty($price->price_value) ? $price->price_value : 0;
                    }
		    
		            $rst        = $this->cut_of_time($a->product_id,'array');
                    $transaction_date =  !empty($rst['transaction_date_allocation']) ? $rst['transaction_date_allocation'] : $this->app_date() ;
                    $trans      = TransactionHistoryDay::selectRaw('SUM(unit) as unit, SUM(total_sub_amount) as total_amount, SUM(total_sub_unit) as total_unit')
                                ->where([['investor_id', $inv_id], ['product_id', $a->product_id], ['account_no', $a->account_no], ['history_date', $transaction_date], ['is_active', 'Yes']])
                                ->whereRaw("LEFT(portfolio_id, 1) IN ('2', '3')")
                                ->first();
                    $unit       = !empty($trans->unit) ? $a->unit - $trans->unit : $a->unit;
                    if ($unit > 0)
                    {
                        $current    = $unit * $prd[$a->product_id];
                        $total_sub  = !empty($trans->total_amount) ? $a->total_sub - $trans->total_amount : $a->total_sub;
                        $total_unit = !empty($trans->unit) ? $a->total_unit - $trans->total_unit : $a->total_unit;
                        $avg        = $total_unit != 0 ? $total_sub / $total_unit : 0;
                        $amt        = $avg * $unit;
                        $earnings   = $current - $amt;
                        $returns    = $amt != 0 ? $earnings / $amt * 100 : 0;
                    }
                    else
                    {
                        $save = false;
                    }
                }
            }
            else
            {
                $current = $a->balance;
            }
            
            if ($save)
            {
                $data = [
                    'investor_id'               => $inv_id,
                    'product_id'                => $a->product_id,
                    'account_no'                => $a->account_no,
                    'history_date'              => $this->app_date(),
                    'unit'                      => $unit,
                    'avg_nav'                   => $avg,
                    'current_balance'           => $current,
                    'investment_amount'         => $amt,
                    'earnings'                  => $earnings,
                    'returns'                   => $returns,
                    'total_sub_amount'          => $a->total_sub,
                    'total_sub_unit'            => $a->total_unit,
                    'diversification_account'   => $a->diversification_account,
                    'regular_payment'           => !empty($a->regular_payment) ? $a->regular_payment : null,
                    'is_active'                 => 'Yes'
                ];

                $qry    = TransactionHistoryDay::where([['investor_id', $inv_id], ['product_id', $a->product_id], ['account_no', $a->account_no], ['history_date', $this->app_date()]])
                        ->whereNull('portfolio_id')
                        ->first();

                if (empty($qry->trans_history_day_id))
                    TransactionHistoryDay::create($data);
                else
                    TransactionHistoryDay::where('trans_history_day_id', $qry->trans_history_day_id)->update($data);
            }
        }
    }

    public function save_wms(Request $request)
    {
        try
        {
            $res = [];
            $data = TransactionHistory::select('t_trans_histories.*', 'd.product_code', 'c.reference_code', 'b.ifua', 'e.account_no as investor_bank_account')
                    ->join('m_products as d', 't_trans_histories.product_id', '=', 'd.product_id')
                    ->join('u_investors as b', 't_trans_histories.investor_id', '=', 'b.investor_id')
                    ->leftJoin('m_trans_reference as c', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Transaction Type'], ['c.is_active', 'Yes']]); })
                    ->join('u_investors_accounts as e', 't_trans_histories.investor_account_id', '=', 'e.investor_account_id')
                    ->where([['t_trans_histories.send_wms', false], ['t_trans_histories.is_active', 'Yes'],  ['b.is_active', 'Yes'],  ['d.is_active', 'Yes'], ['e.is_active', 'Yes']])->get();

            foreach ($data as $dt)
            {
                $val = [];
                $path = '';
                $amt = $dt->net_amount;    
                $cat =  $dt->reference_code == 'SUB' ? empty($dt->account_no) ? 'NEW' : 'SUB' : 'RED';
                
                switch ($cat) {
                    case 'NEW':
                        $path = 'subscription/first';
                        break;
                    case 'SUB':
                        $path = 'subscription';
                        $val = [$dt->account_no];
                        break;
                    case 'RED':
                        $path = 'redemption';
                        $val = [$dt->account_no, $dt->unit, $dt->fee_unit, true, 'amount' ];
                        $amt = 0;
                        break;
                    default:
                        $path = '';
                        break;
                }

                    $val = array_merge([$dt->product_code,
                        $dt->ifua,
                        $dt->reference_no,
                        $cat,
                        $dt->transaction_date,
                        $dt->transaction_date,
                        $dt->transaction_date,
                        $dt->investor_bank_account,
                        $dt->created_by,
                        $dt->created_host,
                        $amt,
                        $amt
                    ],$val);

                $api = $this->api_ws(['sn' => 'TransactionWms', 'val' => $val, 'path' => $path])->original;
                if(!empty($api['code']) && $api['code'] == 200)
                {
                    TransactionHistory::where('trans_history_id', $dt->trans_history_id)->update(['send_wms' => true]);
                    $res[] = $api['data'];
                }else{
                    $res[] = $api;
                }
            }
            return $this->app_response('Transaction WMS', $res);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }  
    }

    /*
    public function update_status_transaction($gid, $type)  {
        try
        {       
            $res  = 'nothing update'; 
            if(!empty($gid)) {
               $type_valid_cancel       = ['OrderFailed','OrderMfSwitchingRejected','OrderMfBuyRejected','PaymentFailed'];
               $type_valid_in_progress  = ['OrderMfSellEntried','OrderMfBuyEntried']; 
               $type_valid_done         = ['PaymentSettled','OrderMfSellApproved','RequestOrderApproved'];

               if(in_array($type,$type_valid_cancel)) {
                    $status_transaction = $this->db_row(['trans_reference_id','reference_name'], ['where' => [['reference_code', 'Canceled'], ['reference_type', 'Transaction Status'],['is_active', 'Yes']]], 'SA\Transaction\Reference')->original['data'];

               }

               if(in_array($type,$type_valid_in_progress)) {
                    $status_transaction = $this->db_row(['trans_reference_id','reference_name'], ['where' => [['reference_code', 'In Proses'], ['reference_type', 'Transaction Status'],['is_active', 'Yes']]], 'SA\Transaction\Reference')->original['data'];

               }

               if(in_array($type,$type_valid_done)) {
                    $status_transaction = $this->db_row(['trans_reference_id','reference_name'], ['where' => [['reference_code', 'Done'], ['reference_type', 'Transaction Status'],['is_active', 'Yes']]], 'SA\Transaction\Reference')->original['data'];

               }
                    
                if(!empty($status_transaction)) {    
                    $respon = TransactionHistory::where([['id_wms', $gid], ['is_active','Yes']])->update(['trans_reference_id' => $status_transaction->trans_reference_id]);

                    $res = array();
                    if($respon) {                      
                        $res['id_wms']        = $gid;    
                        $res['type']          = $type;    
                        $res['status_transaction'] = $status_transaction->reference_name;                        
                        $res['status_update'] = 'success';                        
                    } else  {
                        $res['id_wms']        = $gid;    
                        $res['type']          = $type;    
                        $res['status_transaction'] = $status_transaction->reference_name;                       
                        $res['status_update'] = 'failed';    
                    }
                }    
            }     

            return $this->app_response('Update Status Trasaction',$res);
        }   
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }          
    }  
    */

    public function update_status_transaction($gid, $type, $provider_status, $provider_remark, $provider_reference)  { 
        try
        {       
            $res  = 'nothing update'; 
            if(!empty($gid)) {
                /* 
                $type_valid_cancel                   = ['OrderFailed','OrderMfSwitchingRejected','OrderMfBuyRejected','PaymentFailed'];
                $type_valid_in_progress / onprocess  = ['OrderMfSellEntried','OrderMfBuyEntried','orderMFSellApproved','orderMFBuyApproved','orderMfSwitchingApproved'];
                $type_valid_done                     = ['Approved'];
                */

                $type_valid      = Reference::select('trans_reference_id','reference_code')
                                   ->where([['is_active', 'Yes'], ['reference_type', 'Transaction Status']])
                                   ->whereJsonContains('reference_ext',[$type])
                                   ->first();
                    
                if(!empty($type_valid)) {    
                    $data = [
                        'trans_reference_id'    => $type_valid->trans_reference_id,
                        'provider_status'       => !empty($provider_status) ? urldecode($provider_status) : null,
                        'provider_remark'       => !empty($provider_remark) ? urldecode($provider_remark) : null,
                        'provider_reference'    => !empty($provider_reference) ? urldecode($provider_reference) : null 
                    ];

                    $respon = TransactionHistory::where([['guid', $gid], ['is_active','Yes']])->update($data);

                    $res = array();
                    if($respon) {                      
                        $res['guid']                 = $gid;    
                        $res['type']                 = $type;    
                        $res['status_transaction']   = $type_valid->reference_code;                        
                        $res['status_update']        = 'success'; 
                        $res['investment_to_ondraf'] = $this->check_investment_to_ondraf($gid);
                        $res['provider_status']      = !empty($provider_status) ? $provider_status : null;
                        $res['provider_remark']      = !empty($provider_remark) ? $provider_remark : null;   
                        $res['provider_reference']   = !empty($provider_reference) ? $provider_reference : null;                                                
                    } else  {
                        $res['guid']                 = $gid;    
                        $res['type']                 = $type;    
                        $res['status_transaction']   = $type_valid->reference_code;                       
                        $res['status_update']        = 'failed'; 
                        $res['investment_to_ondraf'] = '';                          
                        $res['provider_status']      = !empty($provider_status) ? $provider_status : null;
                        $res['provider_remark']      = !empty($provider_remark) ? $provider_remark : null;   
                        $res['provider_reference']   = !empty($provider_reference) ? $provider_reference : null;                                                
                    }
                }    
            }     

            return $this->app_response('Update Status Transaction',$res);
        }   
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }          
    }  

    private function check_investment_to_ondraf($gid) {
        $check_cancel = 0;
        $upd_inv = '';
        $respon = TransactionHistory::where([['guid', $gid], ['is_active','Yes']])->first();
        
        if(!empty($respon->portfolio_id))
        {
            $check_cancel = TransactionHistory::select()
                        ->join('m_trans_reference as b', 't_trans_histories.trans_reference_id', '=', 'b.trans_reference_id')
                        ->where([['t_trans_histories.investor_id', $respon->investor_id], ['t_trans_histories.is_active', 'Yes'], ['t_trans_histories.portfolio_id', $respon->portfolio_id], ['b.is_active', 'Yes']])
                        ->whereNotIn('b.reference_name',['Canceled'])
                        ->count();

            if($check_cancel <= 0)
            {
                $status = Reference::where([['is_active', 'Yes'], ['reference_code', 'OD']])->first();
                if($upd_inv = Investment::where([['portfolio_id', $respon->portfolio_id], ['is_active', 'Yes'], ['investor_id', $respon->investor_id]])->update(['status_id' => $status->trans_reference_id])) {
                    $upd_inv = $respon->portfolio_id;
                }
            }
        }

        return $upd_inv;
    }
    
    public function cut_of_time($product_id=null,$type_data_return='json')
    {
        try
        {
            $data = [];

            $rst  = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_id]])
                      ->leftJoin('m_cut_off_time as b', function($qry) { return $qry->on('m_products.currency_id', '=', 'b.currency_id')->where('b.is_active', 'Yes'); })
                      ->leftJoin('m_currency as c', function($qry) { return $qry->on('c.currency_id', '=', 'b.currency_id')->where('c.is_active', 'Yes'); })
                      ->first();

            if(!empty($rst)) {          
                $holiday  = Holiday::where([['m_holiday.is_active', 'Yes'],['m_holiday.currency_id', $rst->currency_id],['effective_date',$this->app_date()]])
                          ->first();

                if(!empty($holiday)) {
                  $is_holiday = 'Yes';  
                } else {
                  $is_holiday = 'No';    
                }  

                $day = strtoupper(date('N'));
                if(in_array($day,array(6,7))) {
                   $is_working_day = 'No';
                } else {
                  $is_working_day = 'Yes';                    
                }

                if(date("Hi") > str_replace(':','',$rst->cut_off_time_value) ) {
                  $is_working_time = 'No'; 
                } else {
                  $is_working_time = 'Yes';                       
                }  

                $data['product_id']  =  $rst->product_id;
                $data['product_name'] =  $rst->product_name;            
                $data['currency'] =  $rst->currency_name;
                $data['cut_of_time'] =  $rst->cut_off_time_value;
                $data['current_date'] =  $this->app_date();
                $data['current_time'] = date("H:i");
                $data['current_day'] =  strtoupper(date('l'));
                $data['is_holiday'] =  $is_holiday;
                //$data['is_holiday'] =  'No';
                $data['is_working_day'] =  $is_working_day;
                //$data['is_working_day'] =  'Yes';
                $data['is_working_time'] =  $is_working_time;
                //$data['is_working_day'] =  'Yes';
                if($is_working_time == 'Yes') {
                    $data['transaction_date_allocation'] =  $this->cut_of_time_get_available_date($this->app_date(),$rst->currency_id); 
                } else {
                    $data['transaction_date_allocation'] =  $this->cut_of_time_get_available_date(date("Y-m-d", strtotime("+ 1 day")),$rst->currency_id);                     
                }
            }    

            if($type_data_return == 'json') {
                return $this->app_partials(1, 0, ['data' => $data]);                
            } else {
                return $data;
            }

        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }  

    private function cut_of_time_get_available_date($dateCheck,$currencyId) 
    {
        $holiday  = Holiday::where([['m_holiday.is_active', 'Yes'],['m_holiday.currency_id', $currencyId],['effective_date',$dateCheck]])
                    ->first();

        $available = true;            
        if(!empty($holiday)) {
          $available= false; 
        }  

        $day = strtoupper(date('N',strtotime($dateCheck)));
        if(in_array($day,array(6,7))) {
          $available= false; 
        }

        if($available) {
          return $dateCheck;            
        } else {
          $dateCheck =  date('Y-m-d', strtotime('+1 day', strtotime($dateCheck))); 
          return $this->cut_of_time_get_available_date($dateCheck,$currencyId);  
        }
    }        
}