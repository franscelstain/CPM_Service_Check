<?php

namespace App\Http\Controllers\Investor\Financial\Planning;

use App\Http\Controllers\AppController;
use App\Models\Investor\Financial\Planning\Goal\InvestmentDetail;
use App\Models\SA\Assets\AssetClass;
use App\Models\SA\Assets\Portfolio\AllocationWeight;
use App\Models\SA\Assets\Portfolio\AllocationWeightDetail;
use App\Models\SA\Assets\Portfolio\Models;
use App\Models\SA\Assets\Portfolio\ModelMapping;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Master\ME\HistInflation;
use App\Models\SA\Reference\Goal;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use Illuminate\Http\Request;
use Auth;

class GoalsSetupController extends AppController
{
    public $table = 'Investor\Financial\Planning\Goal\Investment';
    
    public function index()
    {
        return $this->db_result();
    }
    
    public function breakdown(Request $request)
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
    
    private function calc_process($request, $calc, $inv=0)
    {
        $n                  = 1;
        $growth             = $year = [];
        $investment         = !empty($request->input('investment')) ? $request->input('investment') : $inv;
        $investment_amt     = $investment;
        $projected_amt      = $investment * pow((1+$calc->exp_return_mth), $calc->time_horizon_mth);
        
        foreach ($calc->arrGrowth as $g)
        {
            $sum = 0;
            for ($i = 0; $i <= $calc->time_horizon; $i++)
            {
                if ($i == 0)
                {
                    $sum = 0;
                    if ($n == 1 )
                    {
                        $year[] = date('Y');
                    }
                }
                else
                {
                    switch ($n)
                    {
                        case 1 :
                            $sum    = $investment;
                            $year[] = date('Y', strtotime($i ." years"));
                            break;
                        case 2 :
                        case 3 :
                        case 4 :
                            $exp    = $n > 2 ? $n == 3 ? $calc->exp_return_min/12 : $calc->exp_return_max/12 : $calc->exp_return_mth;
                            $sum    = $investment * pow((1+$exp), (12 * $i));
                            break;
                    }
                }
                $growth[$g][] = $sum;
            }
            $n++;
        }
        return [
            'year'      => $year,
            'lumpsum'   => ['growth'            => $growth,
                            'investment'        => $investment,
                            'investment_amount' => $investment_amt,
                            'projected_amount'  => $projected_amt,
                            'total_return'      => $projected_amt - $investment_amt
                           ]
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
            $model_id           = $request->input('model_id');
            $calc               = $this->calc_item($request);
            $future_amount      = $calc->today_amount*pow((1+($calc->inflation_avg/100)), $calc->time_horizon);
            $allocweight        = AllocationWeight::where([['model_id', $model_id], ['is_active', 'Yes']])->orderBy('effective_date', 'desc')->first();
            $expected_return    = !empty($allocweight->allocation_weight_id) ? floatval($allocweight->expected_return_year) : 0;
            if (!empty($allocweight->allocation_weight_id))
            {
                $dataWeight = AllocationWeightDetail::select('m_portfolio_allocations_weights_detail.weight', 'b.product_id', 'b.product_name', 'b.asset_class_id', 'b.product_type', 'b.min_buy', 'b.allow_sip', 'c.asset_class_name', 'd.expected_return_year', 'd.expected_return_month', 'd.sharpe_ratio', 'd.standard_deviation')
                            ->join('m_products as b', 'm_portfolio_allocations_weights_detail.product_id', '=', 'b.product_id')
                            ->join('m_asset_class as c', 'b.asset_class_id', '=', 'c.asset_class_id')
                            ->leftJoin('t_products_scores as d', function($qry) { return $qry->on('b.product_id', '=', 'd.product_id')->where('d.is_active', 'Yes'); })
                            ->where([['m_portfolio_allocations_weights_detail.allocation_weight_id', $allocweight->allocation_weight_id], ['m_portfolio_allocations_weights_detail.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                            ->get();
            
                foreach ($dataWeight as $dw)
                {
                    $exp_rtn        = floatval($dw->expected_return_year);
                    $exp_rtn_mth    = floatval($dw->expected_return_month);
                    $future_value   = $future_amount * $dw->weight;
                    if ($dw->allow_sip)
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

                    $product[] = [
                        'asset_class_id'        => $dw->asset_class_id,
                        'asset_class_name'      => $dw->asset_class_name,
                        'expected_return_year'  => $exp_rtn,
                        'expected_return_month' => $exp_rtn_mth,
                        'future_value'          => $future_value,
                        'investment_amount'     => $inv_amt,
                        'investment_type'       => $dw->allow_sip ? 'SIP' : 'Lumpsum',
                        'product_id'            => $dw->product_id,
                        'product_name'          => $dw->product_name,
                        'purchase_allow'        => $purch_allow,
                        'sharpe_ratio'          => $dw->sharpe_ratio,
                        'total_investment'      => $total_inv,
                        'total_return'          => $total_rtn,
                        'volatility'            => $dw->standard_deviation,
                        'weight'                => floatval($dw->weight)
                    ];

                    $first_investment   += $inv_amt;
                    $investment_amount  += $total_inv;
                    $projected_amount   += $future_value;
                    $total_return       += $total_rtn;
                }
            }
            
            for ($i = 0; $i <= $calc->time_horizon; $i++)
            {
                $amt_year   = $prj_amt = 0;
                $nyear      = date('Y', strtotime('+'. $i .' years'));
                if (!empty($product))
                {
                    foreach ($product as $prd)
                    {
                        if ($prd['investment_type'] == 'Lumpsum')
                        {
                            $amt_year += $prd['investment_amount'];
                            if ($i > 0)
                                $prj_amt += $prd['investment_amount'] * pow(1 + $prd['expected_return_month'], (12  * $i));
                            else
                                $prj_amt += $prd['investment_amount'];
                        }
                        else
                        {
                            if ($i > 0)
                            {
                                $amt_year += $i == 0 ? $prd['investment_amount'] * 12 : $prd['investment_amount'] * $i * 12;
                                $prj_amt  += (($prd['investment_amount'] * (1 + $prd['expected_return_month'])) * (pow(1 + $prd['expected_return_month'], (12 * $i)) - 1)) / $prd['expected_return_month'];
                            }
                            else
                            {
                                $amt_year += $prd['investment_amount'];
                                $prj_amt  += $prd['investment_amount'];
                            }
                        }
                    }
                }
                $projected_growth['investment_amount'][$nyear]  = $amt_year;
                $projected_growth['projected_amount'][$nyear]   = $prj_amt;  
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
            
            /*if (empty($ct))
            {
                $lmp_investment     = $future_amount*(1/(pow((1+$calc->exp_return_mth), $calc->time_horizon_mth)));
                $lumpsum            = $this->calc_process($request, $calc, $lmp_investment);
                $calc_inv           = ['lumpsum' => $lumpsum['lumpsum'], 'year' => $lumpsum['year']];
            }
            else
            {
                $calc_inv           = $this->calc_process($request, $calc);
            }
            return $this->app_response('Calculator', array_merge([
                'future_amount'     => $future_amount,
                'inflation'         => $infla,
                
                'product'           => $product,
                'projected_return'  => $calc->exp_return*100
            ], $calc_inv));*/
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
                $asset      = $color = $weight = [];
                $qast       = AssetClass::select('m_asset_class.asset_class_id', 'asset_class_color', 'asset_class_name')
                            ->join('m_portfolio_allocations as b', 'm_asset_class.asset_class_id', '=', 'b.asset_class_id')
                            ->where([['b.model_id', $row->model_id], ['m_asset_class.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
                foreach ($qast as $dt)
                {
                    $qalloc     = AllocationWeight::selectRaw('expected_return_year, effective_date, max(b.weight) as weight')
                                ->join('m_portfolio_allocations_weights_detail as b', 'm_portfolio_allocations_weights.allocation_weight_id', '=', 'b.allocation_weight_id')
                                ->join('m_products as c', 'b.product_id', '=', 'c.product_id')
                                ->where([['m_portfolio_allocations_weights.model_id', $row->model_id], ['c.asset_class_id', $dt->asset_class_id], ['m_portfolio_allocations_weights.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                                ->groupBy('expected_return_year', 'effective_date')
                                ->orderBy('effective_date', 'desc')->first();
                    $asset[]    = $dt->asset_class_name;
                    $color[]    = $dt->asset_class_color;
                    $weight[]   = !empty($qalloc->expected_return_year) ? $qalloc->weight : 0;
                    $exp_rtn    = !empty($qalloc->expected_return_year) ? $qalloc->expected_return_year : 0;
                }
                $model[] = [
                    'asset'             => $asset,
                    'color'             => $color,
                    'expected_return'   => $exp_rtn,
                    'weight'            => $weight,
                    'model_id'          => $row->model_id,
                    'model_name'        => $row->model_name
                ];
            }
            return $this->app_response('Model', ['model' => $model]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function investor(Request $request)
    {
        try
        {
            $pfl    = Auth::user()->usercategory_name == 'Investor' ? [] : Profile::where([['profile_id', $request->profile_id], ['is_active', 'Yes']])->first();
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

    public function checkout(Request $request, $id = null)
    {
        
        try {
            $request->request->add([
                'goal_name'      =>  $this->get_name($request, 'goal_id', 'goal_name', 'SA\Reference\Goal'),
                'profile_name'   =>  $this->get_name($request, 'profile_id','profile_name','SA\Reference\Goal\KYC\RiskProfile\Profile'),
                'model_name'     =>  $this->get_name($request, 'model_id','model_name','SA\Reference\Goal\M_Model'),
                'investor_id'    =>  Auth::id(),
                'investor_name'  =>  Auth::user()->FullName,
                'sales_id'       =>  -1,
                'sales_name'     => '-',
                'goal_invest_date'=> $this->cpm_date()
            ]);

            return $this->cpm_save($request,$id);

        } catch (Exception $e) {
            return $this->api_catch($e);
        }
    }

    public function get_name($request, $field_id, $field_name, $model)
    {
        $filter = [
            'where' => [
                [$field_id, $request->input($field_id)]
            ]
        ];
        $response = $this->cpm_row($field_name, $filter, $model);
        return $response->original['data'];
    }
    
    public function table_child()
    {
        return [[
            'model'     => 'Investor\Portfolio\Goal\InvestmentDetail',
            'partial'   => [
                'pKey'  => 'goal_invest_id', 
                'id'    => 'product_id', 
                'name'  => 'product_name', 
                'tbl'   => 'm_products'
            ]
        ]];
    }

    public function save_summary(Request $request, $id = null)
    {
        try
        {
            $request->request->add(['investor_id' => Auth::id(), 'goal_invest_date' => $this->app_date()]);
            
            $id                 = $this->db_save($request, $id, ['res' => 'id']);
            $product_id         = $request->product_id;
            $amount             = $request->amount;
            $exp_rtn            = $request->expected_return_year;
            $target_allocation  = $request->target_allocation;
            $sharpe_ratio       = $request->sharpe_ratio;
            $treynor_ratio      = $request->treynor_ratio;
            $investment_type    = $request->investment_type;
            
            for ($i = 0; $i < count($product_id); $i++)
            {
                $data = [
                    'goal_invest_id'        => $id,
                    'product_id'            => $product_id[$i],
                    'amount'                => $amount[$i],
                    'expected_return_year'  => !empty($exp_rtn[$i]) ? $exp_rtn[$i] : null,
                    'target_allocation'     => !empty($target_allocation[$i]) ? $target_allocation[$i] : null,
                    'sharpe_ratio'          => !empty($sharpe_ratio[$i]) ? $sharpe_ratio[$i] : null,
                    'treynor_ratio'         => !empty($treynor_ratio[$i]) ? $treynor_ratio[$i] : null,
                    'investment_type'       => $investment_type[$i],
                    'created_by'            => Auth::id(),
                    'created_host'          => $request->ip
                ];
                InvestmentDetail::create($data);
            }
            return $this->app_partials(1, 0, ['id' => $id]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
        //return $this->db_save($request, $id);
    }
}
