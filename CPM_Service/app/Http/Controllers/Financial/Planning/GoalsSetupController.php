<?php

namespace App\Http\Controllers\Financial\Planning;

use App\Models\Administrative\Mobile\MobileContent;
use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\Transaction\TransactionInstallment;
use App\Models\SA\Assets\AssetClass;
use App\Models\SA\Assets\Portfolio\AllocationWeight;
use App\Models\SA\Assets\Portfolio\AllocationWeightDetail;
use App\Models\SA\Assets\Portfolio\ModelMapping;
use App\Models\SA\Assets\Products\Fee;
use App\Models\Users\Investor\Account;
use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Assets\Products\Score;
use App\Models\SA\Assets\Portfolio\Models;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Master\ME\HistInflation;
use App\Models\SA\Reference\Goal;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Users\Investor\Investor;
use App\Models\Users\UserSalesDetail;
use App\Models\Financial\Planning\Goal\InvestmentDetail;
use App\Models\Transaction\TransactionOtp;
use App\Models\SA\Reference\KYC\Holiday;
use Illuminate\Http\Request;
use App\Http\Controllers\Administrative\Broker\MessagesController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use DB;

class GoalsSetupController extends AppController
{
    public $table = 'Financial\Planning\Goal\Investment';
    
    public function index(Request $request, $inv_id='')
    {
        try
        {
            $data   = $price = [];
            $limit  = !empty($request->limit) ? $request->limit : 10;
            $page   = !empty($request->page) ? $request->page : 1;
            $offset = ($page-1)*$limit;
            $inv_id = !empty($inv_id) ? $inv_id : $this->auth_user()->id;

            $goals  = Investment::select('t_goal_investment.*', 'b.model_name', 'd.goal_name', 'e.reference_code','e.reference_name', 'e.reference_color')
                    ->leftJoin('m_models as b', function($qry) { return $qry->on('t_goal_investment.model_id', '=', 'b.model_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_risk_profiles as c', function($qry) { return $qry->on('t_goal_investment.profile_id', '=', 'c.profile_id')->where('c.is_active', 'Yes'); })
                    ->leftJoin('m_goals as d', function($qry) {return $qry->on('t_goal_investment.goal_id', '=', 'd.goal_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as e', function($qry) { return $qry->on('t_goal_investment.status_id', '=', 'e.trans_reference_id')->where([['e.reference_type', 'Goals Status'], ['e.is_active', 'Yes']]); })
                    ->where([['t_goal_investment.investor_id', $inv_id], ['t_goal_investment.is_active', 'Yes']]);
            
            if (!empty($request->search))
            {
                $goals  = $goals->where(function($qry) use ($request) {
                            $qry->where('t_goal_investment.goal_invest_date', 'ilike', '%'. $request->search .'%')
                                ->orWhere('t_goal_investment.goal_title', 'ilike', '%'. $request->search .'%')
                                ->orWhere('t_goal_investment.portfolio_id', 'ilike', '%'. $request->search .'%');
                        });
            }
            if (!empty($request->goal))
                $goals = $goals->where('d.goal_id', $request->goal);
            if (!empty($request->status))
                $goals = $goals->where('e.trans_reference_id', $request->status);
            if (!empty($request->balance_minimum))
                $goals = $goals->where('t_goal_investment.total_amount', '>=', $request->balance_minimum);
            if (!empty($request->balance_maximum))
                $goals = $goals->where('t_goal_investment.total_amount', '<=', $request->balance_maximum);
            if (!empty($request->order))
            {
                $sort   = !empty($request->sort) ? $request->sort : 'asc';
                $goals  = $goals->orderBy($request->order, $sort);
            }

            $total = $goals->count();
            $goals = $goals->offset($offset)->limit($limit)->get();
            
            foreach ($goals as $dt)
            {
                $balance    = TransactionHistoryDay::join('m_products as b', 't_trans_histories_days.product_id', '=', 'b.product_id')
                            ->where([['investor_id', $dt->investor_id], ['portfolio_id', $dt->portfolio_id], ['history_date', $this->app_date()], ['t_trans_histories_days.is_active', 'Yes'], ['b.is_active', 'Yes']])
                            ->sum('current_balance');
                $prj_amt    = 0;
                
                if ($balance > 0)
                {
                    $d1         = new \DateTime($dt->goal_invest_date);
                    $d2         = new \DateTime(date('Y-m-d'));
                    $diff       = $d2->diff($d1);
                    $month      = 0;
                    $product    = InvestmentDetail::select('net_amount', 'expected_return_month', 'investment_type')
                                ->join('m_products as b', 't_goal_investment_detail.product_id', '=', 'b.product_id')
                                ->where([['goal_invest_id', $dt->goal_invest_id], ['t_goal_investment_detail.is_active', 'Yes'], ['b.is_active', 'Yes']])
                                ->get();

                    if ($diff->y > 0)
                        $month = $diff->m > 0 ? ($diff->y*12)+$diff->m : $diff->y*12;
                    else
                        $month = $diff->m;

                    $prj_amt = $month == 0 ? $dt->first_investment : 0;

                    foreach ($product as $prd)
                    {                    
                        if ($month > 0)
                        {
                            if ($prd->investment_type == 'Lumpsum')
                                $prj_amt += $prd->net_amount * pow(1 + $prd->expected_return_month, $month);
                            else
                                $prj_amt += $prd->expected_return_month > 0 ? (($prd->net_amount * (1 + $prd->expected_return_month)) * (pow(1 + $prd->expected_return_month, $month) - 1)) / $prd->expected_return_month : 0;
                        }
                    }
                }

                $data[] = [
                    'investment_id'         => $dt->goal_invest_id,
                    'goal_invest_id'        => $dt->goal_invest_id,
                    'investor_id'           => $dt->investor_id,
                    'goal_id'               => $dt->goal_id,
                    'profile_id'            => $dt->profile_id,
                    'model_id'              => $dt->model_id,
                    'status_id'             => $dt->status_id,
                    'portfolio_id'          => $dt->portfolio_id,
                    'goal_invest_date'      => $dt->goal_invest_date,
                    'goal_title'            => $dt->goal_title,
                    'today_amount'          => $dt->today_amount,
                    'time_horizon'          => $dt->time_horizon,
                    'investment_amount'     => $dt->investment_amount,
                    'projected_amount'      => $dt->projected_amount,
                    'total_return'          => $dt->total_return,
                    'future_amount'         => $dt->future_amount,
                    'first_investmen'       => $dt->first_investment,
                    'monthly_investment'    => $dt->monthly_investment,
                    'model_name'            => $dt->model_name,
                    'goal_name'             => $dt->goal_name,
                    'reference_code'        => $dt->reference_code,
                    'reference_name'        => $dt->reference_name,
                    'reference_color'       => $dt->reference_color,
                    'created_at'            => $dt->created_at,
                    'balance'               => floatval($balance),
                    'growth'                => floatval($prj_amt)
                ];
            }
            
            $total_data = $page*$limit;
           
            $paginate = [
                'current_page'  => $page,
                'data'          => $data,
                'from'          => $page > 1 ?  1 + (($page-1)* $limit) : 1,
                'per_page'      => $limit,
                'to'            => $total_data >= $total ? $total : $total_data,
                'total'         => $total
            ];

            return $this->app_response('Portfolio Goals', $paginate);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function backtest(Request $request)
    {
        try
        {
            $data   = $api = $weight = [];
            $mdl_id = !empty($request->model_id) ? $request->model_id : 0;
            $amt    = !empty($request->today_amount) ? $request->today_amount : 0;
            if (!empty($request->weight))
            {
                foreach ($request->weight as $w_key => $w_val)
                    $weight[intval($w_key)] = floatval($w_val);

                $api = $this->api_ws(['sn' => 'Backtest', 'key' => ['token'], 'val' => [$mdl_id, $amt, $weight]])->original['data'];
            }
            return $this->app_response('Goals Setup - Backtest', $api);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function calc_item($request)
    {
        $t_inf  = HistInflation::where('is_active', 'Yes')->whereNotNull('avg_inflation')->orderBy('year', 'desc')->first();
        $model  = Models::where([['model_id', $request->input('model_id')], ['is_active', 'Yes']])->first();
        return (object) [
            'inflation_avg'     => floatval($t_inf->avg_inflation),
            'inflation_year'    => $t_inf->year,
            'time_horizon'      => $request->input('time_horizon'),
            'time_horizon_mth'  => $request->input('time_horizon') * 12,
            'today_amount'      => $request->input('today_amount')
        ];
    }
    
    public function calculator(Request $request)
    {
        try
        {
            $product            = $projected_growth = [];
            $purchase_allow     = true;
            $first_investment   = $investment_amount = $projected_amount = $total_return = 0;
            $ct                 = $request->input('calc_type');
            $inv_type           = $request->investment_type;
            $model_id           = !empty($request->input('model_id')) ? $request->input('model_id') :  $this->getModelFromInvestor() ;
            $calc               = $this->calc_item($request);
            $future_amount      = $calc->today_amount*pow((1+($calc->inflation_avg/100)), $calc->time_horizon);
            $allocweight        = AllocationWeight::where([['model_id', $model_id], ['is_active', 'Yes']])->orderBy('effective_date', 'desc')->first();
            $expected_return    = !empty($allocweight->allocation_weight_id) ? floatval($allocweight->expected_return_year) : 0;
            if (!empty($allocweight->allocation_weight_id))
            {
                $dataWeight = AllocationWeightDetail::select('m_portfolio_allocations_weights_detail.weight', 'b.product_id', 'b.product_name', 'b.asset_class_id', 'b.product_type', 'b.min_buy', 'b.allow_sip', 'c.asset_class_name', 'd.expected_return_year', 'd.expected_return_month','d.expected_return_month_min', 'd.expected_return_month_max', 'd.sharpe_ratio', 'd.standard_deviation', 'e.issuer_logo')
                            ->join('m_products as b', 'm_portfolio_allocations_weights_detail.product_id', '=', 'b.product_id')
                            ->join('m_asset_class as c', 'b.asset_class_id', '=', 'c.asset_class_id')
                            ->leftJoin('t_products_scores as d', function($qry) { return $qry->on('b.product_id', '=', 'd.product_id')->where('d.is_active', 'Yes'); })
                            ->leftJoin('m_issuer as e', function($qry) { return $qry->on('b.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                            ->where([['m_portfolio_allocations_weights_detail.allocation_weight_id', $allocweight->allocation_weight_id], ['m_portfolio_allocations_weights_detail.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                            ->get();
            
                foreach ($dataWeight as $dw)
                {
                    $exp_rtn        = floatval($dw->expected_return_year);
                    $exp_rtn_mth    = floatval($dw->expected_return_month);
                    $future_value   = $future_amount * $dw->weight;
                    if ($dw->allow_sip && $inv_type == 'SIP')
                    {
                        $inv_amt    = ($future_value * $exp_rtn_mth) / ((1 + $exp_rtn_mth) * (pow(1 + $exp_rtn_mth, $calc->time_horizon_mth) - 1));
                        $total_inv  = $inv_amt * $calc->time_horizon_mth;
                    }
                    else
                    {
                        $inv_amt    = $future_value / pow(1 + $exp_rtn_mth, $calc->time_horizon_mth);
                        $total_inv  = $inv_amt;
                    }
                    $total_rtn      = $future_value - $total_inv;
                    $purch_allow    = $dw->min_buy < $inv_amt ? true : false;

                    if (!$purch_allow)
                        $purchase_allow = false;
                    
                    if (!empty($inv_amt))
                    {
                        $product[] = [
                            'master'                => Product::where([['is_active', 'Yes'], ['asset_class_id', $dw->asset_class_id]])->get(),
                            'asset_class_id'        => $dw->asset_class_id,
                            'asset_class_name'      => $dw->asset_class_name,
                            'expected_return_year'  => $exp_rtn,
                            'expected_return_month' => $exp_rtn_mth,
                            'expected_return_month_min' => $dw->expected_return_month_min,
                            'expected_return_month_max' => $dw->expected_return_month_max,
                            'future_value'          => $future_value,
                            'investment_amount'     => $inv_amt,
                            'investment_type'       => $dw->allow_sip && $inv_type == 'SIP' ? $dw->allow_sip ? 'SIP' : 'Lumpsum' : 'Lumpsum',
                            'issuer_logo'           => $dw->issuer_logo,
                            'product_id'            => $dw->product_id,
                            'product_name'          => $dw->product_name,
                            'purchase_allow'        => $purch_allow,
                            'sharpe_ratio'          => $dw->sharpe_ratio,
                            'total_investment'      => $total_inv,
                            'total_return'          => $total_rtn,
                            'volatility'            => $dw->standard_deviation,
                            'weight'                => floatval($dw->weight)
                        ];
                    }

                    $first_investment   += $inv_amt;
                    $investment_amount  += $total_inv;
                    $projected_amount   += $future_value;
                    $total_return       += $total_rtn;
                }
            }
            
            for ($i = 0; $i <= $calc->time_horizon; $i++)
            {
                $amt_year   = $prj_amt = $prj_amt_max = $prj_amt_min = 0;
                $nyear      = date('Y', strtotime('+'. $i .' years'));
                if (!empty($product))
                {
                    foreach ($product as $prd)
                    {
                        if ($prd['investment_type'] == 'Lumpsum')
                        {
                            $amt_year += $prd['investment_amount'];
                            if ($i > 0)
                            {
                                $prj_amt += $prd['investment_amount'] * pow(1 + $prd['expected_return_month'], (12  * $i));
                                $prj_amt_min += $prd['investment_amount'] * pow(1 + $prd['expected_return_month_min'], (12  * $i));
                                // $prj_amt_max += $prd['investment_amount'] * pow(1 + $prd['expected_return_month_max'], (12  * $i));
                            }   
                            else
                            {
                                $prj_amt += $prd['investment_amount'];
                                $prj_amt_min += $prd['investment_amount'];
                                // $prj_amt_max += $prd['investment_amount'];
                            }
                            
                        }
                        else
                        {
                            if ($i > 0)
                            { 
                                $amt_year += $i == 0 ? $prd['investment_amount'] * 12 : $prd['investment_amount'] * $i * 12;
                                $prj_amt  += (($prd['investment_amount'] * (1 + $prd['expected_return_month'])) * (pow(1 + $prd['expected_return_month'], (12 * $i)) - 1)) / $prd['expected_return_month'];
                                $prj_amt_min  += (($prd['investment_amount'] * (1 + $prd['expected_return_month_min'])) * (pow(1 + $prd['expected_return_month_min'], (12 * $i)) - 1)) / $prd['expected_return_month_min'];
                                // $prj_amt_max  += (($prd['investment_amount'] * (1 + $prd['expected_return_month_max'])) * (pow(1 + $prd['expected_return_month_max'], (12 * $i)) - 1)) / $prd['expected_return_month_max'];
                            }
                            else
                            {
                                $amt_year += $prd['investment_amount'];
                                $prj_amt  += $prd['investment_amount'];
                                $prj_amt_min += $prd['investment_amount'];
                                // $prj_amt_max += $prd['investment_amount'];
                            }
                        }
                    }
                }
                $projected_growth['investment_amount'][$nyear]  = $amt_year;
                $projected_growth['projected_amount'][$nyear]   = $prj_amt;  
                $projected_growth['projected_amount_min'][$nyear]   = $prj_amt_min;  
                $projected_growth['projected_amount_max'][$nyear]   = $prj_amt_max;  
            }
            
            return $this->app_response('Calculator', [
                'expected_return'   => $expected_return,
                'first_investment'  => $first_investment,
                'future_amount'     => $future_amount,
                'inflation'         => ['avg' => $calc->inflation_avg, 'year' => $calc->inflation_year],
                'investment_amount' => $investment_amount,
                'product'           => $product,
                'projected_amount'  => $projected_amount,
                'projected_growth'  => $projected_growth,
                'purchase_allow'    => $purchase_allow,
                'total_return'      => $total_return
            ]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function change_product(Request $request)
    {
        try
        {
            $score = Score::select('product_id', 'expected_return_year', 'expected_return_month')
                    ->where([['is_active', 'Yes'], ['product_id', $request->product_id]])->first();

            return $this->app_response('Score', $score);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id, $inv_id='')
    {
        try
        {
            $inv_id     = !empty($inv_id) ? $inv_id : Auth::id();
            $assets     = $detail = $prod = [];
            $prj_amt    = 0;
            $invst      = Investment::select('t_goal_investment.*','t_goal_investment.goal_invest_id as investment_id', 'b.profile_name', 'c.goal_name', 'd.reference_name as status_name', 'd.reference_color as status_color')
                        ->leftJoin('m_risk_profiles as b', function($qry) { return $qry->on('t_goal_investment.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_goals as c', function($qry) {return $qry->on('t_goal_investment.goal_id', '=', 'c.goal_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_trans_reference as d', function($qry) {return $qry->on('t_goal_investment.status_id', '=', 'd.trans_reference_id')->where('d.is_active', 'Yes'); })
                        ->where([['investor_id', $inv_id], ['goal_invest_id', $id], ['t_goal_investment.is_active', 'Yes']])
                        ->first();
            
            if (!empty($invst->goal_invest_id))
            {
                $product    = InvestmentDetail::selectRaw('t_goal_investment_detail.*, b.product_id, b.product_name, c.asset_class_id, c.asset_class_name, c.asset_class_color, d.symbol, e.issuer_logo')
                            ->join('m_products as b', function($qry) {return $qry->on('t_goal_investment_detail.product_id', '=', 'b.product_id')->where('b.is_active', 'Yes'); })
                            ->leftJoin('m_asset_class as c', function($qry) {return $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                            ->leftJoin('m_currency as d', function($qry) {return $qry->on('b.currency_id', '=', 'd.currency_id')->where('d.is_active', 'Yes'); })
                            ->leftJoin('m_issuer as e', function($qry) {return $qry->on('b.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes'); })
                            ->where([['goal_invest_id', $id], ['t_goal_investment_detail.is_active', 'Yes']])
                            ->get();
                
                $d1     = new \DateTime($invst->goal_invest_date);
                $d2     = new \DateTime(date('Y-m-d'));
                $diff   = $d2->diff($d1);
                $month  = 0;
                
                if ($diff->y > 0)
                    $month = $diff->m > 0 ? ($diff->y*12)+$diff->m : $diff->y*12;
                else
                    $month = $diff->m;
                
                $prj_amt = $month == 0 ? $invst->first_investment : 0;
                
                foreach ($product as $prd)
                {
                    $prod[] = [
                        'product_id'              => $prd->product_id,
                        'product_name'            => $prd->product_name,
                        'asset_class_name'        => $prd->asset_class_name,
                        'expected_return_year'    => $prd->expected_return_year,
                        'expected_return_month'   => $prd->expected_return_month,
                        'target_allocation'       => $prd->target_allocation,
                        'net_amount'              => $prd->net_amount,
                        'investment_type'         => $prd->investment_type,
                        'sharpe_ratio'            => $prd->sharpe_ratio,
                        'volatility'              => $prd->volatility,
                        'issuer_logo'             => $prd->issuer_logo
                    ];
                    
                    if ($month > 0)
                    {
                        if ($prd->investment_type == 'Lumpsum')
                            $prj_amt += $prd->net_amount * pow(1 + $prd->expected_return_month, $month);
                        else
                            $prj_amt += $prd->expected_return_month > 0 ? (($prd->net_amount * (1 + $prd->expected_return_month)) * (pow(1 + $prd->expected_return_month, $month) - 1)) / $prd->expected_return_month : 0;
                    }
                    
                    if (in_array($prd->asset_class_id, array_keys($assets)))
                    {
                        $assets[$prd->asset_class_id]['weight'] += $prd->target_allocation;
                    }
                    else
                    {
                        $assets[$prd->asset_class_id] = [
                            'asset_class_name'  => $prd->asset_class_name,
                            'asset_class_color' => $prd->asset_class_color,
                            'symbol'            => $prd->symbol,
                            'weight'            => $prd->target_allocation
                        ];
                    }
                }
            }

            return $this->app_response('Goals Detail', ['data' => $invst, 'assets' => array_values($assets), 'growth' => $prj_amt,'product' => $prod]);        
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail_for_sales($investor_id, $id)
    {
        return $this->detail($id, $investor_id);
    }
    
    public function fee_product($id, $type)
    {
        try
        {
            $ref    = ['Sub' => 'Sub Fee', 'Red' => 'Red Fee'];
            $fee    = Fee::select('fee_product_id', 'fee_value', 'value_type')
                    ->join('m_fee_reference as b', 'fee_id', '=', 'fee_reference_id')
                    ->where([['product_id', $id], ['effective_date', '<=', $this->app_date()], ['reference_value', $ref[$type]], ['m_products_fee.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->first();
            return $this->app_response('Fee Product', $fee);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }
    
    public function get_model(Request $request)
    {
        try
        {
            $model  = [];
            $pfl_id = $request->input('profile_id');
            $qmdl   = ModelMapping::select('b.model_id', 'b.model_name')
                      ->join('m_models as b', 'm_models_mapping.model_id', '=', 'b.model_id')
                      ->where([['m_models_mapping.profile_id', $pfl_id], ['m_models_mapping.is_active', 'Yes'], ['b.is_active', 'Yes']])
                      ->get();
            foreach ($qmdl as $row)
            {
                $exp_rtn    = 0;
                $asset      = $color = $weight = $category = [];
                $qast       = AssetClass::select('m_asset_class.asset_class_id', 'asset_class_color', 'asset_class_name', 'c.asset_category_name')
                            ->join('m_portfolio_allocations as b', 'm_asset_class.asset_class_id', '=', 'b.asset_class_id')
                            ->leftJoin('m_asset_categories as c', function($qry) {$qry->on ('m_asset_class.asset_category_id', '=', 'c.asset_category_id')->where('c.is_active', 'Yes'); })
                            ->where([['b.model_id', $row->model_id], ['m_asset_class.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
                foreach ($qast as $dt)
                {
                    $qalloc     = AllocationWeight::selectRaw('expected_return_year, effective_date, max(b.weight) as weight, m_portfolio_allocations_weights.created_by')
                                ->join('m_portfolio_allocations_weights_detail as b', 'm_portfolio_allocations_weights.allocation_weight_id', '=', 'b.allocation_weight_id')
                                ->join('m_products as c', 'b.product_id', '=', 'c.product_id')
                                ->where([['m_portfolio_allocations_weights.model_id', $row->model_id], ['c.asset_class_id', $dt->asset_class_id], ['m_portfolio_allocations_weights.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                                ->groupBy('expected_return_year', 'effective_date', 'm_portfolio_allocations_weights.created_by')
                                ->orderBy('effective_date', 'desc')->first();
                    $asset[]    = $dt->asset_class_name;
                    $color[]    = $dt->asset_class_color;
                    $category[] = $dt->asset_category_name;
                    $weight[]   = !empty($qalloc->expected_return_year) ? $qalloc->weight : 0;
                    $exp_rtn    = !empty($qalloc->expected_return_year) ? $qalloc->expected_return_year : 0;
                    $label      = !empty($qalloc->created_by) ? $qalloc->created_by : '';                }
                $model[] = [
                    'asset'             => $asset,
                    'color'             => $color,
                    'expected_return'   => $exp_rtn,
                    'weight'            => $weight,
                    'category'          => $category,
                    'model_id'          => $row->model_id,
                    'model_name'        => $row->model_name,
                    'label'             => $label
                ];
            }
            return $this->app_response('Model', ['model' => $model]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function list_of_goals_with_sales(Request $request, $id)
    {
        return $this->index($request, $id);
    }

    public function rebalancing(Request $request)
    {
        try
        {
            $data   = [];
            $limit  = !empty($request->limit) ? $request->limit : 0;
            $page   = !empty($request->page) ? $request->page : 1;
            $offset = ($page-1)*$limit;
            $user   = $this->auth_user()->usercategory_name == 'Investor' ? 'ui.investor_id' : 'ui.sales_id';

            $query  = DB::table('t_goal_investment as tgi')
                    ->join('u_investors as ui', 'ui.investor_id', '=', 'tgi.investor_id')
                    ->leftJoin('m_models as mm', function($qry) { return $qry->on('mm.model_id', '=', 'tgi.model_id')->where('mm.is_active', 'Yes'); })
                    ->leftJoin('m_risk_profiles as rp', function($qry) { return $qry->on('rp.profile_id', '=', 'tgi.profile_id')->where('rp.is_active', 'Yes'); })
                    ->leftJoin('m_goals as mg', function($qry) {return $qry->on('mg.goal_id', '=', 'tgi.goal_id')->where('mg.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as mtr', function($qry) { return $qry->on('mtr.trans_reference_id', '=', 'tgi.status_id')->where([['mtr.reference_type', 'Goals Status'], ['mtr.is_active', 'Yes']]); })
                    ->where([[$user, $this->auth_user()->id], ['tgi.is_active', 'Yes'], ['ui.is_active', 'Yes']]);

	        if (!empty($request->search))
            {
                $query->where(function($qry) use ($request) {
                    $qry->where('tgi.goal_invest_date', 'ilike', '%'. $request->search .'%')
                        ->orWhere('tgi.goal_title', 'ilike', '%'. $request->search .'%')
                        ->orWhere('tgi.portfolio_id', 'ilike', '%'. $request->search .'%');
                });
            }
            if (!empty($request->goal))
                $query->where('tgi.goal_id', $request->goal);
            if (!empty($request->status))
                $query->where('mtr.trans_reference_id', $request->status);
            if (!empty($request->balance_minimum))
                $query->where('tgi.total_amount', '>=', $request->balance_minimum);
            if (!empty($request->balance_maximum))
                $query->where('tgi.total_amount', '<=', $request->balance_maximum);
            if (!empty($request->order))
            {
                $sort = !empty($request->sort) ? $request->sort : 'asc';
                $query->orderBy($request->order, $sort);
            }
            
            if ($query->count() > 0)
            {
                $goals = $query->skip($offset)->take($limit)->get();
                
                $investorIds    = $goals->pluck('investor_id');
                $portfolioIds   = $goals->pluck('portfolio_id');
                $goalInvestIds  = $goals->pluck('goal_invest_id');

                $transHistDays  = DB::table('t_trans_histories_days as thd')
                                ->join('m_products as mp', 'thd.product_id', '=', 'mp.product_id')
                                ->whereIn('thd.investor_id', $investorIds)
                                ->whereIn('thd.portfolio_id', $portfolioIds)
                                ->where('thd.history_date', DB::raw('CURRENT_DATE'))
                                ->where('thd.is_active', 'Yes')
                                ->where('mp.is_active', 'Yes')
                                ->select('thd.investor_id', 'thd.portfolio_id', DB::raw('SUM(thd.current_balance) as total_balance'))
                                ->groupBy('thd.investor_id', 'thd.portfolio_id')
                                ->get();

                $investDetail   = DB::table('t_goal_investment_detail as gdi')
                                ->join('m_products as mp', 'gdi.product_id', '=', 'mp.product_id')
                                ->whereIn('gdi.goal_invest_id', $goalInvestIds)
                                ->where('gdi.is_active', 'Yes')
                                ->where('mp.is_active', 'Yes')
                                ->select('gdi.goal_invest_id', 'gdi.net_amount', 'gdi.expected_return_month', 'gdi.investment_type')
                                ->get();

                $data = $goals->map(function ($dt) use ($transHistDays, $investDetail)
                {
                    $balance = optional($transHistDays->where([['investor_id', $dt->investor_id], ['portfolio_id', $dt->portfolio_id]])->first())->total_balance ?? 0;
                    if ($balance >= 0)
                    {
                        $detail = $investDetail->where('goal_invest_id', $dt->goal_invest_id);
                        if ($detail->count() > 0)
                        {
                            $d1     = new \DateTime($dt->goal_invest_date);
                            $d2     = new \DateTime(date('Y-m-d'));
                            $diff   = $d2->diff($d1);
                            $month  = 0;

                            if ($diff->y > 0)
                                $month = $diff->m > 0 ? ($diff->y*12)+$diff->m : $diff->y*12;
                            else
                                $month = $diff->m;

                            $prj_amt = $month == 0 ? $dt->first_investment : 0;

                            foreach ($detail as $dtl)
                            {                    
                                if ($month > 0)
                                {
                                    if ($dtl->investment_type == 'Lumpsum')
                                        $prj_amt += $dtl->net_amount * pow(1 + $dtl->expected_return_month, $month);
                                    else
                                        $prj_amt += $dtl->expected_return_month > 0 ? (($dtl->net_amount * (1 + $dtl->expected_return_month)) * (pow(1 + $dtl->expected_return_month, $month) - 1)) / $dtl->expected_return_month : 0;
                                }
                            }

                            if ($prj_amt > $balance)
                            {
                                return [
                                    'goal_invest_id'        => $dt->goal_invest_id,
                                    'investor_id'           => $dt->investor_id,
                                    'cif'                   => $dt->cif,
                                    'fullname'              => $dt->fullname,
                                    'photo_profile'         => $dt->photo_profile,
                                    'goal_id'               => $dt->goal_id,
                                    'profile_id'            => $dt->profile_id,
                                    'model_id'              => $dt->model_id,
                                    'status_id'             => $dt->status_id,
                                    'portfolio_id'          => $dt->portfolio_id,
                                    'goal_invest_date'      => $dt->goal_invest_date,
                                    'goal_title'            => $dt->goal_title,
                                    'today_amount'          => $dt->today_amount,
                                    'time_horizon'          => $dt->time_horizon,
                                    'investment_amount'     => $dt->investment_amount,
                                    'projected_amount'      => $dt->projected_amount,
                                    'total_return'          => $dt->total_return,
                                    'future_amount'         => $dt->future_amount,
                                    'first_investmen'       => $dt->first_investment,
                                    'monthly_investment'    => $dt->monthly_investment,
                                    'model_name'            => $dt->model_name,
                                    'goal_name'             => $dt->goal_name,
                                    'reference_code'        => $dt->reference_code,
                                    'reference_name'        => $dt->reference_name,
                                    'reference_color'       => $dt->reference_color,
                                    'created_at'            => $dt->created_at,
                                    'balance'               => floatval($balance),
                                    'growth'                => floatval($prj_amt)
                                ];                        
                            }
                        }
                    }
                    return null;
                })->filter()->values();
            }

            return $this->app_response('Portfolio Goals', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function SaveCheckoutGoalPerformance(Request $request){

        try{
            /* dibuat oleh Ahlicoding.com -->  Faradhillah : faradhillah29@gmail.com
                inv_id = 57; CIF = 73946374
            */
            $id         = null ; //karena BUY NEW;
            $inv_id     = !empty($request->investor_id) ? $request->investor_id : Auth::id();
            $cif        = !empty($request->investor_id) ? $request->cif : Auth::user()->cif;
            $sn         = substr($cif, -5) . date('ymd');

            $ref_code   = empty($request->save_type) ? 'checkout' :  ($request->save_type == 'checkout' ? 'IP' : 'OD');
            $request->save_type = $ref_code ;

            $qry_inv    = Investment::where([['investor_id', $inv_id], ['goal_invest_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('portfolio_id', 'desc')->first();
            $inv_prtf   = !empty($qry_inv->portfolio_id) ? substr($qry_inv->portfolio_id, -3) + 1 : 1;

            // 2 Untuk Goals , 1 untuk Non Goals , 3 untuk Retirement
            $portfolio_type = !empty($request->portfolio_type) && $request->portfolio_type == 1 ? 1 : 2  ;
            $portf_id   = $portfolio_type . $sn . str_pad($inv_prtf, 3, '0', STR_PAD_LEFT);

            $goal_invest_date = $this->app_date();
            $request->request->add(['goal_invest_date' => $goal_invest_date , 'portfolio_id' => $portf_id]);

            if ($ref_code == 'checkout')
            {
                $qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                $trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
                $type_ref   = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'SUB']]], 'SA\Transaction\Reference')->original['data'];
            }


            $invest_id              = $this->db_save($request, $id, ['res' => 'id']);
            $manager                = $this->db_manager($request);
            $product_id             = $request->product_id;
            $fee_product_id         = $request->fee_product_id;
            $investor_account_id    = $request->investor_account_id;
            $transaction_date       = $request->transaction_date;
            $debt_date              = $request->debt_date;
            $amount                 = $request->amount;
            $net_amount             = $request->net_amount;
            $fee_amount             = $request->fee_amount;
            $tax_amount             = $request->tax_amount;
            $exp_rtn_year           = $request->expected_return_year;
            $exp_rtn_month          = $request->expected_return_month;
            $target_allocation      = $request->target_allocation;
            $sharpe_ratio           = $request->sharpe_ratio;
            $treynor_ratio          = $request->volatility;
            $investment_type        = $request->investment_type;
            $percentage             = $request->percentage;

            // Sukses sampai di sini ----

            $status_ref = 1 ;

             $request->request->add(['investor_id' => $inv_id, 'status_id' => $status_ref, 'portfolio_id' => $portf_id]);

            return $this->app_partials(1, 0,
                ['id' => '2',
                'portf_id'=>$portf_id ,
                    'goal_invest_date' => $goal_invest_date,
                    'ref_code' => $request->save_type,
                    'ref_no' => $rn,
                    'trans_ref'=>$trans_ref,
                    'type_ref' => $type_ref,
                    'session_forget' => 'goal_setup']);

        }
        catch (\Exception $e){
            return response()->json($e->getMessage());
        }
    }


    public function save_checkout(Request $request, $id = null)
    {
        try
        {  

            $otp_input      = !empty($request->otp_input) ? $request->otp_input : '';
            $inv_id         = !empty($request->investor_id) ? $request->investor_id : Auth::id();
            $cif            = !empty($request->cif) ? $request->cif : Auth::user()->cif;
            $account_sub    = $this->get_account_sub($cif);
            $sn             = substr($cif, -5) . date('ymd');
            $ref_code       = $request->save_type == 'checkout' ? 'IP' : 'OD';
            $status_ref     = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Goals Status'], ['reference_code', $ref_code]]], 'SA\Transaction\Reference')->original['data'];
            $total_success  = 0;
            $total_failed   = 0;
            $count_success  = 0;
            $count_failed   = 0;


            if (empty($id))
            {
                $qry_inv    = Investment::where([['investor_id', $inv_id], ['goal_invest_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('portfolio_id', 'desc')->first();
                $inv_prtf   = !empty($qry_inv->portfolio_id) ? substr($qry_inv->portfolio_id, -3) + 1 : 1;
                $portf_id   = 2 . $sn . str_pad($inv_prtf, 3, '0', STR_PAD_LEFT);
                
                $request->request->add(['goal_invest_date' => $this->app_date()]);
            }
            else
            {
                $portf_id = $request->portfolio_id;
            }
            
            if ($request->save_type == 'checkout')
            {
				//$qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                //$rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                
                $trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
                $type_ref   = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'SUB']]], 'SA\Transaction\Reference')->original['data'];
            }
            
            $request->request->add(['investor_id' => $inv_id, 'status_id' => $status_ref, 'portfolio_id' => $portf_id]);
            
            $invest_id                  = $this->db_save($request, $id, ['res' => 'id']);
            $manager                    = $this->db_manager($request);
            $product_id                 = $request->product_id;
            $fee_product_id             = $request->fee_product_id;
            $investor_account_id        = $request->investor_account_id;
            $transaction_date           = $request->transaction_date;
            $debt_date                  = $request->debt_date;
            $amount                     = $request->amount;
            $net_amount                 = $request->net_amount;
            $fee_amount                 = $request->fee_amount;
            $tax_amount                 = $request->tax_amount;
            $exp_rtn_year               = $request->expected_return_year;
            $exp_rtn_month              = $request->expected_return_month;
            $target_allocation          = $request->target_allocation;
            $sharpe_ratio               = $request->sharpe_ratio;
            $treynor_ratio              = $request->volatility; 
            $investment_type_product    = $request->investment_type_product;
            $type_investment            = $request->type_investment;
            $percentage                 = $request->percentage;
            $tenor_month                = $request->tenor_month;
            $trans_hist_id              = array();
            $data_all                   = array();
            $data_sip                   = array();
            $data_lumpsum               = array();

            for ($i = 0; $i < count($product_id); $i++)
            {
                $data = [
                    'product_id'            => $product_id[$i],
                    'fee_product_id'        => !empty($fee_product_id[$i]) ? $fee_product_id[$i] : null,
                    'investor_account_id'   => !empty($investor_account_id[$i]) ? $investor_account_id[$i] : null,
                    'debt_date'             => !empty($debt_date[$i]) ? $debt_date[$i] : null,
                    'amount'                => !empty($amount[$i]) ? $amount[$i] : null,
                    'net_amount'            => $net_amount[$i],
                    'fee_amount'            => !empty($fee_amount[$i]) ? round(floatval($fee_amount[$i]),10) : null,
                    'tax_amount'            => !empty($tax_amount[$i]) ? floatval($tax_amount[$i]) : null,
                    'investment_type'       => $investment_type_product[$i],
                    'created_by'            => $manager->user,
                    'created_host'          => $manager->ip
                ];
                
                $invest_detail = array_merge([
                    'goal_invest_id'        => $invest_id,
                    'expected_return_year'  => !empty($exp_rtn_year[$i]) ? $exp_rtn_year[$i] : null,
                    'expected_return_month' => !empty($exp_rtn_month[$i]) ? $exp_rtn_month[$i] : null,
                    'target_allocation'     => !empty($target_allocation[$i]) ? $target_allocation[$i] : null,
                    'sharpe_ratio'          => !empty($sharpe_ratio[$i]) ? $sharpe_ratio[$i] : null,
                    'volatility'            => !empty($treynor_ratio[$i]) ? $treynor_ratio[$i] : null
                ], $data);


                if (empty($id))
                    InvestmentDetail::create($invest_detail);
                else
                    InvestmentDetail::where([['goal_invest_id', $invest_id], ['product_id', $product_id[$i]]])->update($invest_detail);
                

                if ($request->save_type == 'checkout')
                {
                    $sales_code = Investor::select('user_code')
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
                    $rst                = $this->cut_of_time($product_id[$i], 'array');
                    $transaction_date   = !empty($rst['transaction_date_allocation']) ? $rst['transaction_date_allocation'] : $this->app_date() ;
                    $qry_trs            = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $transaction_date], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                    $rn                 = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                    $ref_no             = substr($cif, -5) . date('ymd', strtotime($transaction_date)) . str_pad($rn, 3, '0', STR_PAD_LEFT);
                    
                    $trans_hist = array_merge([
                        'investor_id'           => $inv_id,
                        'portfolio_id'          => $portf_id,
                        'reference_no'          => $ref_no,
                        'status_reference_id'   => $status_ref,
                        'trans_reference_id'    => $trans_ref,
                        'type_reference_id'     => $type_ref,
                        //'transaction_date'    => !empty($transaction_date[$i]) ? $transaction_date[$i] : null,
                        'transaction_date'      => $transaction_date,
                        'percentage'            => !empty($percentage[$i]) ? $percentage[$i] : null
                    ], $data);


                    $account_number = Account::where([['investor_account_id', !empty($investor_account_id[$i]) ? $investor_account_id[$i] : null ]])->first();
                    $account_number = !empty($account_number) ? $account_number->account_no : null;
                    $product        = Product::where([['m_products.is_active', 'Yes'],['product_id', $product_id[$i]]])->first();

                    //return $this->app_partials($total_success, $total_failed, ['data' => $account_number]);
                    if(strtolower($investment_type_product[$i]) == 'sip') {
                    // if (strtolower($type_investment) == 'sip') {
                       $data                        = [];
                       $tenor                       = !empty($tenor_month[$i]) ? $tenor_month[$i] * 12 : null;
                       $data['investor_id']         = $inv_id;
                       $data['product_id']          = $product_id[$i];
                       $data['investor_account_id'] = $investor_account_id[$i];
                       $data['portfolio_id']        = $portf_id;
                       $data['account_no']          = $account_number;
                       $data['registered_id']       = !empty($request->registered_id[$i]) ? $request->registered_id[$i] : '';
                       $data['debt_date']           = !empty($request->debt_date[$i]) ?  $request->debt_date[$i] : null;
                       $data['tenor_month']         = $tenor;
                       $data['investment_amount']   = !empty($net_amount[$i]) ? floatval($net_amount[$i]) : 0;
                       $data['fee_amount']          = !empty($fee_amount[$i]) ? round(floatval($fee_amount[$i]),10) : 0;
                       $data['tax_amount']          = !empty($tax_amount[$i]) ? floatval($tax_amount[$i]) : 0;
                       $data['status']              = 'ACTIVE';
                       $data['start_date']          = $this->app_date();
                       $data['created_by']          = $manager->user;
                       $data['created_host']        =  $manager->ip;

                       $status_save = false;
                       $status_error_message = 'error: no message error from wms';
                        if ($trans = TransactionInstallment::create($data))
                        {
                            if(!empty($trans->trans_installment_id))
                            {
                                $investor   = Investor::where([['investor_id', $inv_id], ['is_active', 'Yes'], ['valid_account', 'Yes']])->first();
                                $product  = Product::where([['m_products.is_active', 'Yes'],['product_id',$product_id[$i]]])->first();

                                if( (!empty($inv_id)) && (!empty($product->product_code)) && (!empty($account_number)))
                                {
                                    $cif                = $investor->cif;
                                    $custAccountNo      = $account_number;
                                    $ProductCode        = $product->product_code;
                                    $amount_oms_wms     = ($net_amount[$i] * 100);
                                    $financialPlanning  = '---';
                                    $refNo              = $trans->trans_installment_id; 
                                    $api                = $this->api_ws(['sn' => 'TransactionInstallmentSave', 'val' => [$cif,$custAccountNo,$ProductCode,$amount_oms_wms,$tenor,$request->debt_date[$i],$financialPlanning,$refNo,$sales_wap]])->original;  
                                    if (!empty($api['message']->IsSuccess) && ($api['message']->IsSuccess == true)) {
                                        TransactionInstallment::where('trans_installment_id', $trans->trans_installment_id)->update(['registered_id' => $api['message']->Result->RegisterID]);
                                        $status_save = true;
                                        $data['forward_to_wms_status'] = 'success';
                                        $data['forward_to_wms_respon'] = $api;                                        
                                        $total_success++;
                                        $count_success++;
                                    }
                                    else
                                    {
                                        if(!empty($api['message']->Message))
                                        {
                                            $status_error_message  = 'error: '.$api['message']->Message;
                                        }
                                        TransactionInstallment::where('trans_installment_id', $trans->trans_installment_id)->update(['is_active'=>'No','wms_message' => $status_error_message]);
                                        $data['forward_to_wms_status'] = 'failed';
                                        $data['forward_to_wms_respon'] = $status_error_message; 
                                        $total_failed++;
                                        $count_failed++;                                              
                                    } 
                               }  else {
                                  $status_error_message  = 'validation CPM Service : cif, product code or accout no not found';
                                  TransactionInstallment::where('trans_installment_id', $trans->trans_installment_id)->update(['is_active'=>'No','wms_message' => $status_error_message]);                    
                                  $data['forward_to_wms_status'] = 'failed';
                                  $data['forward_to_wms_respon'] = $status_error_message; 
                                  $total_failed++;
                                  $count_failed++;                                  
                               }
                           } else {
                              $data['forward_to_wms_status'] = 'failed';
                              $data['forward_to_wms_respon'] = $status_error_message; 
                              $total_failed++;
                              $count_failed++;                            
                           }
                       } else {
                              $data['forward_to_wms_status'] = 'failed';
                              $data['forward_to_wms_respon'] = $status_error_message; 
                              $total_failed++;
                              $count_failed++;                        
                       }

                       $data_sip[] = $data;                                                                  
                    } else {
                        $dataWMS = array();  
                        if (!empty($product))
                        {         
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
                            //$dataWMS[] = floatval($amount[$i]);   
                            $dataWMS[] = floatval($net_amount[$i]);                         
                            //8. "promos": null,
                            $dataWMS[] = null;                         
                            /* 9. "fees" */
                            //$feesContaint = '[{ "code": "DirectAmount", "amount": '.$request->amount.' }]';
                            //$dataWMS[] = [['code'   => 'DirectAmount', 'amount' => floatval($fee_amount[$i]) ]];                        
                            //sementar ambabila fee 0 diberik angka 1 karena di wms selau di recject apabila fee = 0, nanti di konfirmasi ke tim wms
                            //$fee_amount[$i] =  $fee_amount[$i] < 1 ? 1 :  $fee_amount[$i];
                            $dataWMS[] = [['code'   => 'DirectAmount', 'amount' => floatval($fee_amount[$i]) ]];                        

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
                            $dataWMS[] = !empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null;           
                            //15. counterPartyReferralCode": "123",
                            $dataWMS[] = $sales_wap;            
                           
                            //"16. isAdvice": false,
                            $dataWMS[] = false;            
                            //"17. remark": "test buy mf 001",
                            $dataWMS[] = "000";            
                            //"18. referenceNo": "PortfolioNumber001",
                            //$dataWMS[] = $portf_id;   
                            $dataWMS[] = !empty($ref_no) ?  $ref_no : null ;       
                            //"19. entryBy": "dummy",
                            $dataWMS[] = $manager->user;                        
                            //"20. entryHost": "localhost"
                            $dataWMS[] = $manager->ip;  

                            //$api = $this->api_ws(['sn' => 'SmsGateway', 'val' => $dataWMS])->original;
                            $api = $this->api_ws(['sn' => 'TransactionWMSSub', 'val' => $dataWMS])->original;
                            if(!empty($api['success']) && $api['success'] == true)
                            { 
                                $trans_hist = array_merge([
                                    'account_no'  => !empty($account_sub[$product->product_code]) ? $account_sub[$product->product_code] : null,
                                    'send_wms'    => true,
                                    'guid'        => !empty($api['data']->data) ? $api['data']->data : null,
                                ], $trans_hist);
                                
                                $trans_hist_return = TransactionHistory::create($trans_hist);
                               
                                if(!empty($trans_hist_return->trans_history_id)) {
                                    $trans_hist_id[] = $trans_hist_return->trans_history_id;

                                    /*    
                                    $sendEmailNotification = new \App\Http\Controllers\Administrative\Broker\MessagesController;  
                                    $api_email = $sendEmailNotification->transaction($trans_hist_return->trans_history_id);
                                    */

                                    $sendEmailNotification = new MessagesController;
                                    $api_email = $sendEmailNotification->transaction($trans_hist_return->trans_history_id);                                                                                            
                                    if(!empty($api_email->original['success']) && $api_email->original['success'] == 1) {
                                       TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_email' => 'Yes']);                
                                    } else {
                                       TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_email' => 'No']);                                               
                                    }          

                                    $product_notification_amount = !empty($amount[$i]) ? $amount[$i] : 0;
                                    $product_notification = $product->product_name.' Sebesar ( Rp. '.number_format($product_notification_amount).')';

                                    $investor_mobile_phone  = Investor::select('mobile_phone')->where([['u_investors.is_active', 'Yes'],['investor_id',  $inv_id]])->first();
                                    $conf    = MobileContent::where([['mobile_content_name', 'TransactionSub'], ['is_active', 'Yes']])->first();
                                    $msg     = !empty($conf->mobile_content_text) ? str_replace('{product}', $product_notification, $conf->mobile_content_text) : '';
                                    $api_sms = $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$investor_mobile_phone->mobile_phone, $msg]]);   

                                    if(!empty($api_sms->original['code']) && $api_sms->original['code'] == 200) {
                                       TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_sms' => 'Yes']);                
                                    } else {
                                       TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_sms' => 'No']);                                             
                                    }        

                                    $data['send_notication_email'] = $api_email;
                                    $data['send_notication_sms']   = $api_sms;
                                    $data['forward_to_wms_status'] = 'success';
                                    $data['forward_to_wms_respon'] = $api; 
                                    //$data['forward_to_wms_respon'] = $dataWMS; 
                                }

                                $total_success++;
                                $count_success++;
                            }
                            else
                            {
                              $data['send_notication_email'] = null;
                              $data['send_notication_sms']   = null;                            
                              $data['status_forward_to_wms'] = 'failed';   
                              $data['forward_to_wms_respon'] = [$api,$dataWMS];      
                              $total_failed++;
                              $count_failed++;
                            }    
                        }     

                        $data_lumpsum[] = $data;                                           
                    }
                    $data_all[] = $data;                      
                }
            } 

            if (count($trans_hist_id) > 0)
            {
                $trans_history_implode = implode('~', $trans_hist_id);
                TransactionOtp::where(['investor_id' => $inv_id, 'otp' => $otp_input,'is_active' => 'Yes'])->update(['is_valid' => 'Yes', 'trans_history_id' => $trans_history_implode]);                
            }

            if ($request->save_type != 'checkout') {
                $count_success = count($product_id);
                $count_failed = 0;
            }    

            return $this->app_partials($count_success, $count_failed, ['id' => $invest_id,  'session_forget' => 'goal_setup', 'data_investment_sip' => $data_sip, 'data_investment_lumpsum' => $data_lumpsum ]);

        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function ToArray($input){
        if (!is_array($input)){
           return json_decode($input);
        }
        else return $input;
    }

    public function timestamp()
    {
        try
        {
            $inv_id = !empty($request->investor_id) ? $request->investor_id : Auth::id();
            $data   = AssetOutstanding::where([['is_active', 'Yes'], ['investor_id', $inv_id]])
                    ->whereNotNull('created_at')
                    ->orderBy('created_at', 'DESC')->first();
            return $this->app_response('time', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function tools(Request $request)
    {
        try
        {
            $pfl    = $this->auth_user()->usercategory_name == 'Investor' ? [] : Profile::where([['profile_id', $request->profile_id], ['is_active', 'Yes']])->first();
            $pflnm  = !empty($pfl) ? $pfl->profile_name : '';
            $goal   = Goal::where('is_active', 'Yes')->orderBy('goal_name')->get();
            $risk   = Profile::where('is_active', 'Yes')->orderBy('sequence_to')->get();
            return $this->app_response('Investor', ['goal' => $goal, 'riskprof' => $risk, 'pfl_name' => $pflnm]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    // public function validasi_risk_profile(Request $request)
    // {
    //     try
    //     {
    //         $api = $this->api_ws(['sn' => 'ValidasiRiskProfile'])->original['data'];

    //         return $this->app_response('Vlaidasi Risk Profiles', $api);
    //     }
    //     catch (\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     }
    // }
    public function saveCheckoutProductPerformance(Request $request)
    {
        try {
            $inv_id     = !empty($request->investor_id) ? $request->investor_id : Auth::id();
            $cif        = !empty($request->investor_id) ? $request->cif : Auth::user()->cif;

            $sn         = substr($cif, -5) . date('ymd');
            $ref_code   =  $request->save_type == 'checkout' ? 'IP' : 'OD';
            $status_ref = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Goals Status'], ['reference_code', $ref_code]]], 'SA\Transaction\Reference')->original['data'];

            if (empty($id)) {
                $qry_inv    = Investment::where([['investor_id', $inv_id], ['goal_invest_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('portfolio_id', 'desc')->first();
                $inv_prtf   = !empty($qry_inv->portfolio_id) ? substr($qry_inv->portfolio_id, -3) + 1 : 1;

                // 2 Untuk Goals , 1 untuk Non Goals , 3 untuk Retirement
                $portfolio_type = !empty($request->portfolio_type) && $request->portfolio_type == 1 ? 1 : 2;
                $portf_id   = $portfolio_type . $sn . str_pad($inv_prtf, 3, '0', STR_PAD_LEFT);

                $request->request->add(['goal_invest_date' => $this->app_date(), 'portfolio_id' => $portf_id]);
            } else {
                $portf_id = $request->portfolio_id;
            }

            if ($request->save_type == 'checkout') {
                $qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                $trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
                $type_ref   = $this->db_row('trans_reference_id', ['where' => [[
                    'reference_type', 'Transaction Type'
                ], ['reference_code', 'SUB']]], 'SA\Transaction\Reference')->original['data'];
            }

            $request->request->add(['investor_id' => $inv_id, 'status_id' => $status_ref, 'portfolio_id' => $portf_id]);

            $invest_id              = $this->db_save($request, $id, ['res' => 'id']);
            $manager                = $this->db_manager($request);
            $product_id             = $this->ToArray($request->product_id);
            $fee_product_id         = $this->ToArray($request->fee_product_id);
            $investor_account_id    = $this->ToArray($request->investor_account_id);
            $transaction_date       = $this->ToArray($request->transaction_date);
            $debt_date              = $this->ToArray($request->debt_date);
            $amount                 = $this->ToArray($request->amount);
            $net_amount             = $this->ToArray($request->net_amount);
            $fee_amount             = $this->ToArray($request->fee_amount);
            $tax_amount             = $this->ToArray($request->tax_amount);
            $exp_rtn_year           = $this->ToArray($request->expected_return_year);
            $exp_rtn_month          = $this->ToArray($request->expected_return_month);
            $target_allocation      = $this->ToArray($request->target_allocation);
            $sharpe_ratio           = $this->ToArray($request->sharpe_ratio);
            $treynor_ratio          = $this->ToArray($request->volatility);
            $investment_type        = $this->ToArray($request->investment_type);
            $percentage             = $this->ToArray($request->percentage);

            for ($i = 0; $i < count($product_id); $i++) {
                $data = [
                    'product_id'            => $product_id[$i],
                    'fee_product_id'        => !empty($fee_product_id[$i]) ? $fee_product_id[$i] : null,
                    'investor_account_id'   => !empty($investor_account_id[$i]) ? $investor_account_id[$i] : null,
                    'debt_date'             => !empty($debt_date[$i]) ? $debt_date[$i] : null,
                    'amount'                => !empty($amount[$i]) ? $amount[$i] : null,
                    'net_amount'            => $net_amount[$i],
                    'fee_amount'            => !empty($fee_amount[$i]) ? $fee_amount[$i] : null,
                    'tax_amount'            => !empty($tax_amount[$i]) ? $tax_amount[$i] : null,
                    'investment_type'       => $investment_type[$i],
                    'created_by'            => $manager->user,
                    'created_host'          => $manager->ip
                ];
                //
                $invest_detail = array_merge([
                    'goal_invest_id'        => $invest_id,
                    'expected_return_year'  => !empty($exp_rtn_year[$i]) ? $exp_rtn_year[$i] : null,
                    'expected_return_month' => !empty($exp_rtn_month[$i]) ? $exp_rtn_month[$i] : null,
                    'target_allocation'     => !empty($target_allocation[$i]) ? $target_allocation[$i] : null,
                    'sharpe_ratio'          => !empty($sharpe_ratio[$i]) ? $sharpe_ratio[$i] : null,
                    'volatility'            => !empty($treynor_ratio[$i]) ? $treynor_ratio[$i] : null
                ], $data);
                //
                if (empty($id))
                    InvestmentDetail::create($invest_detail);
                else
                    InvestmentDetail::where([['goal_invest_id', $invest_id], ['product_id', $product_id[$i]]])->update($invest_detail);
                //
                if ($request->save_type == 'checkout') {
                    $ref_no     = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);
                    $trans_hist = array_merge([
                        'investor_id'           => $inv_id,
                        'portfolio_id'          => $portf_id,
                        'reference_no'          => $ref_no,
                        'status_reference_id'   => $status_ref,
                        'trans_reference_id'    => $trans_ref,
                        'type_reference_id'     => $type_ref,
                        'transaction_date'      => !empty($transaction_date[$i]) ? $transaction_date[$i] : null,
                        'percentage'            => !empty($percentage[$i]) ? $percentage[$i] : null
                    ], $data);
                    TransactionHistory::create($trans_hist);
                    $rn++;
                }
            }
            return $this->app_partials(1, 0, ['id' => $invest_id, 'count product_id' => count($product_id),  'session_forget' => 'goal_setup']);
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function getModelFromInvestor()
    {
        return DB::table('m_models_mapping')->where('profile_id', Auth::user()->profile_id)->get();
    }
    /**
     * Fungsi menyimpan data non goals
     * 
     */
    public function saveNonGoals(Request $request, $id = null)
    {
        try {
            $inv_id     = !empty($request->investor_id) ? $request->investor_id : Auth::id();
            $cif        = !empty($request->investor_id) ? $request->cif : Auth::user()->cif;

            $sn         = substr($cif, -5) . date('ymd');
            $ref_code   =  $request->save_type == 'checkout' ? 'IP' : 'OD';
            $status_ref = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Goals Status'], ['reference_code', $ref_code]]], 'SA\Transaction\Reference')->original['data'];

            if (empty($id)) {
                $qry_inv    = Investment::where([['investor_id', $inv_id], ['goal_invest_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('portfolio_id', 'desc')->first();
                $inv_prtf   = !empty($qry_inv->portfolio_id) ? substr($qry_inv->portfolio_id, -3) + 1 : 1;

                // 2 Untuk Goals , 1 untuk Non Goals , 3 untuk Retirement
                $portfolio_type = !empty($request->portfolio_type) && $request->portfolio_type == 1 ? 1 : 2;
                $portf_id   = $portfolio_type . $sn . str_pad($inv_prtf, 3, '0', STR_PAD_LEFT);

                $request->request->add(['goal_invest_date' => $this->app_date(), 'portfolio_id' => null]);
            } else {
                $portf_id = $request->portfolio_id;
            }

            if ($request->save_type == 'checkout') {
                $qry_trs    = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                $trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
                $type_ref   = $this->db_row('trans_reference_id', ['where' => [[
                    'reference_type', 'Transaction Type'
                ], ['reference_code', 'SUB']]], 'SA\Transaction\Reference')->original['data'];
            }

            $request->request->add(['investor_id' => $inv_id, 'status_id' => $status_ref, 'portfolio_id' => null]);

            $invest_id              = $this->db_save($request, $id, ['res' => 'id']);
            $manager                = $this->db_manager($request);
            $product_id             = $this->ToArray($request->product_id);
            $fee_product_id         = $this->ToArray($request->fee_product_id);
            $investor_account_id    = $this->ToArray($request->investor_account_id);
            $transaction_date       = $this->ToArray($request->transaction_date);
            $debt_date              = $this->ToArray($request->debt_date);
            $amount                 = $this->ToArray($request->amount);
            $net_amount             = $this->ToArray($request->net_amount);
            $fee_amount             = $this->ToArray($request->fee_amount);
            $tax_amount             = $this->ToArray($request->tax_amount);
            $investment_type        = $this->ToArray($request->investment_type);
            $percentage             = $this->ToArray($request->percentage);

            for ($i = 0; $i < count($product_id); $i++) {
                $data = [
                    'product_id'            => $product_id[$i],
                    'fee_product_id'        => !empty($fee_product_id[$i]) ? $fee_product_id[$i] : null,
                    'investor_account_id'   => !empty($investor_account_id[$i]) ? $investor_account_id[$i] : null,
                    'debt_date'             => !empty($debt_date[$i]) ? $debt_date[$i] : null,
                    'amount'                => !empty($amount[$i]) ? $amount[$i] : null,
                    'net_amount'            => $net_amount[$i],
                    'fee_amount'            => !empty($fee_amount[$i]) ? $fee_amount[$i] : null,
                    'tax_amount'            => !empty($tax_amount[$i]) ? $tax_amount[$i] : null,
                    'investment_type'       => $investment_type[$i],
                    'created_by'            => $manager->user,
                    'created_host'          => $manager->ip
                ];
                // invest detail ga perlu dimasukkan.
                if ($request->save_type == 'checkout') {
                    $ref_no     = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);
                    $trans_hist = array_merge([
                        'investor_id'           => $inv_id,
                        'portfolio_id'          => null,
                        'reference_no'          => $ref_no,
                        'status_reference_id'   => $status_ref,
                        'trans_reference_id'    => $trans_ref,
                        'type_reference_id'     => $type_ref,
                        'transaction_date'      => !empty($transaction_date[$i]) ? $transaction_date[$i] : null,
                        'percentage'            => !empty($percentage[$i]) ? $percentage[$i] : null
                    ], $data);
                    TransactionHistory::create($trans_hist);
                    $rn++;
                }
            }
            return $this->app_partials(1, 0, ['id' => $invest_id, 'count product_id' => count($product_id),  'session_forget' => 'goal_setup']);
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function check_bank_account(Request $request)
    {
        try
        {
            $balance  = 0;
            $inv_id   = Auth::user()->usercategory_name == 'Investor' ? Auth::id() : $request->investor_id;
            $investor = Account::select('b.cif', 'u_investors_accounts.*')
                        ->join('u_investors as b', 'u_investors_accounts.investor_id', '=', 'b.investor_id')
                        ->where([['b.investor_id', $inv_id], ['u_investors_accounts.investor_account_id', $request->investor_account_id], ['u_investors_accounts.is_active', 'Yes'], ['b.is_active', 'Yes'], ['b.valid_account', 'Yes']])
                        ->first();
            if(!empty($investor->cif))
            {
                $account    = $this->api_ws(['sn' => 'InvestorAccount', 'val' => [$investor->cif]])->original['data'];
                foreach ($account as $acc) {
                    if($acc->accountTypeCode == $investor->ext_code)
                    {
                        $balance = $acc->balance;
                    }
                }    
            }
            return $this->app_response('Get Bank account', $balance);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
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
}
