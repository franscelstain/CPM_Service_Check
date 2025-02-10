<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\SA\Master\FinancialCheckUp\FinancialAsset;

class ReportController extends AppController
{
    public function trans_histories(Request $request)
    {
        try 
        {
            $search = trim($request->search);
            $user   = 'b.investor_id';
            $data   = TransactionHistory::select('g.reference_code','t_trans_histories.*', 'b.fullname', 'b.photo_profile', 'b.cif', 'c.product_name', 'd.asset_class_name', 'e.reference_name as status_name', 'f.reference_name as trans_reference_name', 'f.reference_code as trans_reference_code', 'f.reference_color', 'g.reference_name as type_reference_name', 'h.issuer_logo', 'i.account_no as bank_account_no', 'i.account_name as bank_account_name')
                    ->join('u_investors as b', 't_trans_histories.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_trans_histories.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { return $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as e', function($qry) { return $qry->on('t_trans_histories.status_reference_id', '=', 'e.trans_reference_id')->where([['e.reference_type', 'Goals Status'], ['e.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as f', function($qry) { return $qry->on('t_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')->where([['f.reference_type', 'Transaction Status'], ['f.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Transaction Type'], ['g.is_active', 'Yes']]); })
                    ->leftJoin('m_issuer as h', function($qry) { return $qry->on('c.issuer_id', '=', 'h.issuer_id')->where('h.is_active', 'Yes'); })
                    ->leftJoin('u_investors_accounts as i', function($qry) { return $qry->on('t_trans_histories.investor_account_id', '=', 'i.investor_account_id')->where('i.is_active', 'Yes'); })
                    ->where([['t_trans_histories.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->whereBetween('transaction_date', [$request->fromDate, $request->toDate])
                    //->whereIn('g.reference_code', $code)
                    ->orderBy('t_trans_histories.trans_history_id', 'desc');
            if ($search!='') $data = $data->WhereRaw("
                        (g.reference_code ilike '%$search%' or
                        f.reference_code  ilike '%$search%' or
                        reference_no ilike '%$search%' or
                        cif ilike '%$search%' or
                        fullname ilike '%$search%' or
                        product_name ilike '%$search%') ");
            $data = $data->get();

            return $this->app_response('ReportTransaction', $data);
        } catch(\Exception $e) {
            return $this->app_catch($e);
        }
    }

    private function cpm_date($date='', $fmt='Y-m-d')
    {
      if ($fmt == '.net')
      {
         preg_match('/([\d]{9})/', $date, $dt);
         return date('Y-m-d', $dt[0]);
      }
      else
      {
         return !empty($date) ? date($fmt, strtotime($date)) : date($fmt);
      }
    }

    public function report_header()
    {
        try {
            $data = DB::table('m_clients')
                    ->select('client_name', 'office_address', 'call_center', 'currency_name', 'symbol')
                    ->where([['m_clients.is_active', 'Yes']])
                    ->leftJoin('m_currency as mc', 'mc.currency_id', 'm_clients.currency_id')
                    ->first();
            $logo = DB::table('c_logo')
                    ->select('logo_color', 'logo_color_white', 'logo_white', 'logo_only')
                    ->where([['is_active', 'Yes']])
                    ->first(); 
            
            return $this->app_response('Report Header', ['company' => $data, 'logo' => $logo]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function report_invbalance(Request $request)
    {
        ini_set('max_execution_time', 1200);
       
        // $inv_id     = !empty($request->invid) ? $request->invid : Auth::id();
        $inv_id     =  $request->invid;
        
        $cpm_date =  $this->cpm_date(); 
        // $cpm_date = '2022-12-13'; //debug
        try {
            $data1 = DB::table('u_investors')->selectRaw("u_investors.investor_id, fullname, tao.outstanding_date, cif, account_no, tao.balance_amount as amount, tao.investment_amount,
                                                            asset_class_name, product_name, tao.total_subscription, asset_category_name, tao.outstanding_unit, tao.regular_payment")
                    ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
                    ->leftJoin('m_products as mp', 'mp.product_id', 'tao.product_id')
                    ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                    ->leftJoin('m_issuer as mi', 'mi.issuer_id', '=', 'mp.issuer_id') 
                    ->where([['u_investors.valid_account', 'Yes'],['u_investors.is_active', 'Yes'], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['mi.is_active', 'Yes'],
                                ['u_investors.investor_id', $inv_id],
                                ['tao.outstanding_date', $cpm_date],
                                ['tao.balance_amount', '>=', 1]<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\SA\Master\FinancialCheckUp\FinancialAsset;

class ReportController extends AppController
{
    public function trans_histories(Request $request)
    {
        try 
        {
            $search = trim($request->search);
            $user   = 'b.investor_id';
            $data   = TransactionHistory::select('g.reference_code','t_trans_histories.*', 'b.fullname', 'b.photo_profile', 'b.cif', 'c.product_name', 'd.asset_class_name', 'e.reference_name as status_name', 'f.reference_name as trans_reference_name', 'f.reference_code as trans_reference_code', 'f.reference_color', 'g.reference_name as type_reference_name', 'h.issuer_logo', 'i.account_no as bank_account_no', 'i.account_name as bank_account_name')
                    ->join('u_investors as b', 't_trans_histories.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_trans_histories.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { return $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as e', function($qry) { return $qry->on('t_trans_histories.status_reference_id', '=', 'e.trans_reference_id')->where([['e.reference_type', 'Goals Status'], ['e.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as f', function($qry) { return $qry->on('t_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')->where([['f.reference_type', 'Transaction Status'], ['f.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Transaction Type'], ['g.is_active', 'Yes']]); })
                    ->leftJoin('m_issuer as h', function($qry) { return $qry->on('c.issuer_id', '=', 'h.issuer_id')->where('h.is_active', 'Yes'); })
                    ->leftJoin('u_investors_accounts as i', function($qry) { return $qry->on('t_trans_histories.investor_account_id', '=', 'i.investor_account_id')->where('i.is_active', 'Yes'); })
                    ->where([['t_trans_histories.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->whereBetween('transaction_date', [$request->fromDate, $request->toDate])
                    //->whereIn('g.reference_code', $code)
                    ->orderBy('t_trans_histories.trans_history_id', 'desc');
            if ($search!='') $data = $data->WhereRaw("
                        (g.reference_code ilike '%$search%' or
                        f.reference_code  ilike '%$search%' or
                        reference_no ilike '%$search%' or
                        cif ilike '%$search%' or
                        fullname ilike '%$search%' or
                        product_name ilike '%$search%') ");
            $data = $data->get();

            return $this->app_response('ReportTransaction', $data);
        } catch(\Exception $e) {
            return $this->app_catch($e);
        }
    }

    private function cpm_date($date='', $fmt='Y-m-d')
    {
      if ($fmt == '.net')
      {
         preg_match('/([\d]{9})/', $date, $dt);
         return date('Y-m-d', $dt[0]);
      }
      else
      {
         return !empty($date) ? date($fmt, strtotime($date)) : date($fmt);
      }
    }

    public function report_header()
    {
        try {
            $data = DB::table('m_clients')
                    ->select('client_name', 'office_address', 'call_center', 'currency_name', 'symbol')
                    ->where([['m_clients.is_active', 'Yes']])
                    ->leftJoin('m_currency as mc', 'mc.currency_id', 'm_clients.currency_id')
                    ->first();
            $logo = DB::table('c_logo')
                    ->select('logo_color', 'logo_color_white', 'logo_white', 'logo_only')
                    ->where([['is_active', 'Yes']])
                    ->first(); 
            
            return $this->app_response('Report Header', ['company' => $data, 'logo' => $logo]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function report_invbalance(Request $request)
    {
        ini_set('max_execution_time', 1200);   
       
        // $inv_id     = !empty($request->invid) ? $request->invid : Auth::id();
        $inv_id     =  $request->invid;
        
        $cpm_date =  $this->cpm_date(); 
        // $cpm_date = '2022-12-13'; //debug
        try {
            $data1 = DB::table('u_investors')->selectRaw("u_investors.investor_id, fullname, tao.outstanding_date, cif, account_no, tao.balance_amount as amount, tao.investment_amount, 
                        asset_class_name, product_name, tao.total_subscription, asset_category_name, tao.outstanding_unit, tao.regular_payment,  tao.avg_unit_cost")
                    ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
                    ->leftJoin('m_products as mp', 'mp.product_id', 'tao.product_id')
                    ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                    ->leftJoin('m_issuer as mi', 'mi.issuer_id', '=', 'mp.issuer_id') 
                    ->where([['u_investors.valid_account', 'Yes'],['u_investors.is_active', 'Yes'], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['mi.is_active', 'Yes'],
                    ['u_investors.investor_id', $inv_id],
                    ['tao.outstanding_date', $cpm_date],
                    ['tao.balance_amount', '>=', 1]
                    ])
                    ->orderBy('mac.asset_class_name','asc')
                    ->get(); 

            $data2 = LiabilityOutstanding::select("liabilities_name", "account_id", "outstanding_balance as amount")
                                       ->where([['investor_id', $inv_id], ['is_active', 'Yes'], ['outstanding_date', $cpm_date]])->get();


            /*foreach ($data as $dt) {  
                $dt->amount = (float)$dt->amount;
                $dt->total_unit = (float)$dt->total_unit;
    
                switch (strtolower($dt->asset_category_name))
                {
                    case 'reksa dana':
                    case 'mutual fund':
                    case 'mutual funds':
                    case 'balance fund':
                    case 'balance funds':
                    case 'equity fund':
                    case 'equity funds':
                    case 'fixed income fund':
                    case 'fixed income funds':
                    case 'money market fund':
                    case 'money market funds':
                        $dt->classtype      = 'AmountReksadana';
                        break;
                    case 'bond':
                    case 'bonds':
                    case 'government bond':
                    case 'government bonds':
                        $dt->classtype      = 'AmountSukuk';
                        break;
                    case 'dpk':
                        $dt->classtype      = 'AmountGiro';
                        break;
                    case 'insurance':
                    case 'insurances':
                        $dt->classtype      = 'AmountBancas';
                        break;
                    case 'deposito':
                        $dt->classtype      = 'AmountDeposito';
                        break;
                    case 'saving':
                    case 'savings':
                        $dt->classtype      = 'AmountTabungan';
                        break;
                } 
            }*/

            return $this->app_response('Report', ['report1'=>$data1, 'report2'=>$data2] );
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function group_asset_class() {
        try {
            $data = FinancialAsset::selectRaw("m_financials_assets.asset_class_id, mac.asset_class_name, m_financials_assets.financial_id, mf.financial_name") 
                    ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'm_financials_assets.asset_class_id')
                    ->leftJoin('m_financials as mf', 'mf.financial_id', 'm_financials_assets.financial_id')
                    ->where([['m_financials_assets.is_active', 'Yes'], ['mac.is_active', 'Yes'], ['mf.is_active', 'Yes']]);
            $data = $data->get();

            return $this->app_response('AssetClass', $data );
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }     
    }      
   
}

                                ])
                    ->orderBy('mac.asset_class_name','asc')
                    ->get(); 

            $data2 = LiabilityOutstanding::select("liabilities_name", "account_id", "outstanding_balance as amount")
                                       ->where([['investor_id', $inv_id], ['is_active', 'Yes'], ['outstanding_date', $cpm_date]])->get();


            /*foreach ($data as $dt) {  
                $dt->amount = (float)$dt->amount;
                $dt->total_unit = (float)$dt->total_unit;
    
                switch (strtolower($dt->asset_category_name))
                {
                    case 'reksa dana':
                    case 'mutual fund':
                    case 'mutual funds':
                    case 'balance fund':
                    case 'balance funds':
                    case 'equity fund':
                    case 'equity funds':
                    case 'fixed income fund':
                    case 'fixed income funds':
                    case 'money market fund':
                    case 'money market funds':
                        $dt->classtype      = 'AmountReksadana';
                        break;
                    case 'bond':
                    case 'bonds':
                    case 'government bond':
                    case 'government bonds':
                        $dt->classtype      = 'AmountSukuk';
                        break;
                    case 'dpk':
                        $dt->classtype      = 'AmountGiro';
                        break;
                    case 'insurance':
                    case 'insurances':
                        $dt->classtype      = 'AmountBancas';
                        break;
                    case 'deposito':
                        $dt->classtype      = 'AmountDeposito';
                        break;
                    case 'saving':
                    case 'savings':
                        $dt->classtype      = 'AmountTabungan';
                        break;
                } 
            }*/

            return $this->app_response('Report', ['report1'=>$data1, 'report2'=>$data2] );
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function group_asset_class() {
        try {
            $data = FinancialAsset::selectRaw("m_financials_assets.asset_class_id, mac.asset_class_name, m_financials_assets.financial_id, mf.financial_name") 
                    ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'm_financials_assets.asset_class_id')
                    ->leftJoin('m_financials as mf', 'mf.financial_id', 'm_financials_assets.financial_id')
                    ->where([['m_financials_assets.is_active', 'Yes'], ['mac.is_active', 'Yes'], ['mf.is_active', 'Yes']]);
            $data = $data->get();

            return $this->app_response('AssetClass', $data );
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }     
    }      
   
}
