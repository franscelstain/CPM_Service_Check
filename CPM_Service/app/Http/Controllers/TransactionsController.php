<?php

namespace App\Http\Controllers;

use App\Models\Administrative\Mobile\MobileContent;
use App\Http\Controllers\AppController;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\SA\Assets\AssetClass;
use App\Models\SA\Assets\Products\Fee;
use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Assets\Products\Product;
use App\Models\Users\Investor\Account;
use App\Models\SA\Reference\KYC\Holiday;
use App\Models\SA\Transaction\Reference;
use App\Models\Transaction\TransactionFeeOutstanding;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Transaction\TransactionOtp;
use App\Models\Users\Investor\Investor;
use App\Models\Users\UserSalesDetail;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\AssetFreeze;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Http\Controllers\Administrative\Broker\MessagesController;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Auth;

class TransactionsController extends AppController
{
    protected $table = 'Transaction\TransactionHistory';
    
    public function index(Request $request)
    {   
        return $this->trans_histories($request, ['SUB', 'TOPUP']);
    }
    
    public function all(Request $request)
    {
        return $this->trans_histories($request, ['SUB', 'TOPUP', 'RED']);
    }

    public function account_balance(Request $request)
    {
       try
        {
            $account_no = $asset = $ast_amt = $ast_unit = $prd_amt = $prd_unit = $product = [];
            $trans_date = $cif = '';
            $total_blc  = $total_unit = 0;
            $app_date_min_one_day = date( "Y-m-d", strtotime(date('Y-m-d')." -1 day"));
            /* yang lengap where nya yang ini */
            $data       = TransactionHistory::select('t_trans_histories.*', 'c.reference_code as type_ref_code', 'd.product_id', 'd.product_name', 'e.asset_class_id', 'e.asset_class_name',  'e.asset_class_color', 'f.issuer_logo', 'i.asset_category_name',  'g.symbol', 'h.cif', 'h.investor_id','d.min_buy','d.min_sell','d.max_buy','d.max_sell','d.issuer_id','d.issuer_id','d.allow_switching','d.min_switch_out','d.max_switch_out','d.min_switch_in','d.max_switch_in')
                        ->join('m_trans_reference as b', 't_trans_histories.trans_reference_id', '=', 'b.trans_reference_id')
                        ->leftJoin('m_trans_reference as c', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Transaction Type'], ['c.is_active', 'Yes']]); })
                        ->join('m_products as d', 't_trans_histories.product_id', '=', 'd.product_id')
                        ->leftJoin('m_asset_class as e', function($qry) { return $qry->on('d.asset_class_id', '=', 'e.asset_class_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as f', function($qry) { return $qry->on('d.issuer_id', '=', 'f.issuer_id')->where('f.is_active', 'Yes'); })
                        ->leftJoin('m_currency as g', function($qry) { return $qry->on('d.currency_id', '=', 'g.currency_id')->where('g.is_active', 'Yes'); })
                        ->leftJoin('u_investors as h', function($qry) { return $qry->on('h.investor_id', '=', 't_trans_histories.investor_id')->where('h.is_active', 'Yes'); })
                        ->leftJoin('m_asset_categories as i', function($qry) { $qry->on('e.asset_category_id', '=', 'i.asset_category_id')->where('i.is_active', 'Yes'); })
                        ->where([['t_trans_histories.investor_id', $request->investor_id], ['portfolio_id', $request->portfolio_id], ['t_trans_histories.is_active', 'Yes'],
                                 ['b.reference_type', 'Transaction Status'], ['b.reference_code', 'Done'], ['d.is_active', 'Yes']])
                        ->whereNotNull('account_no')
                        ->get();

            //yang ini untuk testing - tidak menyertakan ['b.reference_type', 'Transaction Status'], ['b.reference_code', 'Done'] dan ->whereNotNull('account_no')
            /*
            $data       = TransactionHistory::select('t_trans_histories.*', 'c.reference_code as type_ref_code', 'd.product_id', 'd.product_name', 'e.asset_class_id', 'e.asset_class_name', 'e.asset_class_color', 'f.issuer_logo', 'g.symbol', 'h.cif', 'h.investor_id','d.min_buy','d.min_sell','d.max_buy','d.max_sell')
                        ->join('m_trans_reference as b', 't_trans_histories.trans_reference_id', '=', 'b.trans_reference_id')
                        ->leftJoin('m_trans_reference as c', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Transaction Type'], ['c.is_active', 'Yes']]); })
                        ->join('m_products as d', 't_trans_histories.product_id', '=', 'd.product_id')
                        ->leftJoin('m_asset_class as e', function($qry) { return $qry->on('d.asset_class_id', '=', 'e.asset_class_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as f', function($qry) { return $qry->on('d.issuer_id', '=', 'f.issuer_id')->where('f.is_active', 'Yes'); })
                        ->leftJoin('m_currency as g', function($qry) { return $qry->on('d.currency_id', '=', 'g.currency_id')->where('g.is_active', 'Yes'); })
                        ->leftJoin('u_investors as h', function($qry) { return $qry->on('h.investor_id', '=', 't_trans_histories.investor_id')->where('h.is_active', 'Yes'); })
                        ->where([['t_trans_histories.investor_id', $request->investor_id], ['portfolio_id', $request->portfolio_id], ['t_trans_histories.is_active', 'Yes'],                                  
                                  ['d.is_active', 'Yes']])
                        ->whereNotNull('account_no')
                        ->get();
            */
            foreach ($data as $dt)
            {
                if(floatval($dt->unit) >= 1)
                {
                    $cif            = $dt->cif;
                    $account_no[]   = $dt->account_no;
                    $trans_date     = !empty($trans_date) && strtotime($trans_date) > strtotime($dt->transaction_date) ? $trans_date : $dt->transaction_date;
                    $unit           = !empty($dt->approve_unit) ? in_array($dt->type_ref_code, ['SUB', 'TOPUP', 'SWTIN']) ? $dt->approve_unit : $dt->approve_unit * -1 : 0;
                    $total_unit    += $unit;
                    $product_id_account_no = ($dt->product_id.'_'.str_replace(' ','_',$dt->account_no));
                    
                    if (in_array($dt->type_ref_code, ['SUB', 'TOPUP', 'SWTIN']))
                    {
                        //$prd_amt[$dt->product_id]       = !empty($prd_amt[$dt->product_id]) ? $prd_amt[$dt->product_id] + $dt->net_amount : $dt->net_amount;
                        //$prd_unit[$dt->product_id]      = !empty($prd_unit[$dt->product_id]) ? $prd_unit[$dt->product_id] + $dt->approve_unit : $dt->approve_unit;

                        $prd_amt[$product_id_account_no]       = !empty($prd_amt[$product_id_account_no]) ? $prd_amt[$product_id_account_no] + $dt->net_amount : $dt->net_amount;
                        $prd_unit[$product_id_account_no]      = !empty($prd_unit[$product_id_account_no]) ? $prd_unit[$product_id_account_no] + $dt->approve_unit : $dt->approve_unit;
                        $ast_amt[$dt->asset_class_id]   = !empty($ast_amt[$dt->asset_class_id]) ? $ast_amt[$dt->asset_class_id] + $dt->net_amount : $dt->net_amount;
                        $ast_unit[$dt->asset_class_id]  = !empty($ast_unit[$dt->asset_class_id]) ? $ast_unit[$dt->asset_class_id] + $dt->approve_unit : $dt->approve_unit;
                    }
                    
                    //if (in_array($dt->product_id, array_keys($product)))
                    if (in_array($product_id_account_no, array_keys($product)))
                    {
                        // $asset_outstanding_reedem_freeze = AssetOutstanding::select(DB::raw('sum(freeze_unit::decimal) as total_freeze_unit'))
                        //                                  ->where([['product_id',$dt->product_id], ['investor_id',$request->investor_id], ['is_active','Yes']])
                        //                                  ->first();
                        // $asset_outstanding_reedem_freeze_unit = $asset_outstanding_reedem_freeze->total_freeze_unit != null ? $asset_outstanding_reedem_freeze->total_freeze_unit : 0;

                        $asset_outstanding_reedem_freeze_tmp = AssetFreeze::where([['product_id',$dt->product_id], ['investor_id',$request->investor_id], ['portfolio_id', $dt->portfolio_id], ['account_no',$dt->account_no]])->first();
                         $asset_outstanding_reedem_freeze =  !empty($asset_outstanding_reedem_freeze_tmp['freeze_unit']) ? floatval($asset_outstanding_reedem_freeze_tmp['freeze_unit']) : 0;
                    
                        //$curr_blc                                        = $unit * $product[$dt->product_id]['price'];
                        //$product[$dt->product_id]['unit']               += $unit;
                        //$product[$dt->product_id]['current_balance']    += $curr_blc;
                        $curr_blc                                             = $unit * $product[$product_id_account_no]['price'];
                        $product[$product_id_account_no]['unit']              += $unit;
                        $product[$product_id_account_no]['current_balance']   += $curr_blc;                    
                        $asset[$dt->asset_class_id]['current_balance']  += $curr_blc;
                        $asset[$dt->asset_class_id]['unit']             += $unit;
                        $total_blc                                      += $curr_blc;
                    }
                    else
                    {
                        // $asset_outstanding_reedem_freeze = AssetOutstanding::select(DB::raw('sum(freeze_unit::decimal) as total_freeze_unit'))
                        //                                  ->where([['product_id',$dt->product_id], ['investor_id',$request->investor_id], ['is_active','Yes']])
                        //                                  ->first();
                        // $asset_outstanding_reedem_freeze_unit = $asset_outstanding_reedem_freeze->total_freeze_unit != null ? $asset_outstanding_reedem_freeze->total_freeze_unit : 0;
                        $asset_outstanding_reedem_freeze_tmp = AssetFreeze::where([['product_id',$dt->product_id], ['investor_id',$request->investor_id], ['portfolio_id', $dt->portfolio_id], ['account_no',$dt->account_no]])->first();
                        $asset_outstanding_reedem_freeze =  !empty($asset_outstanding_reedem_freeze_tmp['freeze_unit']) ? floatval($asset_outstanding_reedem_freeze_tmp['freeze_unit']) : 0;
                        
                        $ref_val    = in_array($dt->type_ref_code, ['SUB', 'TOPUP', 'SWTIN']) ? 'Sub Fee' : 'Red Fee';
                        $price      = Price::where([['product_id', $dt->product_id], ['price_date', '<=', $this->app_date()], ['is_active', 'Yes']])->orderBy('price_date', 'DESC')->limit(1)->first();
                        $fee        = Fee::select('m_products_fee.fee_value', 'm_products_fee.value_type')
                                    ->join('m_fee_reference as b', 'm_products_fee.fee_id', '=', 'b.fee_reference_id')
                                    ->where([['product_id', $dt->product_id], ['b.reference_value', $ref_val], ['b.reference_type', 'Fee'], ['m_products_fee.is_active', 'Yes'], ['b.is_active', 'Yes']])
                                    ->first();
                        $curr_blc   = !empty($price->price_value) ? $unit * $price->price_value : 0;
                        $total_blc += $curr_blc;
                        
                        //$product[$dt->product_id] = [
                        $product[$product_id_account_no] = [
                            'trans_hist_id'         => $dt->trans_history_id,
                            'trans_reference_id'    => $dt->trans_reference_id,
                            'portfolio_id'          => $dt->portfolio_id,
                            'product_id'            => $dt->product_id,
                            'product_name'          => $dt->product_name,
                            'asset_class_name'      => $dt->asset_class_name,
                            'asset_class_color'     => $dt->asset_class_color,
                            'issuer_logo'           => $dt->issuer_logo,
                            'reference_no'          => $dt->reference_no,
                            'account_no'            => $dt->account_no,
                            'symbol'                => $dt->symbol,
                            'value_type'            => !empty($fee->value_type) ? $fee->value_type : null,
                            'fee_value'             => !empty($fee->fee_value) ? floatval($fee->fee_value) : null,
                            'unit'                  => floatval($unit),
                            'price'                 => !empty($price->price_value) ? floatval($price->price_value) : 0,
                            'current_balance'       => $curr_blc,     
                            'asset_outstanding_reedem_freeze' => $asset_outstanding_reedem_freeze,
                            'product_max_sell'      => !empty($dt->max_sell) ? $dt->max_sell : 0,
                            'product_min_sell'      => !empty($dt->min_sell) ? $dt->min_sell : 0,
                            'product_max_buy'       => !empty($dt->max_buy) ? $dt->max_buy : 0,
                            'product_min_buy'       => !empty($dt->min_buy) ? $dt->min_buy : 0,
                            'product_min_switch_out'      => !empty($dt->min_switch_out) ? $dt->min_switch_out : 0,
                            'product_max_switch_out'      => !empty($dt->max_switch_out) ? $dt->max_switch_out : 0,
                            'product_min_switch_in'       => !empty($dt->min_switch_in) ? $dt->min_switch_in : 0,
                            'product_max_switch_in'   => !empty($dt->max_switch_in) ? $dt->max_switch_in : 0,
                            'issuer_id'             => $dt->issuer_id,
                            'allow_switching'       => $dt->allow_switching,
                            'product_id_account_no' => ($dt->product_id.'_'.str_replace(' ','_',$dt->account_no))                                                                     
                        ];
                        $asset[$dt->asset_class_id] = [
                            'asset_class_name'  => $dt->asset_class_name,
                            'asset_class_color' => $dt->asset_class_color,
                            'symbol'            => $dt->symbol,
                            'unit'              => floatval($unit),
                            'current_balance'   => !in_array($dt->asset_class_id, array_keys($asset)) ? $curr_blc : $asset[$dt->asset_class_id]['current_balance'] + $curr_blc,
                        ];
                    }
                }

            }

            foreach ($product as $p_key => $p_val)
            {
                $product[$p_key]['avg_nav']             = !empty($prd_unit[$p_key]) && !empty($prd_amt[$p_key]) ? $prd_unit[$p_key] != 0 ? $prd_amt[$p_key] / $prd_unit[$p_key] : 0 : 0;
              	$product[$p_key]['investment_amount']   = $product[$p_key]['avg_nav'] * $product[$p_key]['unit'];
                $product[$p_key]['earnings']            = $product[$p_key]['current_balance'] - $product[$p_key]['investment_amount'];
                $product[$p_key]['returns']             = $product[$p_key]['investment_amount'] != 0 ? $product[$p_key]['earnings'] / $product[$p_key]['investment_amount'] * 100 : 0;
            }
            
            foreach ($asset as $a_key => $a_val)
            {
                $asset[$a_key]['avg_nav']             = !empty($ast_unit[$a_key]) && !empty($ast_amt[$a_key]) ? $ast_unit[$a_key] != 0 ? $ast_amt[$a_key] / $ast_unit[$a_key] : 0 : 0;
                $asset[$a_key]['investment_amount']   = $asset[$a_key]['avg_nav'] * $asset[$a_key]['unit'];
                $asset[$a_key]['earnings']            = $asset[$a_key]['current_balance'] - $asset[$a_key]['investment_amount'];
                $asset[$a_key]['returns']             = $asset[$a_key]['investment_amount'] != 0 ? $asset[$a_key]['earnings'] / $asset[$a_key]['investment_amount'] * 100 : 0;
            }

            if (!empty($request->get_invest) && $request->get_invest == 'Y')
                $invest = Investment::where([['investor_id', $request->investor_id], ['portfolio_id', $request->portfolio_id], ['is_active', 'Yes']])->first();

            return $this->app_response('Transaction', [
                'investor_id'       => $request->investor_id,
                'cif'               => $cif,
                'portfolio_id'      => $request->portfolio_id,
                'goal_invest_id'    => !empty($invest) && !empty($invest->goal_invest_id) ? $invest->goal_invest_id : '',
                'transaction_date'  => $trans_date,
                'asset'             => array_values($asset), 
                'product'           => array_values($product),
                'total_balance'     => $total_blc,
                'total_unit'        => $total_unit,
                'num_account'       => count(array_unique($account_no))
            ]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function goals_balance(Request $request)
    {
        try
        {
            $total_blc  = $total_dt = 0;
            $balance    = $price = $data2 = [];
            $status     = ['IP', 'TF', 'VR'];
            foreach ($status as $s)
            {
                $curr_blc   = 0;
                $data       = TransactionHistory::select('t_trans_histories.*', 'd.reference_code as type_ref_code', 'e.product_id', 'e.product_name')
                            ->join('m_trans_reference as b', 't_trans_histories.trans_reference_id', '=', 'b.trans_reference_id')
                            ->join('m_trans_reference as c', 't_trans_histories.status_reference_id', '=', 'c.trans_reference_id')
                            ->leftJoin('m_trans_reference as d', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'd.trans_reference_id')->where([['d.reference_type', 'Transaction Type'], ['d.is_active', 'Yes']]); })
                            ->join('m_products as e', 't_trans_histories.product_id', '=', 'e.product_id')
                            ->where([['investor_id', $request->investor_id], ['t_trans_histories.is_active', 'Yes'], ['e.is_active', 'Yes'],
                                    ['b.reference_type', 'Transaction Status'], ['b.reference_code', 'Done'], ['b.is_active', 'Yes'],
                                    ['c.reference_type', 'Goals Status'], ['c.is_active', 'Yes']])
                            ->whereRaw("LEFT(portfolio_id, 1) = '2'")
                            ->whereNotNull('account_no');
                if ($s == 'IP')
                    $data = $data->where(function($qry) use ($s) { $qry->where('c.reference_code', $s)->orWhere('c.reference_code', 'HD'); });
                else
                    $data = $data->where('c.reference_code', $s);
                
                foreach ($data->get() as $dt)
                {
                    if (!in_array($dt->product_id, $price))
                    {
                        $qry_price              = Price::where([['product_id', $dt->product_id], ['price_date', '<=', $this->app_date()], ['is_active', 'Yes']])->orderBy('price_date', 'DESC')->limit(1)->first();
                        $price[$dt->product_id] = !empty($qry_price->price_value) ? $qry_price->price_value : 0;
                    }
                    $unit       = !empty($dt->approve_unit) ? in_array($dt->type_ref_code, ['SUB', 'TOPUP', 'SWTIN']) ? $dt->approve_unit : $dt->approve_unit * -1 : 0;
                    $curr_blc  += $unit * $price[$dt->product_id];
                }
                $total_blc     += $curr_blc;
                $total_dt      += $data->count();
                $balance[$s]    = ['balance' => $curr_blc, 'total' => $data->count()];
            }

            $balance['TB'] = ['balance' => $total_blc, 'total' => $total_dt];
            
            return $this->app_response('Goals Balance', $balance);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function redeem(Request $request)
    {   
        return $this->trans_histories($request, ['RED']);
    }

    public function switching(Request $request)
    {   
        return $this->trans_histories($request, ['SWTIN', 'SWTOT']);
    }

    public function other(Request $request)
    {
        return $this->trans_histories($request, ['OTH', 'TRIN', 'TROT', 'BL', 'UBL', 'RIS']);
    }

    public function sales_fee()
    {
        try
        {
            $auth   = $this->auth_user();
            $fee    = DB::table('t_fee_outstanding as tfo')
                    ->join('u_investors as ui', 'tfo.investor_id', 'ui.investor_id')
                    ->where('tfo.fee_date', '>=', DB::raw("DATE_TRUNC('year', CURRENT_DATE)"))
                    ->where('tfo.fee_date', '<=', DB::raw("CURRENT_DATE"))
                    ->where('ui.sales_id', $auth->id)
                    ->select(
                        DB::raw("TO_CHAR(tfo.fee_date, 'YYYY-MM') as fee_month"),
                        DB::raw("SUM(tfo.fee_amount) as total_fee")
                    )
                    ->groupBy(DB::raw("TO_CHAR(tfo.fee_date, 'YYYY-MM')"))
                    ->orderBy('fee_month')
                    ->get();

            $fee = $fee->keyBy('fee_month')->map(function ($item) {
                return floatval($item->total_fee);
            });

            return $this->app_response('Sales Fee', $fee);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }

    public function save_redeem(Request $request)
    {   
        /* untuk test kirim email reademption                    
        $sendEmailNotification = new Administrative\Broker\MessagesController;
        $api_email = $sendEmailNotification->transaction(365);
        */

        /* untuk test kirim email subscription
        $sendEmailNotification = new MessagesController;
        $api_email = $sendEmailNotification->transaction(371);
        */
        //return $this->app_partials(1, 0, $api_email);

        try
        {   
            $otp_input  = !empty($request->otp_input) ? $request->otp_input : '';
            $manager    = $this->db_manager($request);
            $product_id = $request->product_id;
            $inv_id     = !empty($request->investor_id) ? $request->investor_id : Auth::id();
            $trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
            $type_ref   = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'RED']]], 'SA\Transaction\Reference')->original['data'];
            $cif        = !empty($request->cif) ? $request->cif : $this->auth_user()->cif;;
            //$sn         = substr($cif, -5) . date('ymd');
            $trans_hist_id  = array();
            $data_all       = array();
            $data           = array();
            $count_success  = 0;
            $count_failed   = 0;
            $account_sub = $this->get_account_sub($cif);  


            $sales_code     = Investor::select('user_code')
                              ->join('u_users as u', 'u.user_id', '=', 'u_investors.sales_id')
                              ->where([['u_investors.is_active', 'Yes'], ['u.is_active', 'Yes'], ['investor_id', $inv_id]])->first();
            
            $wms    = $this->api_ws(['sn' => 'SalesWaperd', 'val' => [$sales_code->user_code]])->original;
            
            if(!empty($wms['data']->agentCode) && !empty($wms['data']->agentWaperdExpDate) && $wms['data']->agentWaperdExpDate >  $this->app_date() && !empty($wms['data']->agentWaperdNo))
            {
                $sales_wap  = $wms['data']->agentCode;
            }else
            {
                $sales_wap  = $wms['data']->dummyAgentCode;
            }

            for ($i = 0; $i < count((array)$product_id); $i++)
            {
                $rst  = $this->cut_of_time($product_id[$i],'array');
                $transaction_date =  !empty($rst['transaction_date_allocation']) ? $rst['transaction_date_allocation'] : $this->app_date() ;
                //$qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                $qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $transaction_date], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                $sn         = substr($cif, -5) . date('ymd',strtotime($transaction_date));
                $ref_no     = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);
                $data       = TransactionHistory::select('t_trans_histories.*', 'c.reference_code as type_ref_code', 'd.product_id', 'd.product_name', 'e.asset_class_id', 'e.asset_class_name', 'e.asset_class_color', 'f.issuer_logo', 'g.symbol', 'h.cif', 'h.investor_id','d.min_buy','d.min_sell','d.max_buy','d.max_sell');
                $account_number = Account::where([['investor_account_id', $request->investor_account_id[$i]]])->first();
                $account_number = !empty($account_number) ? $account_number->account_no : null;
                //$product  = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_id[$i]]])->first();
                $product  = Product::where([['m_products.is_active', 'Yes'],['product_id',$request->product_id[$i]]])->first();
                $dataWMS  = array();
                if(!empty($product)) {      
                    //1. "orderType": "SELL",
                    $dataWMS[] = "SELL"; 
                    //2. "orderCategory": "MF",
                    $dataWMS[] = "MF";
                    //3. "customerIdentityType": "CIF",
                    $dataWMS[] = "CIF"; 
                    //4. "customerIdentityNo": "73178245",
                    $dataWMS[] = $cif; 
                    //5. "transactionDate": "2021-07-23" ,
                    $dataWMS[] = $transaction_date; 
                    //6. "productCode": "MISB",
                    $dataWMS[] = $product->product_code;             
                    //7. "amount": 1250000,
                    //$dataWMS[] = floatval($request->amount);            
                    $dataWMS[] =  !empty($request->unit[$i]) ? floatval($request->unit[$i]) : 0;                       
                    //8. "promos": null,
                    $dataWMS[] = null;                         
                    /* 9. "fees" */
                    //$feesContaint = '[{ "code": "DirectAmount", "amount": '.$request->amount.' }]';
                    //$dataWMS[] = [['code'   => 'DirectAmount', 'amount' => floatval($request->fee_amount)]];   
                    $fee_unit = !empty($request->fee_unit[$i]) ? floatval($request->fee_unit[$i]) : 0;                       
                    $dataWMS[] = [['code'   => 'DirectUnit', 'amount' => $fee_unit]];                        

                    //10. "customerAccountNo": "0507011381",
                    $dataWMS[] = $account_number;
                    //"11. paymentMethod": "BSI-Transfer",
                    $dataWMS[] = "BSI-Transfer";
                    //"12. inputMode": "UNIT untuk redeem",
                    $dataWMS[] = "UNIT";
                    //"13. charges": 0,
                    $dataWMS[] = 0;
                    //14. "portfolioNo": "SubAccountNo001",
                    //$dataWMS[] =  !empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null;             
                    $dataWMS[] =  !empty($request->account_no[$i]) ? $request->account_no[$i] : null;             
                    //15. counterPartyReferralCode": "123",
                    $dataWMS[] = $sales_wap;            
                    //"16. isAdvice": false,
                    $dataWMS[] = false;            
                    //"17. remark": "test buy mf 001",
                    $dataWMS[] = "000";            
                    //"18. referenceNo": "PortfolioNumber001",
                    //$dataWMS[] = !empty($request->portfolio_id[$i]) ? $request->portfolio_id[$i] : null;
		            $dataWMS[] = !empty($ref_no) ?  $ref_no : null ;            
                    //"19. entryBy": "dummy",
                    $dataWMS[] = $manager->user;                        
                    //"20. entryHost": "localhost"
                    $dataWMS[] = $manager->ip;  
                    //"21. entryHost": "localhost"
                    $dataWMS[] = $manager->ip;  

                    $api = $this->api_ws(['sn' => 'TransactionWMSSub', 'val' => $dataWMS])->original;
                    //$api = $this->api_ws(['sn' => 'TransactionWMSSub', 'val' => $dataWMS]);
                    if(!empty($api['success']) && $api['success'] == true) {
                        $data   = ['product_id'           => $request->product_id[$i],
                                   'investor_id'          => $inv_id,
                                   'trans_reference_id'   => $trans_ref,
                                   'type_reference_id'    => $type_ref,
                                   'transaction_date'     => $transaction_date,
                                   'reference_no'         => $ref_no,
                                   'portfolio_id'         => $request->portfolio_id[$i],
                                   //'account_no'           => !empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null,
                                   'account_no'           => !empty($request->account_no[$i]) ? $request->account_no[$i] : null,                                   
                                   'unit'                 => !empty($request->unit[$i]) ? $request->unit[$i] : 0 ,
                                   'percentage'           => !empty($request->fee_percent[$i]) ? $request->fee_percent[$i] : null,
                                   'fee_amount'           => !empty($request->fee_amount[$i]) ? $request->fee_amount[$i] : null,
                                   'tax_amount'           => !empty($request->tax_amount[$i]) ? $request->tax_amount[$i] : null,
                                   'investor_account_id'  => !empty($request->investor_account_id[$i]) ? $request->investor_account_id[$i] : null,
                                   'send_wms'             => true,
                                   'guid'                 => !empty($api['data']->data) ? $api['data']->data : null, 
                                   'fee_unit'             => !empty($request->fee_unit[$i]) ? $request->fee_unit[$i] : null,                               
                                   'created_by'           => $manager->user,
                                   'created_host'         => $manager->ip  
                                ];
                        $trans_hist_return = TransactionHistory::create($data);

                        if(!empty($trans_hist_return->trans_history_id)) {
                            $trans_hist_id[] = $trans_hist_return->trans_history_id;

                        //$account_freeze =!empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null;
                        $account_freeze =!empty($request->account_no[$i]) ? $request->account_no[$i] : null;
                        $portfolio_id_freeze = !empty($request->portfolio_id[$i]) ? $request->portfolio_id[$i] : null;
                        
                        $freeze =  AssetFreeze::where([['investor_id',  $inv_id], ['product_id', $request->product_id[$i]], ['portfolio_id', $portfolio_id_freeze], ['account_no', $account_freeze]])->first();

                        $act_freez  = empty($freeze->asset_freeze_id) ? 'cre' : 'upd';
                        $redeem_freeze_unit = 0;
                        $unit_redeem_freeze = !empty($request->unit[$i]) ? $request->unit[$i] : 0;
                        if($unit_redeem_freeze > 0)
                        {
                            if(!empty($freeze->freeze_unit))
                            {
                                $redeem_freeze_unit =  $freeze->freeze_unit + $unit_redeem_freeze;
                            }else{
                                 $redeem_freeze_unit = $unit_redeem_freeze;
                            }
                        }
                       
                        $data_freez = [
                            'investor_id'           => $inv_id,
                            'product_id'            => $request->product_id[$i],
                            'portfolio_id'          => $portfolio_id_freeze,
                            //'account_no'            => $account_sub[$product->product_code],
                            'account_no'            => !empty($request->account_no[$i]) ? $request->account_no[$i] : null,                            
                            $act_freez.'ated_by'    => 'System',
                            $act_freez.'ated_host'  => '::1'
                        ];
                        if(!empty($unit_redeem_freeze))
                        {
                           $data_freez=  array_merge($data_freez, ['freeze_unit' => $redeem_freeze_unit]);
                        }

                        $freeze = (empty($freeze->asset_freeze_id)) ? AssetFreeze::create($data_freez) : AssetFreeze::where('asset_freeze_id', $freeze->asset_freeze_id)->update($data_freez);

                            /*    
                            $sendEmailNotification = new Administrative\Broker\MessagesController;
                            $api_email = $sendEmailNotification->transaction($trans_hist_return->trans_history_id);
                            */

                            $sendEmailNotification = new MessagesController;
                            $api_email = $sendEmailNotification->transaction($trans_hist_return->trans_history_id);

                            if(!empty($api_email->original['success']) && $api_email->original['success'] == 1) {
			                   TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_email' => 'Yes']);                
                            } else {
			                   TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_email' => 'No']);                                            	
                            }        

                            $product_notification_unit_redeem = !empty($request->unit[$i]) ? $request->unit[$i] : 0;
                            $product_notification = $product->product_name.' Sejumlah ( '.$product_notification_unit_redeem.' Unit )';
                            $investor_mobile_phone  = Investor::select('mobile_phone')->where([['u_investors.is_active', 'Yes'],['investor_id',  $inv_id]])->first();
                            $conf    = MobileContent::where([['mobile_content_name', 'TransactionRedeem'], ['is_active', 'Yes']])->first();
                            $msg     = !empty($conf->mobile_content_text) ? str_replace('{product}', $product_notification, $conf->mobile_content_text) : '';
                            $api_sms = $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$investor_mobile_phone->mobile_phone, $msg]]);   

                            if(!empty($api_sms->original['code']) && $api_sms->original['code'] == 200) {
			                   TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_sms' => 'Yes']);
                            } else {
			                   TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_sms' => 'No']);                                            	
                            }
                        } 

                        $data['send_notication_email'] = $api_email;
                        $data['send_notication_sms']   = $api_sms;
                        $data['forward_to_wms_respon'] = $api; 
                        $data['forward_to_wms_status'] = 'success';
                        $data['forward_to_wms_respon'] = $api; 
                        $count_success++;     
                    } else {
                      $data['send_notication_email'] = null;
                      $data['send_notication_sms']   = null;
                      $data['status_forward_to_wms'] = 'failed';   
                      $data['forward_to_wms_respon'] = null;      
                      $count_failed++;
                    }                 
                } 

                $data_all[] = $data; 

                $rn++;
            }

            if(count($trans_hist_id) > 0) {
                $trans_history_implode = implode('~', $trans_hist_id);
                TransactionOtp::where(['investor_id' => $inv_id,'otp' => $otp_input,'is_active' => 'Yes'])->update(['is_valid' => 'Yes','trans_history_id' => $trans_history_implode]);                
            }

            return $this->app_partials($count_success, $count_failed, ['data' => $data, 'session_forget' => 'redeem_checkout']);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save_topup(Request $request)
    {  
        try
        {   
            $rst        = $this->cut_of_time($request->product_id,'array');
            $transaction_date =  !empty($rst['transaction_date_allocation']) ? $rst['transaction_date_allocation'] : $this->app_date() ;
            $manager    = $this->db_manager($request);
            $trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
            $type_ref   = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'SUB']]], 'SA\Transaction\Reference')->original['data'];
            $cif        = !empty($request->cif) ? $request->cif : $this->auth_user()->cif;
            $sn         = substr($cif, -5) . date('ymd',strtotime($transaction_date));
            //$qry_trs    = TransactionHistory::where([['investor_id', $request->investor_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
            $qry_trs    = TransactionHistory::where([['investor_id', $request->investor_id], ['transaction_date', $transaction_date], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
            $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
            $ref_no     = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);
            $otp_input = !empty($request->otp_input) ? $request->otp_input : '';


            if(substr_count($request->investor_account_id,'-')) {
                $tmp_investor_account_id = explode('-', $request->investor_account_id);
                $request->investor_account_id = $tmp_investor_account_id[1];               
            }

            $account_number = Account::where([['investor_account_id', $request->investor_account_id]])->first();
            $account_number = !empty($account_number) ? $account_number->account_no : null;
            $inv_id   = !empty($request->investor_id) ? $request->investor_id : Auth::id();


            $sales_code     = Investor::select('user_code')
                              ->join('u_users as u', 'u.user_id', '=', 'u_investors.sales_id')
                              ->where([['u_investors.is_active', 'Yes'], ['u.is_active', 'Yes'], ['investor_id',  $inv_id]])->first();

            
            $wms    = $this->api_ws(['sn' => 'SalesWaperd', 'val' => [$sales_code->user_code]])->original;
        
            if(!empty($wms['data']->agentCode) && !empty($wms['data']->agentWaperdExpDate) && $wms['data']->agentWaperdExpDate >  $this->app_date() && !empty($wms['data']->agentWaperdNo))
            {
                $sales_wap  = $wms['data']->agentCode;
            }else
            {
                $sales_wap  = $wms['data']->dummyAgentCode;
            }


            $product  = Product::where([['m_products.is_active', 'Yes'],['product_id',$request->product_id]])
                      ->first();
            if(!empty($request->account_no)) {
                $account_no = $request->account_no;
            } else {
                //$account_sub = $this->get_account_sub($cif); 
                $account_sub = $this->get_account_sub_regular($cif); 
                $account_no = !empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null;                
            }

            if(!empty($product)) {         
                //1. "orderType": "BUY",
                $dataWMS[] = "BUY"; 
                //2. "orderCategory": "MF",
                $dataWMS[] = "MF";
                //3. "customerIdentityType": "CIF",
                $dataWMS[] = "CIF"; 
                //4. "customerIdentityNo": "73178245",
                $dataWMS[] = $cif; 
                //5. "transactionDate": "2021-07-23" ,
                $dataWMS[] = $transaction_date; 
                //6. "productCode": "MISB",
                $dataWMS[] = $product->product_code;             
                //7. "amount": 1250000,
                //$dataWMS[] = floatval($request->amount);                         
                $dataWMS[] = floatval($request->net_amount);                         

                //8. "promos": null,
                $dataWMS[] = null;                         
                /* 9. "fees" */
                //$feesContaint = '[{ "code": "DirectAmount", "amount": '.$request->amount.' }]';
                $dataWMS[] = [['code'   => 'DirectAmount', 'amount' => floatval($request->fee_amount)]];                        
                //10. "customerAccountNo": "0507011381",
                $dataWMS[] = $account_number;
                //"11. paymentMethod": "BSI-Transfer",
                $dataWMS[] = "BSI-Transfer";
                //"12. inputMode": "NET",
                $dataWMS[] = "NET";
                //"13. charges": 0,
                $dataWMS[] = 0;
                //14. "portfolioNo": "SubAccountNo001",
                //$dataWMS[] = null;                        
                //$dataWMS[] =   !empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null; 
                $dataWMS[] = $account_no; 

                //15. counterPartyReferralCode": "123",
                $dataWMS[] = $sales_wap;            
                //"16. isAdvice": false,
                $dataWMS[] = false;            
                //"17. remark": "test buy mf 001",
                $dataWMS[] = "000";            
                //"18. referenceNo": "PortfolioNumber001",
                //$dataWMS[] = !empty($request->portfolio_id) ? $request->portfolio_id : null;  
		        $dataWMS[] = !empty($ref_no) ?  $ref_no : null ;          
                //$dataWMS[] = 'xxx';            
                //"19. entryBy": "dummy",
                $dataWMS[] = $manager->user;                        
                //"20. entryHost": "localhost"
                $dataWMS[] = $manager->ip;  
                //$api = $this->api_ws(['sn' => 'SmsGateway', 'val' => $dataWMS])->original;
                $api = $this->api_ws(['sn' => 'TransactionWMSSub', 'val' => $dataWMS])->original;
                //$api = $this->api_ws(['sn' => 'TransactionWMSSub', 'val' => $dataWMS]);
                if(!empty($api['success']) && $api['success'] == true) {
                    $data  = ['product_id'            => $request->product_id,
                               'investor_id'          => $inv_id,
                               'trans_reference_id'   => $trans_ref,
                               'type_reference_id'    => $type_ref,
                               //'transaction_date'   => $this->app_date(),
                               'transaction_date'     => $transaction_date,
                               'reference_no'         => $ref_no,
                               'portfolio_id'         => $request->portfolio_id,
                               //'account_no'         => !empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null,
                               'account_no'           => $account_no,                               
                               //'amount'               => $request->amount,
                               'amount'               => !empty($request->total_amount) ? $request->total_amount : 0,
                               'net_amount'           => !empty($request->net_amount) ? $request->net_amount : 0,
                               'fee_amount'           => !empty($request->fee_amount) ? $request->fee_amount : 0,
                               'tax_amount'           => !empty($request->tax_amount) ? $request->tax_amount : 0,
                               'percentage'           => !empty($request->fee_percent) ? $request->fee_percent : null,
                               'investor_account_id'  => $request->investor_account_id,
                               'send_wms'             => true,
                               'guid'                 => !empty($api['data']->data) ? $api['data']->data : null,
                               'created_by'           => $manager->user,
                               'created_host'         => $manager->ip
                              ];
                    $trans = TransactionHistory::create($data);
                    if(!empty($trans->trans_history_id)) {
                        TransactionOtp::where(['investor_id' => $request->investor_id,'otp' => $otp_input,'is_active' => 'Yes'])->update(['is_valid' => 'Yes','trans_history_id' => $trans->trans_history_id]);                

                        /*    
                        $sendEmailNotification = new Administrative\Broker\MessagesController;
                        $api_email = $sendEmailNotification->transaction($trans->trans_history_id);
                        */

                        $sendEmailNotification = new MessagesController;
                        $api_email = $sendEmailNotification->transaction($trans->trans_history_id);
                        
                        if(!empty($api_email->original['success']) && $api_email->original['success'] == 1) {
		                   TransactionHistory::where(['trans_history_id' => $trans->trans_history_id])->update(['notif_send_email' => 'Yes']);                
                        } else {
		                   TransactionHistory::where(['trans_history_id' => $trans->trans_history_id])->update(['notif_send_email' => 'No']);                                            	
                        }        

                        $product_notification_amount = !empty($request->total_amount) ? $request->total_amount : 0;
                        $product_notification = $product->product_name.' Sebesar (Rp. '.number_format($product_notification_amount).')';
                        $investor_mobile_phone  = Investor::select('mobile_phone')->where([['u_investors.is_active', 'Yes'],['investor_id',  $inv_id]])->first();
                        $conf    = MobileContent::where([['mobile_content_name', 'TransactionSub'], ['is_active', 'Yes']])->first();
                        $msg     = !empty($conf->mobile_content_text) ? str_replace('{product}', $product_notification, $conf->mobile_content_text) : '';
                        $api_sms = $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$investor_mobile_phone->mobile_phone, $msg]]);   

                        if(!empty($api_sms->original['code']) && $api_sms->original['code'] == 200) {
		                   TransactionHistory::where(['trans_history_id' => $trans->trans_history_id])->update(['notif_send_sms' => 'Yes']);                
                        } else {
		                   TransactionHistory::where(['trans_history_id' => $trans->trans_history_id])->update(['notif_send_sms' => 'No']);                                            	
                        }
                    }


                  $data['send_notication_email'] = $api_email;
                  $data['send_notication_sms']   = $api_sms;
                  $data['forward_to_wms_status'] = 'success';
                  $data['forward_to_wms_respon'] = $api;  

                  return $this->app_partials(1, 0, ['data' => $data]);   
                  

                } else {
                  $data['send_notication_email'] = null;
                  $data['send_notication_sms']   = null;
                  $data['status_forward_to_wms'] = 'failed';   
                  $data['forward_to_wms_respon'] = $api;      

                  return $this->app_partials(0, 1, ['data' => $data]);    
                }                 
            }    

        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }


    public function status(Request $request)
    {
        try
        {
            $type = !empty($request->type) ? $request->type : 'Goals Status';
            $data = Reference::select('trans_reference_id', 'reference_name', 'reference_code', 'reference_color')
                    ->where([['reference_type', $type], ['is_active', 'Yes']])
                    ->orderBy('reference_name')
                    ->get();
            return $this->app_response('Status', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }   
    }
    
    /*
    private function trans_histories($request, $code)
    {
        try
        {
            $user   = $this->auth_user()->usercategory_name == 'Investor' ? 'b.investor_id' : 'b.sales_id';
            $data   = TransactionHistory::select('t_trans_histories.*', 'b.fullname', 'b.photo_profile', 'b.cif', 'c.product_name', 'd.asset_class_name', 'e.reference_name as status_name', 'f.reference_name as trans_reference_name', 'f.reference_code as trans_reference_code', 'f.reference_color', 'g.reference_name as type_reference_name', 'h.issuer_logo', 'i.account_no as bank_account_no', 'i.account_name as bank_account_name')
                    ->join('u_investors as b', 't_trans_histories.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_trans_histories.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { return $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as e', function($qry) { return $qry->on('t_trans_histories.status_reference_id', '=', 'e.trans_reference_id')->where([['e.reference_type', 'Goals Status'], ['e.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as f', function($qry) { return $qry->on('t_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')->where([['f.reference_type', 'Transaction Status'], ['f.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Transaction Type'], ['g.is_active', 'Yes']]); })
                    ->leftJoin('m_issuer as h', function($qry) { return $qry->on('c.issuer_id', '=', 'h.issuer_id')->where('h.is_active', 'Yes'); })
                    ->leftJoin('u_investors_accounts as i', function($qry) { return $qry->on('t_trans_histories.investor_account_id', '=', 'i.investor_account_id')->where('i.is_active', 'Yes'); })
                    ->where([['t_trans_histories.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->whereIn('g.reference_code', $code)
                    ->orderBy('t_trans_histories.trans_history_id', 'desc');  
          
            if($this->auth_user()->usercategory_name != 'Super Admin')
            {
                $data = $data->where($user, $this->auth_user()->id);
            }

            if(!in_array('RED', $code))
            {                 
                //$data = $data->whereNotNull('t_trans_histories.amount');
            }

            if ($code != 'OTH'){
                $data->whereIn('g.reference_code', $code);
            }else{
                $data->whereNotIn('g.reference_code', ['SUB', 'TOPUP', 'RED', 'SWTIN', 'SWTOT']);
            }
            
            if (!empty($request->search))
            {
                $search = $request->search;
                $data   = $data->where(function($qry) use ($search) {
                            $qry->where('reference_no', 'ilike', '%'. $search .'%')
                                ->orWhere('portfolio_id', 'ilike', '%'. $search .'%')
                                ->orWhere('product_name', 'ilike', '%'. $search .'%')
				                ->orWhere('cif', 'ilike', '%'. $search .'%')
                                ->orWhere('fullname', 'ilike', '%'. $search .'%');
                        });
            }
            
            if (!empty($request->trans_reference_id))
                $data = $data->where('t_trans_histories.trans_reference_id', $request->trans_reference_id);
            if (!empty($request->start_date))
                $data = $data->where('t_trans_histories.transaction_date', '>=', $request->start_date);
            if (!empty($request->end_date))
                $data = $data->where('t_trans_histories.transaction_date', '<=', $request->end_date);

            if (!empty($request->trans_history_id))
            {
                $data = $data->where('t_trans_histories.trans_history_id', $request->trans_history_id)->first();
            }
            elseif (!empty($request->account_no))
            { 
                $data = $data->where('t_trans_histories.account_no', $request->account_no)->get();
                $code = array('SWTIN');
            }
            else if (!empty($request->page))
            {
                $limit  = !empty($request->limit) ? $request->limit : 10;
                $page   = !empty($request->page) ? $request->page : 1;
                $data   = $data->paginate($limit, ['*'], 'page', $page);
            }
            else
            {
                $data = !empty($request->limit) ? $data->limit($request->limit)->get() : $data->get();
            }

            $target = ['RED', 'SWTIN', 'SWTOT'];
            if (count(array_intersect($code, $target)) == count($target))
               // $data->whereNotNull('t_trans_histories.amount');

            return $this->app_response('Transaction', $data);
            // if(in_array('SWTIN', $code)) {
            //     $dataSwitcingOut = [];
            //     foreach($data as $val) {
            //       $dataSwitcingOut[$val->account_no] = $this->get_switching_in_out($val->account_no);
            //     }
            // } 

            // if(in_array('SWTIN', $code)) {
            //    return $this->app_response('Transaction', ['data'=>$data,'dataSwitcingInOut'=>$dataSwitcingOut]); 
            // } else {    
                // return $this->app_response('Transaction', $data);
            // }    
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    */

    private function trans_histories($request, $code)
    {
        try
        {
            $user   = $this->auth_user()->usercategory_name == 'Investor' ? 'b.investor_id' : 'b.sales_id';
            $data   = TransactionHistory::select('t_trans_histories.*', 'b.fullname', 'b.photo_profile', 'b.cif', 'c.product_name', 'd.asset_class_name', 'e.reference_name as status_name', 'f.reference_name as trans_reference_name', 'f.reference_code as trans_reference_code', 'f.reference_color', 'g.reference_name as type_reference_name', 'h.issuer_logo', 'i.account_no as bank_account_no', 'i.account_name as bank_account_name')
                    ->join('u_investors as b', 't_trans_histories.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_trans_histories.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { return $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as e', function($qry) { return $qry->on('t_trans_histories.status_reference_id', '=', 'e.trans_reference_id')->where([['e.reference_type', 'Goals Status'], ['e.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as f', function($qry) { return $qry->on('t_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')->where([['f.reference_type', 'Transaction Status'], ['f.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Transaction Type'], ['g.is_active', 'Yes']]); })
                    ->leftJoin('m_issuer as h', function($qry) { return $qry->on('c.issuer_id', '=', 'h.issuer_id')->where('h.is_active', 'Yes'); })
                    ->leftJoin('u_investors_accounts as i', function($qry) { return $qry->on('t_trans_histories.investor_account_id', '=', 'i.investor_account_id')->where('i.is_active', 'Yes'); })
                    ->where([['t_trans_histories.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->whereIn('g.reference_code', $code)
                    ->orderBy('t_trans_histories.trans_history_id', 'desc');  

            if($this->auth_user()->usercategory_name != 'Super Admin')
            {
                $data = $data->where($user, $this->auth_user()->id);
            }

            if(!in_array('RED', $code) && !in_array('SWTIN', $code) && !in_array('SWTOUT', $code) )
            {                 
                $data = $data->whereNotNull('t_trans_histories.amount');
            }



            if ($code != 'OTH'){
                $data->whereIn('g.reference_code', $code);
            }else{
                $data->whereNotIn('g.reference_code', ['SUB', 'TOPUP', 'RED', 'SWTIN', 'SWTOT']);
            }
            
            if (!empty($request->search))
            {
                $search = $request->search;
                $data   = $data->where(function($qry) use ($search) {
                            $qry->where('reference_no', 'ilike', '%'. $search .'%')
                                ->orWhere('portfolio_id', 'ilike', '%'. $search .'%')
                                ->orWhere('product_name', 'ilike', '%'. $search .'%')
				                ->orWhere('cif', 'ilike', '%'. $search .'%')
                                ->orWhere('fullname', 'ilike', '%'. $search .'%');
                        });
            }
            
            if (!empty($request->trans_reference_id))
                $data = $data->where('t_trans_histories.trans_reference_id', $request->trans_reference_id);
            if (!empty($request->start_date))
                $data = $data->where('t_trans_histories.transaction_date', '>=', $request->start_date);
            if (!empty($request->end_date))
                $data = $data->where('t_trans_histories.transaction_date', '<=', $request->end_date);

            if (!empty($request->trans_history_id))
            {
                $data = $data->where('t_trans_histories.trans_history_id', $request->trans_history_id)->first();
            }
            elseif (!empty($request->account_no))
            { 
                $data = $data->where('t_trans_histories.account_no', $request->account_no)->get();
                $code = array('SWTIN');
            }
            else if (!empty($request->page))
            {
                $limit  = !empty($request->limit) ? $request->limit : 10;
                $page   = !empty($request->page) ? $request->page : 1;
                $data   = $data->paginate($limit, ['*'], 'page', $page);
            }
            else
            {
                $data = !empty($request->limit) ? $data->limit($request->limit)->get() : $data->get();
            }

            $target = ['RED', 'SWTIN', 'SWTOT'];
            if (count(array_intersect($code, $target)) == count($target))
                $data->whereNotNull('t_trans_histories.amount');

            return $this->app_response('Transaction', $data);
            // if(in_array('SWTIN', $code)) {
            //     $dataSwitcingOut = [];
            //     foreach($data as $val) {
            //       $dataSwitcingOut[$val->account_no] = $this->get_switching_in_out($val->account_no);
            //     }
            // } 

            // if(in_array('SWTIN', $code)) {
            //    return $this->app_response('Transaction', ['data'=>$data,'dataSwitcingInOut'=>$dataSwitcingOut]); 
            // } else {    
                // return $this->app_response('Transaction', $data);
            // }    
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
        
    public function update_account(Request $request)
    {        
        try
        {
            $validate = ['account_no' => 'required', 'trans_hist_id' => 'required'];
            if (!empty($this->app_validate($request, $validate)))
                exit;
            
            $managed = $this->db_manager($request);
            TransactionHistory::where('trans_history_id', $request->trans_hist_id)
                                ->update(['account_no' => $request->account_no, 'updated_by' => $managed->user, 'updated_host' => $managed->ip]);
            
            return $this->app_response('Transaction', 'Add Account No. Successfully');
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function selling_product()
    {
        try
        {
            $transaction = TransactionHistory::selectRaw('SUM(t_trans_histories.net_amount) as amount, b.product_name, max(b.product_id) as product, c.issuer_logo, d.asset_class_name')
                        ->join('m_products as b', 't_trans_histories.product_id', '=', 'b.product_id')
                        ->join('m_issuer as c', 'b.issuer_id', '=', 'c.issuer_id')
                        ->join('m_asset_class as d', 'b.asset_class_id', '=', 'd.asset_class_id')
                        ->leftJoin('m_trans_reference as f', function($qry) { return $qry->on('t_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')->where([['f.reference_type', 'Transaction Status'], ['f.is_active', 'Yes']]); })
                        ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Transaction Type'], ['g.is_active', 'Yes']]); })
                        ->where([['t_trans_histories.is_active', 'Yes'], ['b.is_active', 'Yes'], ['t_trans_histories.transaction_date', '>=',  date('Y-m-01', strtotime('-1 month'))], ['t_trans_histories.transaction_date', '<',  date('Y-m-01')], ['c.is_active', 'Yes'], ['d.is_active', 'Yes'], ['f.reference_code', 'Done'], ['g.reference_code', 'SUB']])
                        ->groupBy(['b.product_name', 'b.product_id','c.issuer_logo','d.asset_class_name'])
                        // ->groupBy('c.issuer_logo')
                        // ->groupBy('d.asset_class_name')
                        ->limit(10)
                        ->orderBy('product', 'desc')
                        ->get();

            return $this->app_response('Transaction', $transaction);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function revenue()
    {
        try
        {
            $transaction = TransactionFeeOutstanding::selectRaw('SUM(t_fee_outstanding.fee_amount) as amount')
                        ->where([['t_fee_outstanding.is_active', 'Yes'], ['t_fee_outstanding.fee_date', '>=',  date('Y-m-01', strtotime('-1 month'))], ['t_fee_outstanding.fee_date', '<',  date('Y-m-01')]])
                        ->get();
            $transaction_now = TransactionFeeOutstanding::selectRaw('SUM(t_fee_outstanding.fee_amount) as amount')
                        ->where([['t_fee_outstanding.is_active', 'Yes'], ['t_fee_outstanding.fee_date', $this->app_date()]])
                        ->get();       
            foreach ($transaction as $trn) {
                $trnan = $trn;    
            }    
            foreach ($transaction_now as $now) {
                $trn_now = $now;    
            }              
            $growth     = $trnan->amount > 0 ? $trnan->amount / $trn_now->amount : 0;  
            return $this->app_response('Transaction', ['transaction' => $trnan->amount, 'growth' => $growth]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function revenue_date(Request $request)
    {
        try
        {
            $day = !empty($request->day) ? $request->day-1 : 0;
            $data_week = [];
            for($i = $day; $i >= 0; $i-- )
            {
                $fee_date             = date('Y-m-d', strtotime('- '.$i.' day'));
                $transaction_week     = TransactionFeeOutstanding::where([['is_active', 'Yes'], ['fee_date', $fee_date]])->sum('fee_amount');
                $data_week[$fee_date] = floatval($transaction_week) ;
            }
                  
            return $this->app_response('Transaction', $data_week);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function get_switching_in_out($accountNo) {
        /*
            $data       = TransactionHistory::select('t_trans_histories.*', 'c.reference_code as type_ref_code', 'd.product_id', 'd.product_name', 'e.asset_class_id', 'e.asset_class_name', 'e.asset_class_color', 'f.issuer_logo', 'g.symbol', 'h.cif', 'h.investor_id','d.min_buy','d.min_sell','d.max_buy','d.max_sell')
                        ->join('m_trans_reference as b', 't_trans_histories.trans_reference_id', '=', 'b.trans_reference_id')
                        ->leftJoin('m_trans_reference as c', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Transaction Type'], ['c.is_active', 'Yes']]); })
                        ->join('m_products as d', 't_trans_histories.product_id', '=', 'd.product_id')
                        ->leftJoin('m_asset_class as e', function($qry) { return $qry->on('d.asset_class_id', '=', 'e.asset_class_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as f', function($qry) { return $qry->on('d.issuer_id', '=', 'f.issuer_id')->where('f.is_active', 'Yes'); })
                        ->leftJoin('m_currency as g', function($qry) { return $qry->on('d.currency_id', '=', 'g.currency_id')->where('g.is_active', 'Yes'); })
                        ->leftJoin('u_investors as h', function($qry) { return $qry->on('h.investor_id', '=', 't_trans_histories.investor_id')->where('h.is_active', 'Yes'); })
                        ->where([['t_trans_histories.investor_id', $request->investor_id], ['portfolio_id', $request->portfolio_id], ['t_trans_histories.is_active', 'Yes'],
                                 ['b.reference_type', 'Transaction Status'], ['b.reference_code', 'Done'], ['d.is_active', 'Yes']])
                        ->whereNotNull('account_no')
                        ->get();
        */                

        return TransactionHistory::select('t_trans_histories.*','t_trans_histories.reference_no','t_trans_histories.account_no','p.product_name','p.product_name','g.reference_name as type_reference_name','g.reference_code as type_reference_code','a.asset_class_name','g.reference_color', 'g.reference_name as type_reference_name','f.reference_name as trans_reference_name', 'f.reference_code as trans_reference_code', 'f.reference_color', )
            ->leftJoin('m_products as p', function($qry) { return $qry->on('t_trans_histories.product_id', '=', 'p.product_id')->where('p.is_active', 'Yes'); })
            ->leftJoin('m_asset_class as a', function($qry) { return $qry->on('a.asset_class_id', '=', 'p.asset_class_id')->where('a.is_active', 'Yes'); })
            ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Transaction Type'], ['g.is_active', 'Yes']]); })   
            ->leftJoin('m_trans_reference as f', function($qry) { return $qry->on('t_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')->where([['f.reference_type', 'Transaction Status'], ['f.is_active', 'Yes']]); })             
            ->whereIn('g.reference_code', ['SWTIN','SWTOT']) 
            ->where([['t_trans_histories.is_active', 'Yes'],['t_trans_histories.account_no', $accountNo]])
            ->orderBy('g.reference_type', 'desc')->get();        
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
                $data['product_name'] =  $rst->product_name; 
                $data['product_min_buy'] =  $rst->min_buy;
                $data['product_max_buy'] =  $rst->max_buy;            
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

    private function cut_of_time_get_available_date($dateCheck,$currencyId) {
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

    public function otp_send(Request $request) {
        try
        {
            $manager     = $this->db_manager($request);
            $otp         = rand(1000,9999);
            $investor_id = $this->auth_user()->id;
            $mobile_phone = $this->auth_user()->mobile_phone;            

            $msg  = 'Please Input This Token for transaction: '.$otp;
            $api = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$mobile_phone,$msg]])->original;
            $data = array();

            if (!empty($api['code']) && $api['code'] == 200)
            {
                TransactionOtp::where(['investor_id' => $investor_id,'is_valid' => 'No'])
                               ->update(['is_active' => 'No']);

                $data       = ['trans_history_id'     => null,
                               'investor_id'          => $investor_id,
                               'otp'                  => $otp,
                               'mobile_phone'         => $mobile_phone,
                               'otp_created'          => date('Y-m-d H:i:s'),
                               'is_valid'             => 'No',
                               'is_active'            => 'Yes',
                               'created_by'           => $manager->user,
                               'created_host'         => $manager->ip
                              ];
                TransactionOtp::create($data);                
            }

            return $this->app_response('Transaction Send OTP', $data);               
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function otp_verified(Request $request,$otp) {
        try
        {
            $data = array();
            $data['status_verified'] = false;
            $data['time_was_waiting_second'] = 0;
            $data['time_max_waiting_second'] = 60;

            if(!empty($otp)) {
                $otp = $otp;
                $investor_id = $this->auth_user()->id;                

                $rst = TransactionOtp::where(['investor_id' => $investor_id,'otp'=>$otp,'is_valid' => 'No','is_active' => 'Yes'])->first();
                if(!empty($rst)) {
                    $diff = (time() - strtotime($rst->otp_created));
                    if($diff <= $data['time_max_waiting_second']) { 
                         $data['status_verified'] = true;
                    } else {
                        TransactionOtp::where(['investor_id' => $investor_id,'otp' => $otp,'is_valid' => 'No'])
                        ->update(['is_active' => 'No']);
                    } 

                    $data['time_was_waiting_second'] = $diff;                  
                }
            }
                
            return $this->app_response('Transaction Verified OTP', $data);               
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function get_account_sub($cif) {
        $data = array();
        $api = $this->api_ws(['sn' => 'InvestorAsset', 'val' => [$cif]])->original;
        if(!empty($api['data'])) {
          foreach($api['data'] as $dt) {
            $data[$dt->productCode] = $dt->accountNo;
          }   
        } 

        return $data;
    }

    private function get_account_sub_regular($cif) {
        $data = array();
        $api = $this->api_ws(['sn' => 'InvestorAsset', 'val' => [$cif]])->original;
        if(!empty($api['data'])) {
          foreach($api['data'] as $dt) {
            if(substr_count(strtolower($dt->accountNo),'reguler') > 0) {
                $data[$dt->productCode] = $dt->accountNo;
            }  
          }   
        } 

        return $data;
    }

    public function product_switching(Request $request) {
       try
        {
            $data = [];
            $product  = Product::where([['m_products.is_active', 'Yes'],['allow_switching',true],['issuer_id',$request->issuer_id]])->orderBy('product_name', 'asc');  
            if(!empty($request->product_id))
            {
                $product->where('product_id', '<>', $request->product_id);
            }  

           foreach ($product->get() as $val) {
                $tmp = [];
                $price = Price::where([['product_id', $val->product_id],['is_active', 'Yes']])->orderBy('price_date', 'DESC')->limit(1)->first();
                $price_value = !empty($price->price_value) ? $price->price_value : 0;

                $tmp['product_id'] = $val['product_id'];
                $tmp['product_name'] = strtoupper($val['product_name']);
                $tmp['product_price'] = number_format($price_value,2);
                $tmp['product_max_switch_in'] = $val['max_switch_in'];
                $tmp['product_max_switch_out'] = $val['max_switch_out'];
                $tmp['product_min_switch_in'] = $val['min_switch_in'];
                $tmp['product_min_switch_out'] = $val['min_switch_out'];

                $data[] = $tmp;
           }

           return $this->app_response('Product Switching', $data);               
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

      public function product_detail(Request $request) {
       try
        {
           $product  = Product::where([['m_products.is_active', 'Yes'], ['product_id', $request->product_id]])->first();                           
           return $this->app_response('Product detail', $product);               
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function get_product_switching($product_id=null)
    {
        try
        {
            $price = Price::where([['product_id', $product_id],['is_active', 'Yes']])->orderBy('price_date', 'DESC')->limit(1)->first();
            $data =  Product::select('m_products.product_id','m_products.product_name', 'b.asset_class_name', 'd.issuer_name', 'd.issuer_logo', 'e.price_value', 'f.return_1day','f.return_1year', 'g.standard_deviation', 'h.account_no')
                    ->leftJoin('m_asset_class as b', function($qry) { return $qry->on('m_products.asset_class_id', '=', 'b.asset_class_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_asset_categories as c', function($qry) { return $qry->on('b.asset_category_id', '=', 'c.asset_category_id')->where('c.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as d', function($qry) { return $qry->on('m_products.issuer_id', '=', 'd.issuer_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_products_prices as e', function($qry) { return $qry->on('m_products.product_id', '=', 'e.product_id')->where('e.is_active', 'Yes'); })
                    ->leftJoin('m_products_period as f', function($qry) { return $qry->on('m_products.product_id', '=', 'f.product_id')->where('f.is_active', 'Yes'); })
                    ->leftJoin('t_products_scores as g', function($qry) { return $qry->on('m_products.product_id', '=', 'g.product_id')->where('g.is_active', 'Yes'); })
                    ->leftJoin('t_assets_outstanding as h', function($qry) { return $qry->on('m_products.product_id', '=', 'h.product_id')->where('h.is_active', 'Yes'); })
                    // ->where([['m_products.is_active', 'Yes'], ['e.price_date', date('Y-m-d', strtotime('-1 day'))], ['f.period_date', $this->app_date()], ['g.score_date', $this->app_date()]])
                    //->where([['m_products.is_active', 'Yes'], ['m_products.product_id', $product_id], ['e.product_id', $price->product_id]])
                    ->where([['m_products.is_active', 'Yes'], ['m_products.product_id', $product_id]])                    
                    ->first();
            return $this->app_response('Product Switching', $data);               
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save_switching(Request $request)
    {  
        try
        {   
            $product_switch_in  = $request->product_code_to;
            $product_switch_out =  $request->product_id;    
            $account_no = !empty($request->account_no) ? $request->account_no : null ;
            $account_no_to = !empty($request->account_no_to) ? $request->account_no_to : null ;
            $investor_account_id = $request->investor_account_id;
            $otp_input = !empty($request->otp_input) ? $request->otp_input : '';
            $fee_unit = !empty($request->fee_unit) ? $request->fee_unit : 0;
            $unit = !empty($request->unit) ? $request->unit : 0;
            $net_amount = !empty($request->net_amount) ? $request->net_amount : 0;
            $switch_in_amount = !empty($request->switch_in_amount) ? $request->switch_in_amount : 0;
            $switch_out_amount = !empty($request->switch_out_amount) ? $request->switch_out_amount : 0;
            $percentage = !empty($request->fee_percent) ? $request->fee_percent : 0;
            $fee_amount = !empty($request->fee_amount) ? $request->fee_amount : 0;
            $tax_amount = !empty($request->tax_amount) ? $request->tax_amount : 0;
            $portfolio_id = !empty($request->portfolio_id) ? $request->portfolio_id : '';

            $manager     = $this->db_manager($request);
            $cif         = !empty($request->cif) ? $request->cif : $this->auth_user()->cif;
            $inv_id      = !empty($request->investor_id) ? $request->investor_id : Auth::id();

            $trans_history_id = [];
            $data_switch = [];

            $rst        = $this->cut_of_time($product_switch_in,'array');
            $transaction_date =  !empty($rst['transaction_date_allocation']) ? $rst['transaction_date_allocation'] : $this->app_date() ;

            $sn         = substr($cif, -5) . date('ymd',strtotime($transaction_date));
            $qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $transaction_date], ['is_active', 'Yes'],])->whereNotNull('reference_no')->orderBy('reference_no', 'desc')->first();
            $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
            $ref_no     = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);

            /* start forward to wms */
            $wms_product_switch_out_code = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_switch_out]])->first();    
             // return $this->app_response('xx', $wms_product_switch_out_code);
            $wms_product_switch_in_code = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_switch_in]])->first();  
            $wms_account_number = Account::where([['investor_account_id', $investor_account_id],['is_active','Yes']])->first();
            $wms_account_number = !empty($wms_account_number) ? $wms_account_number->account_no : null;  
            $wms_sales_code     = Investor::select('user_code')
                                  ->join('u_users as u', 'u.user_id', '=', 'u_investors.sales_id')
                                  ->where([['u_investors.is_active', 'Yes'], ['u.is_active', 'Yes'], ['investor_id',  $inv_id]])->first();

            //tambah api untuk waperted 
            $wms    = $this->api_ws(['sn' => 'SalesWaperd', 'val' => [$wms_sales_code->user_code]])->original;
            
            // $sales_wap  = UserSalesDetail::where([['is_active', 'Yes'], ['user_id', $wms->agentCode]])->whereNotNull('agent_waperd_no')->orWhereNotNull('agent_waperd_expdate')->whereDate('agent_waperd_expdate', '>=',  $this->app_date())->first(); 

            if(!empty($wms['data']->agentCode) && !empty($wms['data']->agentWaperdExpDate) && $wms['data']->agentWaperdExpDate >  $this->app_date() && !empty($wms['data']->agentWaperdNo))
            {
                $sales_wap  = $wms['data']->agentCode;
            }else
            {
                $sales_wap  = $wms['data']->dummyAgentCode;
            }


            // $wmw_product_price  = Price::where([['product_id', $product_switch_out], ['price_date', '<=', $this->app_date()], ['is_active', 'Yes']])->orderBy('price_date', 'DESC')->limit(1)->first();            

            //product in
            $product_code   = !empty($wms_product_switch_out_code->ext_code) ? $wms_product_switch_out_code->ext_code : '';  

            //product to
            $product_code_to   = !empty($wms_product_switch_in_code->ext_code) ? $wms_product_switch_in_code->ext_code : '';

            //ambil acountno
            $account_sub = $this->get_account_sub_regular($cif); 
            $account_no_sub = !empty($account_sub[$product_code]) ? $account_sub[$product_code] : null;    

//untuk mendapatkan data testing
//              $data_swt = [[
//                       //'InvAccountNo'  => 'NEW8307148312',
//                       'InvAccountNo'  => '83442894 12 REGULER',  
//                       'IsRedeemAll'   => false,
//                       'NetAmount'     => 0,
//                       'Units'         => 7]
//                     ];

//             $api_feetax = $this->api_ws(['sn' => 'FeeTaxAdapterSWT', 'val' => ['83442894', '7138878672', 'MPUS', 'MITRAS', 'SWT', $data_swt]]);
            
//             $wmw_product_price = '';
//             if (!empty($api_feetax->original['message']))
//             {
//                 if(!empty($api_feetax->original['message']->Result) && !empty($api_feetax->original['message']->Result->_TaxFeeListOut))
//                 {
//                     $wmw_product_price = $api_feetax->original['message']->Result->_TaxFeeListOut;
//               }
//             }

// return $wmw_product_price[0]->Amount;
            $wmw_product_price = 0;
            $data_swt = [[
                      //'InvAccountNo'  => 'NEW8307148312',
                      'InvAccountNo'  => $account_no_sub,  
                      'IsRedeemAll'   => false,
                      'NetAmount'     => 0,
                      'Units'         => floatval($unit)]
                    ];

            $api_feetax = $this->api_ws(['sn' => 'FeeTaxAdapterSWT', 'val' => [$cif, $wms_account_number, $product_code, $product_code_to, 'SWT', $data_swt]]);
            if (!empty($api_feetax->original['message']))
            {
                if(!empty($api_feetax->original['message']->Result) && !empty($api_feetax->original['message']->Result->_TaxFeeListOut))
                {
                    //$wmw_product_price = round(floatval($wmw_product_price[0]->Amount),2);
                    $price_feetax = $api_feetax->original['message']->Result;
                    $wmw_product_price = $price_feetax->NAVPerUnit;
                }else{
                    $wmw_product_price = 0;
                }
            }else{
                $wmw_product_price = 0;
            }


            // return $this->app_response('xxxx', $wmw_product_price);
            //1. customerIdentityNo
            $dataWMS[] = $cif; 
            //2. transactionDate
            $dataWMS[] = $transaction_date;
            //3. SecondLegProductCode
            $dataWMS[] = $transaction_date;            
            //4. productCode
            $dataWMS[] = !empty($wms_product_switch_out_code->product_code) ? $wms_product_switch_out_code->product_code : '';            
            //5. SecondLegProductCode
            $dataWMS[] = !empty($wms_product_switch_in_code->product_code) ? $wms_product_switch_in_code->product_code : '' ;            
            //6. Price
            // $dataWMS[] = !empty($net_amount) ? (float) ($net_amount) : 0;
            $dataWMS[] = $wmw_product_price;
            //7. Amount
            $dataWMS[] = (float) $unit;     
            //8. Promos
            $dataWMS[] = null;
            //9. Fees
            $dataWMS[] = [['code'   => 'DirectAmount', 'amount' => floatval($fee_amount), 'isSecondLeg' => false]];                       
            //10. customerAccountNo
            $dataWMS[] = $wms_account_number;    
            //11. charges
            $dataWMS[] = 0;
            //12. portfolioNo
            $dataWMS[] = $account_no;   
            //13. counterPartyCode   
            //if ambil dari agent code else ambil dummy agent code dari api 
            $dataWMS[] = $sales_wap;
            //14. isAdvice
            $dataWMS[] = false;            
            //15. remark 
            $dataWMS[] = "000";
            //16. refferenceNo
            $dataWMS[] = $ref_no;
            //17. entryBy
            $dataWMS[] = $manager->user;            
            //18. entryHost
            $dataWMS[] = $manager->ip ;            
            // return $dataWMS;
            // return ($dataWMS);
            $api = $this->api_ws(['sn' => 'TransactionWMSSwitch', 'val' => $dataWMS])->original;
            //return $api;  
            /* end forward to wms*/

            if(!empty($api['message']->isOk) && $api['message']->isOk == true) {
                /*
                $rst        = $this->cut_of_time($product_switch_in,'array');
                $transaction_date =  !empty($rst['transaction_date_allocation']) ? $rst['transaction_date_allocation'] : $this->app_date() ;
                */

                //start switch out
                $sw_out_trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
                $sw_out_type_ref   = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'SWTOT']]  ], 'SA\Transaction\Reference')->original['data'];

                /*
                $sn         = substr($cif, -5) . date('ymd',strtotime($transaction_date));
                $qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $transaction_date], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                $ref_no     = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);
                */

                $data   = [];
                $data   = ['product_id'           => $product_switch_out,
                           'investor_id'          => $inv_id,
                           'trans_reference_id'   => $sw_out_trans_ref,
                           'type_reference_id'    => $sw_out_type_ref,
                           'transaction_date'     => $transaction_date,
                           'reference_no'         => $ref_no, 
                           'portfolio_id'         => $portfolio_id,
                           'account_no'           => $account_no,
                           'unit'                 => $unit,
                           'percentage'           => $percentage,   
                           //'net_amount'           => $net_amount,   
                           //'amount'               => $switch_out_amount,   
                           'net_amount'           => 0,   
                           'amount'               => 0,   
                           'fee_amount'           => $fee_amount,
                           'tax_amount'           => $tax_amount,
                           'investor_account_id'  => $investor_account_id,
                           'send_wms'             => true,
                           'guid'                 => !empty($api['message']->data) ? $api['message']->data : null, 
                           'fee_unit'             => $fee_unit,                               
                           'created_by'           => $manager->user,
                           'created_host'         => $manager->ip  
                        ];
                $trans_hist_return = TransactionHistory::create($data);
                $trans_history_id[] = $trans_hist_return->trans_history_id;                              
                $data_switch['switch_out'] = $data;

                $account_sub = $this->get_account_sub($cif);  
                //$account_freeze =!empty($account_sub[$product_switch_out]) ? $account_sub[$product_switch_out] : $account_no;
                $account_freeze = $account_no;
                $portfolio_id_freeze = !empty($portfolio_id) ? $portfolio_id : null;

                $freeze =  AssetFreeze::where([['investor_id',  $inv_id], ['product_id', $product_switch_out], ['portfolio_id', $portfolio_id_freeze], ['account_no', $account_freeze]])->first();


                $act_freez  = empty($freeze->asset_freeze_id) ? 'cre' : 'upd';
                $redeem_freeze_unit = 0;
                $unit_redeem_freeze = !empty($unit) ? $unit : 0;
                if($unit_redeem_freeze > 0)
                {
                    if(!empty($freeze->freeze_unit))
                    {
                        $redeem_freeze_unit =  $freeze->freeze_unit + $unit_redeem_freeze;
                    }else{
                         $redeem_freeze_unit = $unit_redeem_freeze;
                    }
                }

                $data_freez = [
                    'investor_id'           => $inv_id,
                    'product_id'            => $product_switch_out,
                    'portfolio_id'          => $portfolio_id_freeze,
                    //'account_no'            => (!empty($account_sub[$product_switch_out]) ? $account_sub[$product_switch_out] : $account_no),
                    'account_no'            => $account_no,
                    $act_freez.'ated_by'    => 'System',
                    $act_freez.'ated_host'  => '::1'
                ];
                if(!empty($unit_redeem_freeze))
                {
                   $data_freez=  array_merge($data_freez, ['freeze_unit' => $redeem_freeze_unit]);
                }

                $freeze = (empty($freeze->asset_freeze_id)) ? AssetFreeze::create($data_freez) : AssetFreeze::where('asset_freeze_id', $freeze->asset_freeze_id)->update($data_freez);
                 
                //end switch out

                //start switch in
                $sw_in_trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
                $sw_in_type_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'SWTIN']]], 'SA\Transaction\Reference')->original['data'];

                $product_switch_in_code = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_switch_in]])->first();    
                $account_sub_switch_in = $this->get_account_sub($cif);  

                $data   = [];
                $data   = ['product_id'           => $product_switch_in,
                           'investor_id'          => $inv_id,
                           'trans_reference_id'   => $sw_in_trans_ref,
                           'type_reference_id'    => $sw_in_type_ref,
                           'transaction_date'     => $transaction_date,
                           'reference_no'         => $ref_no,
                           'portfolio_id'         => $portfolio_id,
                           //'account_no'           => !empty($account_sub_switch_in[$product_switch_in_code->product_code]) ? $account_sub_switch_in[$product_switch_in_code->product_code] : null,
                           'account_no'           => $account_no_to,
                           //'unit'                 => $unit,
                           'unit'                 => 0,                           
                           'percentage'           => 0,
                           //'net_amount'           => $switch_in_amount,   
                           //'amount'               => $switch_in_amount,   
                           'net_amount'           => 0,   
                           'amount'               => 0,   
                           'fee_amount'           => 0,
                           'tax_amount'           => 0,
                           'investor_account_id'  => $investor_account_id,
                           'send_wms'             => true,
                           'guid'                 => !empty($api['message']->data) ? $api['message']->data : null, 
                           'fee_unit'             => $fee_unit,                               
                           'created_by'           => $manager->user,
                           'created_host'         => $manager->ip  
                        ];
                $trans_hist_return = TransactionHistory::create($data);
                $trans_history_id[] = $trans_hist_return->trans_history_id;                  
                $data_switch['switch_in'] = $data;
                //end switch in

                //start reset otp
                $trans_history_implode = implode('~', $trans_history_id);
                TransactionOtp::where(['investor_id' => $inv_id,'otp' => $otp_input,'is_active' => 'Yes'])->update(['is_valid' => 'Yes','trans_history_id' => $trans_history_implode]);                
                //end reset otp

                //start notif email dan sms
                $product_switching_out  = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_switch_out]])->first();
                $product_switching_out = !empty($product_switching_out->product_code) ? $product_switching_out->product_code : '';
                $product_switching_in  = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_switch_in]])->first();
                $product_switching_in = !empty($product_switching_in->product_code) ? $product_switching_in->product_code : '';

                $sms_switch_content = [$product_switching_out,$unit,$product_switching_in,$unit];
                $investor_mobile_phone  = Investor::select('mobile_phone')->where([['u_investors.is_active', 'Yes'],['investor_id',  $inv_id]])->first();
                $conf    = MobileContent::where([['mobile_content_name', 'TransactionSwitching'], ['is_active', 'Yes']])->first();
                $msg     = !empty($conf->mobile_content_text) ? str_replace(['{switch_out_product}','{switch_out_unit}','{switch_in_product}','{switch_in_unit}'], $sms_switch_content, $conf->mobile_content_text) : '';

                $api_sms = $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$investor_mobile_phone->mobile_phone, $msg]]); 
                if(!empty($api_sms->original['code']) && $api_sms->original['code'] == 200) {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_sms' => 'Yes']);
                   $data_switch['notif_send_sms'] = 'Yes';
                } else {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_sms' => 'No']);                                             
                   $data_switch['notif_send_sms'] = 'No';
                }

                $sendEmailNotification = new MessagesController;
                $api_email = $sendEmailNotification->transaction_switching($trans_history_id[0],$trans_history_id[1]);

                if(!empty($api_email->original['success']) && $api_email->original['success'] == 1) {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_email' => 'Yes']);  
                   $data_switch['notif_send_email'] = 'Yes';
                } else {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_email' => 'No']);                                               
                   $data_switch['notif_send_email'] = 'No';
                }                    
                //end notif email dan sms    

                return $this->app_partials(2, 0, ['data' => $data_switch, 'session_forget' => 'switching_checkout']);
            } else {
                  $data['send_notication_email'] = null;
                  $data['send_notication_sms']   = null;
                  $data['status_forward_to_wms'] = 'failed';   
                  $data['forward_to_wms_respon'] = $api;      

                  return $this->app_partials(0, 1, ['data' => $data]);    
            }                        

        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function summary()
    {
        try
        {
            $data   = DB::table('t_trans_histories as tth')
                    ->leftJoin('m_trans_reference as tr', function($qry) { return $qry->on('tr.trans_reference_id', 'tth.trans_reference_id')->where([['tr.reference_type', 'Transaction Status'], ['tr.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as tp', function($qry) { return $qry->on('tp.trans_reference_id', 'tth.type_reference_id')->where([['tp.reference_type', 'Transaction Type'], ['tp.is_active', 'Yes']]); })
                    ->where('tth.is_active', 'Yes')
                    ->whereIn('tp.reference_code', ['SUB', 'TOPUP', 'RED'])
                    ->select('tth.transaction_date', 'tr.reference_name as status_name', 'tp.reference_name as type_name', 'tr.reference_color')
                    ->groupBy('tth.transaction_date', 'tr.reference_name', 'tp.reference_name', 'tr.reference_color');

            if ($this->auth_user()->usercategory_name == 'Investor')
            {
                $data->join('m_products as mp', 'mp.product_id', 'tth.product_id')
                    ->leftJoin('m_asset_class as mac', function($qry) { return $qry->on('mac.asset_class_id', 'mp.asset_class_id')->where('mac.is_active', 'Yes'); })
                    ->where([['mp.is_active', 'Yes'], ['tth.investor_id', $this->auth_user()->id]])
                    ->whereIn('tth.investor_id', function($query) {
                        $query->select('investor_id')
                            ->from('u_investors')
                            ->where('is_active', 'Yes');
                    })
                    ->addSelect('tth.portfolio_id', 'mp.product_name', 'mac.asset_class_name', DB::raw('SUM(tth.amount) as total_amount'))
                    ->groupBy('tth.portfolio_id', 'mp.product_name', 'mac.asset_class_name');                
            }
            else
            {
                $data->join('u_investors as ui', 'ui.investor_id', 'tth.investor_id')
                    ->where('ui.is_active', 'Yes')
                    ->addSelect('tth.reference_no', 'ui.fullname', 'ui.photo_profile', DB::raw('SUM(tth.amount) as total_amount'))
                    ->groupBy('tth.reference_no', 'ui.fullname', 'ui.photo_profile');
                if ($this->auth_user()->usercategory_name == 'Sales')
                {
                    $data->where('ui.sales_id', $this->auth_user()->id);
                }
            }

            return $this->app_response('Transaction Summary', $data->limit(5)->get());
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function portfolio_balance(Request $request)
    {
        try
        {
            $userId = $this->auth_user()->id;
            $cName  = $this->auth_user()->usercategory_name;
            $dates  = $this->getOutstandingDatesByRole($request, $cName, $userId);
            $res    = [];

            if (!empty($dates)) {                
                $goals = DB::table('t_trans_histories_days as thd')
                        ->join('u_investors as ui', 'ui.investor_id', 'thd.investor_id')
                        ->whereIn('thd.history_date', $dates)
                        ->where('thd.is_active', 'Yes')
                        ->where('ui.is_active', 'Yes')
                        ->whereRaw("LEFT(thd.portfolio_id, 1) = '2'")
                        ->orderBy('thd.history_date');
                
                if ($cName === 'Investor')
                {
                    $placeholders = implode(',', array_fill(0, count($dates), '?'));
                    $bindings = array_merge([$userId], $dates);
                    $nonGoals = DB::select("
                        SELECT account_no, outstanding_date, product_id, product_name, asset_class_id, 
                                asset_class_name, asset_class_color, 1 as portfolio, SUM(balance_amount) AS balance
                        FROM (
                            SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                                    tao.account_no, tao.outstanding_date, mp.product_id, mp.product_name, 
                                    mac.asset_class_id, mac.asset_class_name, mac.asset_class_color, tao.balance_amount
                            FROM t_assets_outstanding tao
                            JOIN u_investors ui ON tao.investor_id = ui.investor_id
                            JOIN m_products mp ON tao.product_id = mp.product_id
                            LEFT JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id AND mac.is_active = 'Yes'
                            WHERE tao.investor_id = ?
                                AND tao.outstanding_date IN ($placeholders)
                                AND tao.is_active = 'Yes'
                                AND ui.is_active = 'Yes'
                                AND mp.is_active = 'Yes'
                            ORDER BY tao.investor_id, tao.account_no, tao.product_id, tao.data_date DESC, tao.outstanding_id DESC
                        ) AS assets
                        GROUP BY account_no, outstanding_date, product_id, product_name, 
                                asset_class_id, asset_class_name, asset_class_color
                    ", $bindings);

                    $goals = $goals->join('m_products as mp', 'mp.product_id', 'thd.product_id')
                            ->leftJoin('m_asset_class as mac', function($join) { 
                                $join->on('mac.asset_class_id', '=', 'mp.asset_class_id')->where('mac.is_active', 'Yes'); 
                            })
                            ->where('ui.investor_id', $userId)
                            ->where('mp.is_active', 'Yes')
                            ->groupBy(
                                'thd.history_date', 'thd.account_no', 'mp.product_id', 'mp.product_name', 
                                'mac.asset_class_id', 'mac.asset_class_name', 'mac.asset_class_color'
                            )
                            ->select(
                                'thd.history_date', 'thd.account_no', 'mp.product_id', 'mp.product_name', 
                                'mac.asset_class_id', 'mac.asset_class_name', 'mac.asset_class_color', 
                                DB::raw('2 as portfolio'), DB::raw('SUM(thd.current_balance) as balance')
                            )
                            ->get();

                    $nonMap = [];
                    foreach ($nonGoals as $dt) {
                        $nonMap[$dt->outstanding_date]['non_goals']['product'][] = [
                            'product_name' => $dt->product_name,
                            'balance_amount' => floatval($dt->balance),
                            'account_no' => $dt->account_no,
                            'asset_class_name' => $dt->asset_class_name,
                            'asset_class_color' => $dt->asset_class_color
                        ];
                    }
        
                    $goalMap = [];
                    foreach ($goals as $dt) {
                        $goalMap[$dt->history_date]['goals']['product'][] = [
                            'product_name' => $dt->product_name,
                            'balance_amount' => floatval($dt->balance),
                            'account_no' => $dt->account_no,
                            'asset_class_name' => $dt->asset_class_name,
                            'asset_class_color' => $dt->asset_class_color
                        ];
                    }
        
                    $allDates = array_unique(array_merge(array_keys($nonMap), array_keys($goalMap)));
        
                    foreach ($allDates as $date) {
                        $hasNonGoals = isset($nonMap[$date]['non_goals']['product']);
                        $hasGoals = isset($goalMap[$date]['goals']['product']);
        
                        $nonGoalsData = $hasNonGoals ? $nonMap[$date]['non_goals']['product'] : [];
                        $goalsData = $hasGoals ? $goalMap[$date]['goals']['product'] : [];
        
                        $goalsGrouped = collect($goalsData)->groupBy('product_name')->map(function ($items) {
                            return $items->sum('balance_amount');
                        });
        
                        $nonFiltered = [];
                        foreach ($nonGoalsData as $item) {
                            $productName = $item['product_name'];
                            $amount = $item['balance_amount'];
        
                            if (isset($goalsGrouped[$productName])) {
                                $amount -= $goalsGrouped[$productName];
                                unset($goalsGrouped[$productName]);
                            }
        
                            if ($amount > 0) {
                                $nonFiltered[] = [
                                    'product_name' => $item['product_name'],
                                    'balance_amount' => $amount,
                                    'account_no' => $item['account_no'],
                                    'asset_class_name' => $item['asset_class_name'],
                                    'asset_class_color' => $item['asset_class_color']
                                ];
                            }
                        }
        
                        if (empty($nonFiltered) && empty($goalsData)) {
                            continue;
                        }
        
                        $res[$date] = [];
        
                        $assets = [];
                        $balance = 0;
                        foreach ($nonFiltered as $nf) {
                            $balance += $nf['balance_amount'];
                            $acn = $nf['asset_class_name'];
                            if (!isset($assets[$acn])) {
                                $assets[$acn] = [
                                    'name' => $acn,
                                    'amount' => $nf['balance_amount'],
                                    'color' => $nf['asset_class_color']
                                ];
                            } else {
                                $assets[$acn]['amount'] += $nf['balance_amount'];
                            }
                        }
        
                        $res[$date]['non_goals'] = $balance > 0 ? [
                            'balance' => $balance,
                            'asset' => $assets,
                            'product' => array_map(function ($p) {
                                unset($p['asset_class_name'], $p['asset_class_color']);
                                return $p;
                            }, $nonFiltered)
                        ] : [];
        
                        if ($hasGoals) {
                            $goalBalance = 0;
                            $goalAssets = [];
                            $goalProducts = [];
        
                            foreach ($goalsData as $g) {
                                $goalBalance += $g['balance_amount'];
                                $acn = $g['asset_class_name'];
                                if (!isset($goalAssets[$acn])) {
                                    $goalAssets[$acn] = [
                                        'name' => $acn,
                                        'amount' => $g['balance_amount'],
                                        'color' => $g['asset_class_color']
                                    ];
                                } else {
                                    $goalAssets[$acn]['amount'] += $g['balance_amount'];
                                }
        
                                $goalProducts[] = [
                                    'product_name' => $g['product_name'],
                                    'balance_amount' => $g['balance_amount'],
                                    'account_no' => $g['account_no']
                                ];
                            }
        
                            $res[$date]['goals'] = [
                                'balance' => $goalBalance,
                                'asset' => $goalAssets,
                                'product' => $goalProducts
                            ];
                        } else {
                            $res[$date]['goals'] = [];
                        }
                    }
                } else {
                    $whereSales = '';
                    $bindings = [];
                    
                    if ($cName === 'Sales') {
                        $whereSales = 'AND ui.sales_id = ?';
                        $bindings[] = $userId;
                        $goals->where('ui.sales_id', $userId);
                    }

                    $placeholders = implode(',', array_fill(0, count($dates), '?'));
                    $bindings = array_merge($dates, $bindings);

                    $sql = "
                        SELECT outstanding_date, SUM(balance_amount) AS non_goals
                        FROM (
                            SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                                tao.outstanding_date, tao.balance_amount
                            FROM t_assets_outstanding tao
                            JOIN u_investors ui ON tao.investor_id = ui.investor_id
                            JOIN m_products mp ON tao.product_id = mp.product_id
                            JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id
                            JOIN m_asset_categories mact ON mac.asset_category_id = mact.asset_category_id
                            WHERE tao.outstanding_date IN ($placeholders)
                                AND tao.is_active = 'Yes'
                                AND ui.is_active = 'Yes'
                                AND mp.is_active = 'Yes'
                                AND mac.is_active = 'Yes'
                                AND mact.is_active = 'Yes'
                                $whereSales
                            ORDER BY tao.investor_id, tao.account_no, tao.product_id, tao.data_date DESC, tao.outstanding_id DESC
                        ) AS assets
                        GROUP BY outstanding_date
                    ";

                    $nonGoalsQry = DB::select($sql, $bindings);
                    $nonGoals = collect($nonGoalsQry)->pluck('non_goals', 'outstanding_date')->toArray();

                    $goals = $goals->groupBy('thd.history_date')
                            ->select('thd.history_date', DB::raw("SUM(thd.current_balance)::float as goals"))
                            ->pluck('goals', 'thd.history_date')
                            ->toArray();

                    $allDates = array_unique(array_merge(array_keys($nonGoals), array_keys($goals)));

                    foreach ($allDates as $date) {
                        $non = isset($nonGoals[$date]) ? floatval($nonGoals[$date]) : 0;
                        $goal = isset($goals[$date]) ? floatval($goals[$date]) : 0;
                
                        // Kurangi non_goals jika ada goals
                        $adjustedNon = max($non - $goal, 0);
                
                        if ($cName === 'Super Admin') {
                            // Format khusus untuk Super Admin: hanya total
                            $res[$date] = $adjustedNon + $goal;
                        } else {
                            // Format default: goals dan non_goals
                            $res[$date] = [
                                'non_goals' => $adjustedNon,
                                'goals' => $goal
                            ];
                        }
                    }
                }
            }
            return $this->app_response('Portfolio Balance', $res);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function getOutstandingDatesByRole($request, $role, $userId)
    {
        if ($role === 'Sales') {
            $today = Carbon::now();
            $todayStr = $today->format('Y-m-d');
            $startOfYear = $today->copy()->startOfYear()->format('Y-m-d');
            $endOfPrevMonth = $today->copy()->subMonth()->endOfMonth()->format('Y-m-d');
            
            // Ambil investor aktif
            $investorIds = DB::table('u_investors')
                ->where('sales_id', $userId)
                ->where('is_active', 'Yes')
                ->pluck('investor_id');

            // Ambil tanggal terakhir dari setiap bulan SEBELUM bulan ini
            $dates = DB::table('t_assets_outstanding')
                ->select(DB::raw('MAX(outstanding_date) as latest_date'))
                ->whereIn('investor_id', $investorIds)
                ->where('is_active', 'Yes')
                ->whereBetween('outstanding_date', [$startOfYear, $endOfPrevMonth])
                ->groupBy(DB::raw("DATE_TRUNC('month', outstanding_date)"))
                ->orderBy(DB::raw("DATE_TRUNC('month', outstanding_date)"))
                ->pluck('latest_date')
                ->toArray();

            // Tambahkan tanggal hari ini untuk bulan ini
            $dates[] = $todayStr;

            return array_values(array_unique($dates));
        } else {
            $dates = [];
            $ymd = !empty($request->date) ? ' '. $request->date : '';
            $no = !empty($request->day) ? $request->day : 1;
            for ($i = ($no-1); $i >= 0; $i--) {
                $dates[] = date('Y-m-d', strtotime('-'. $i .' days '. $ymd));
            }
            return $dates;
        }

        return [];
    }
}
