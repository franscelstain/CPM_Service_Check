<?php

namespace App\Http\Controllers\Financial\Planning;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Portfolio\AllocationWeight;
use App\Models\SA\Assets\Portfolio\AllocationWeightDetail;
use App\Models\SA\Master\ME\HistInflation;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use Illuminate\Http\Request;
use Auth;

class RetirementController extends AppController
{
    public function backtest(Request $request)
    {
        try
        {
            $product    = $request->product_id;
            $weight     = $request->weight;
            $prd        = [];
            
            for ($i = 0; $i < count($product); $i++)
                $prd[$product[$i]] = floatval($weight[$i]);
            
            $api = $this->api_ws(['sn' => 'Backtest', 'key' => ['token'], 'val' => [intval($request->model_id), 10000000, $prd]])->original['data'];
            return $this->app_response('Backtesting', $api);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function calculator(Request $request)
    {
        try
        {
            $ret_prep           = $request->retirement_age - $request->current_age;
            $ret_prep_m         = $ret_prep*12;
            $dur_aft_ret        = $request->life_expectancy - $request->retirement_age;
            $current_mth_exp    = floatval($request->current_monthly_expense);
            $exp_inf_upto_ret   = $request->expected_inflation_upto_retirement/100;
            $exp_inf_aft_ret    = $request->expected_inflation_after_retirement/100;
            $exp_rtn_upto_ret   = $request->expected_return_upto_retirement/100;
            $exp_rtn_upto_ret_m = $exp_rtn_upto_ret/12;
            $exp_mth_first_year = $current_mth_exp * pow(1+$exp_inf_upto_ret, $ret_prep);
            $eff_inf_calc       = $exp_mth_first_year * pow(1+$exp_inf_aft_ret, 1+$dur_aft_ret);
            $total_req_ret      = (($exp_mth_first_year*12) * ($exp_inf_aft_ret+1)) * (pow(1+$exp_inf_aft_ret, 1+$dur_aft_ret)-1) / $exp_inf_aft_ret;
            $starting_invest    = ($total_req_ret*$exp_rtn_upto_ret_m) / ((1+$exp_rtn_upto_ret_m)*(pow(1+$exp_rtn_upto_ret_m, $ret_prep_m)-1));
            $inv_amt            = $starting_invest * $ret_prep_m;
            $projected_amt      = ($starting_invest * (1+$exp_rtn_upto_ret_m)) * (pow(1+$exp_rtn_upto_ret_m, $ret_prep_m)-1) / $exp_rtn_upto_ret_m;
            $product            = $this->calculator_product((object) ['model' => $request->model_id, 'req' => $request->input(), 'realized_goals' => $total_req_ret, 'preparation' => $ret_prep*12]);
            $year               = $this->calculator_year((object) ['ret_prep' => $ret_prep, 'dur_aft_ret' => $dur_aft_ret, 'exp_rtn' => $exp_rtn_upto_ret_m, 'projected_amount' => $projected_amt, 'starting_invest' => $starting_invest, 'exp_inf_aft_ret' => $exp_inf_aft_ret, 'exp_mth_first_year' => $exp_mth_first_year]);
            
            $data = [
                'current_monthly_expense'           => $current_mth_exp,
                'duration_after_retirement'         => $dur_aft_ret,
                'effective_inflation_calculator'    => $eff_inf_calc,
                'expected_return'                   => $product->expected_return,
                'first_investment'                  => $product->first_investment,
                'inflation_avg'                     => $product->inflation_avg,
                'inflation_year'                    => $product->inflation_year,
                'investment_amount'                 => $inv_amt,
                'projected_amount'                  => $projected_amt,
                'product'                           => $product->product,
                'realized_goals'                    => $total_req_ret,
                'retirement_preparation'            => $ret_prep,
                'starting_invest'                   => $starting_invest,
                'total_return'                      => $projected_amt - $inv_amt,
                'year'                              => $year
            ];            
            
            return $this->app_response('Retirement Calculator', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function calculator_product($calc)
    {
        $product        = [];
        $exprtn         = $total = 0;
        $allocweight    = AllocationWeight::where([['model_id', $calc->model], ['is_active', 'Yes']])->orderBy('effective_date', 'desc')->first();
        $t_inf          = HistInflation::where('is_active', 'Yes')->whereNotNull('avg_inflation')->orderBy('year', 'desc')->first();
        if (!empty($allocweight->allocation_weight_id))
        {
            $exprtn     = !empty($allocweight->allocation_weight_id) ? floatval($allocweight->expected_return_year) : 0;
            $dataWeight = AllocationWeightDetail::select('m_portfolio_allocations_weights_detail.weight', 'b.product_id', 'b.product_name', 'b.asset_class_id', 'b.product_type', 'b.min_buy', 'b.allow_sip', 'c.asset_class_name', 'd.expected_return_year', 'd.expected_return_month', 'd.sharpe_ratio', 'd.standard_deviation', 'e.issuer_logo')
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
                $future_value   = $calc->realized_goals * $dw->weight;
                
                if ($dw->allow_sip)
                {
                    $inv_amt    = ($future_value * $exp_rtn_mth) / ((1 + $exp_rtn_mth) * (pow(1 + $exp_rtn_mth, $calc->preparation) - 1));
                    $total_inv  = $inv_amt * $calc->preparation;
                }
                else
                {
                    $inv_amt    = $future_value / pow(1 + $exp_rtn_mth, $calc->preparation);
                    $total_inv  = $inv_amt;
                }
                
                if (!empty($inv_amt))
                {
                    $product[] = [
                        'asset_class_name'      => $dw->asset_class_name,
                        'expected_return_year'  => floatval($dw->expected_return_year),
                        'investment_amount'     => $inv_amt,
                        'investment_type'       => $dw->allow_sip ? 'SIP' : 'Lumpsum',
                        'issuer_logo'           => $dw->issuer_logo,
                        'product_id'            => $dw->product_id,
                        'product_name'          => $dw->product_name,
                        'weight'                => floatval($dw->weight)
                    ];
                    $total += $inv_amt;
                }
                
                for ($i = 0; $i < count($product); $i++)
                    $product[$i]['current_allocation'] = $product[$i]['investment_amount']/$total;
            }
        }
        return (object) ['expected_return' => $exprtn, 'first_investment' => $total, 'inflation_avg' => floatval($t_inf->avg_inflation), 'inflation_year' => $t_inf->year, 'product' => $product];
    }
    
    private function calculator_year($calc)
    {
        $n      = $calc->ret_prep + $calc->dur_aft_ret + 1;
        $now    = intval(date('Y'));
        $year   = [];
        for ($i = 0; $i <= $n; $i++)
        {
            if ($i <= $calc->ret_prep)
            {
                $sum = $i == 0 ? $calc->starting_invest : ($calc->starting_invest*(1+$calc->exp_rtn))*(pow(1+$calc->exp_rtn, $i*12)-1)/$calc->exp_rtn;
            }
            else
            {
                $amt = $i-$calc->ret_prep == 1 ? $calc->projected_amount : $sum;
                $sum = $amt - ($calc->exp_mth_first_year*pow(1+$calc->exp_inf_aft_ret, $i-$calc->ret_prep)*12);
            }
            
            $yr         = $now+$i;
            $year[$yr]  = $sum > 0 ? $sum : 0;
        }
        return $year;
    }

    public function save_checkout(Request $request, $id = null)
    {
        try
        {
            $res                    = [];
            $success                = $fail = 0;
            $inv_id                 = Auth::id();
            $cif                    = Auth::user()->cif;
            $sn                     = substr($cif, -5) . date('ymd');
            $qry_trs                = TransactionHistory::where([['investor_id', $inv_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
            $rn                     = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
            $trans_ref              = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
            $type_ref               = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'SUB']]], 'SA\Transaction\Reference')->original['data'];
            $portf_id               = 3 . $sn . str_pad(1, 3, '0', STR_PAD_LEFT);
            
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
            $investment_type        = $request->investment_type;
            $percentage             = $request->percentage;
            
            for ($i = 0; $i < count($product_id); $i++)
            {
                $ref_no = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);
                $data   = [
                    'investor_id'           => $inv_id,
                    'portfolio_id'          => $portf_id,
                    'reference_no'          => $ref_no,
                    'trans_reference_id'    => $trans_ref,
                    'type_reference_id'     => $type_ref,
                    'transaction_date'      => !empty($transaction_date[$i]) ? $transaction_date[$i] : null,
                    'product_id'            => $product_id[$i],
                    'fee_product_id'        => !empty($fee_product_id[$i]) ? $fee_product_id[$i] : null,
                    'investor_account_id'   => !empty($investor_account_id[$i]) ? $investor_account_id[$i] : null,
                    'debt_date'             => !empty($debt_date[$i]) ? $debt_date[$i] : null,
                    'amount'                => !empty($amount[$i]) ? $amount[$i] : null,
                    'net_amount'            => $net_amount[$i],
                    'fee_amount'            => !empty($fee_amount[$i]) ? $fee_amount[$i] : null,
                    'tax_amount'            => !empty($tax_amount[$i]) ? $tax_amount[$i] : null,
                    'percentage'            => !empty($percentage[$i]) ? $percentage[$i] : null,
                    'investment_type'       => $investment_type[$i],
                    'created_by'            => $manager->user,
                    'created_host'          => $manager->ip
                ];
                
                $save = TransactionHistory::create($data);
                if ($save)
                {
                    $res[] = ['product_id' => $product_id[$i], 'ref_no' => $ref_no];
                    $success++;
                }
                else
                {
                    $fail++;
                }                
                
                $rn++;
            }
            return $this->app_partials($success, $fail, [$res, 'session_forget' => 'retirement_planning']);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}