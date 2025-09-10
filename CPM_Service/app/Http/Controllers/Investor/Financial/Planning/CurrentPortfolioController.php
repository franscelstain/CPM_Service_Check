<?php

namespace App\Http\Controllers\Investor\Financial\Planning;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\Transaction\TransactionHistoryDay;
use Illuminate\Http\Request;
use Auth;

class CurrentPortfolioController extends AppController
{
    public $table = 'Financial\AssetOutstanding';
    
        public function index(Request $request, $id='')
    {
        try
        {
            $asset      = $account = $product = [];
            $total_amt  = $total_blc = $total_earnings = 0;
            $history_date = '';
            $inv_id   = !empty($id) ? $id : Auth::id();
            $app_date = !empty($this->app_date())? $this->app_date() : date('Y-m-d');
            $app_date_min_one_day = date( "Y-m-d", strtotime(date('Y-m-d')." -1 day"));
            $data = TransactionHistoryDay::select('t_trans_histories_days.*', 'c.product_name', 'c.product_code', 'd.asset_class_id', 'd.asset_class_name', 'd.asset_class_color', 'e.issuer_logo', 'f.symbol', 'p.price_value')
                        ->join('u_investors as b', 't_trans_histories_days.investor_id', '=', 'b.investor_id')
                        ->join('m_products as c', 't_trans_histories_days.product_id', '=', 'c.product_id')
                        ->leftJoin('m_products_prices as p', function($qry) { $qry->on('p.product_id', '=', 'c.product_id')->where('p.is_active', 'Yes'); })
                        ->leftJoin('m_asset_class as d', function($qry) { $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                        ->leftJoin('m_issuer as e', function($qry) { $qry->on('c.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                        ->leftJoin('m_currency as f', function($qry) { $qry->on('c.currency_id', '=', 'f.currency_id')->where('f.is_active', 'Yes'); })
                        ->where([['b.investor_id', $inv_id], ['t_trans_histories_days.history_date', $app_date], ['t_trans_histories_days.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes'], ['p.price_date', $app_date_min_one_day]])
                        ->where(function($qry) {
                            $qry->whereRaw("LEFT(t_trans_histories_days.portfolio_id, 1) NOT IN ('2', '3')")
                                ->orWhereNull('portfolio_id');
                        });

            if (!empty($request->search))
                $data = $data->where('c.product_name', 'ilike', '%'. $request->search .'%');
            if (!empty($request->asset_class_id))
                $data = $data->where('d.asset_class_id', $request->asset_class_id);
            if (!empty($request->balance_minimum))
                $data = $data->where('t_trans_histories_days.current_balance', '>=', $request->balance_minimum);
            if (!empty($request->balance_maximum))
                $data = $data->where('t_trans_histories_days.current_balance', '<=', $request->balance_maximum);
            
            $data = $data->orderBy('diversification_account', 'desc')->get();
            
            foreach ($data as $dt)
            {
                $id             = md5($dt->product_id . $dt->account_no);
                $product[$id]   = ['product_id'                 => $dt->product_id,
                                   'product_name'               => $dt->product_name,
                                   'asset_class_name'           => $dt->asset_class_name,
                                   'asset_class_color'          => $dt->asset_class_color,
                                   'account_no'                 => $dt->account_no,
                                   'issuer_logo'                => $dt->issuer_logo,
                                   'symbol'                     => $dt->symbol,
                                   'diversification_account'    => $dt->diversification_account,
                                   'current_balance'            => floatval($dt->current_balance),
                                   'unit'                       => floatval($dt->unit),
                                   'avg_unit'                   => floatval($dt->avg_nav),
                                   'investment_amount'          => floatval($dt->investment_amount),
                                   'earnings'                   => floatval($dt->earnings),
                                   'returns'                    => floatval($dt->returns),
                                   'history_date'               => $dt->history_date,
                                   'nav_per_unit'               => floatval($dt->price_value)
                                  ];
                
                if (in_array($dt->asset_class_id, array_keys($asset)))
                {
                    $asset[$dt->asset_class_id]['amount'] += $dt->current_balance;
                }
                else
                {
                    $asset[$dt->asset_class_id] = ['name' => $dt->asset_class_name, 'amount' => floatval($dt->current_balance), 'color' => $dt->asset_class_color];
                }
                
                $account[]       = $dt->account_no;
                $total_amt      += !empty($dt->investment_amount) ? $dt->investment_amount : 0;
                $total_blc      += !empty($dt->current_balance) ? $dt->current_balance : 0;
                $total_earnings += !empty($dt->earnings) ? $dt->earnings : 0;
                $history_date    = !empty($dt->history_date) ? $dt->history_date : date('Y-m-d');
            }
            
            $returns = $total_amt != 0 ? $total_earnings / $total_amt * 100 : 0; 
            
            return $this->app_response('Current Portfolio', ['account_no' => count($account), 'asset' => array_values($asset), 'balance' => $total_blc, 'earning' => $total_earnings, 'product' => array_values($product), 'returns' => $returns, 'history_date' => $history_date]);
         }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }  

    
    public function detail_for_sales(Request $request, $id)
    {
        return $this->index($request, $id);
    }

    public function current_portfolio(Request $request)
    {
        try
        {
            $user   = $this->auth_user()->usercategory_name == 'Investor' ? 'b.investor_id' : 'b.sales_id';
            $data   = AssetOutstanding::select('t_assets_outstanding.*', 'c.product_name', 'c.product_code', 'd.asset_class_id', 'd.asset_class_name', 'd.asset_class_color', 'e.issuer_logo')
                    ->join('u_investors as b', 't_assets_outstanding.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_issuer as e', function($qry) { $qry->on('c.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                    ->where([[$user, $this->auth_user()->id], ['t_assets_outstanding.outstanding_date', $this->app_date()], ['t_assets_outstanding.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']]);                 
            
            if (!empty($request->search))
                $data = $data->where('c.product_name', 'ilike', '%'. $request->search .'%');
            if (!empty($request->asset_class_id))
                $data = $data->where('d.asset_class_id', $request->asset_class_id);
            if (!empty($request->balance_minimum))
                $data = $data->where('balance_amount', '>=', $request->balance_minimum);
            if (!empty($request->balance_maximum))
                $data = $data->where('balance_amount', '<=', $request->balance_maximum);
            
            $data   = $data->distinct()->get();
            $total  = !empty($request->total) ? ['total' => $this->list_total($data)] : [];
            
            return $this->app_response('Current Portfolio', array_merge(['data' => $data], $total));
         }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function list_total($item)
    {
        $asset      = $account = $product = [];
        $balance    = $earning = $return = 0;
        foreach ($item as $i) 
        {
            if (in_array($i->asset_class_id, array_keys($asset)))
            {
                $asset[$i->asset_class_id]['amount'] += $i->balance_amount;
            }
            else
            {
                $asset[$i->asset_class_id] = ['name' => $i->asset_class_name, 'amount' => floatval($i->balance_amount), 'color' => $i->asset_class_color];
            }
            $product[] = ['product_name' => $i->product_name, 'balance_amount' => $i->balance_amount, 'account_no' => $i->account_no];
            $account[] = $i->account_no;
            $balance  += $i->balance_amount;
            $earning  += $i->return_amount;
        }
        return ['account_no' => count($account), 'asset' => array_values($asset), 'balance' => $balance, 'earning' => $earning, 'product' => $product, 'returns' => $return];
    }
}
