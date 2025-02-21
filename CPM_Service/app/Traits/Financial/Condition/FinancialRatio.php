<?php

namespace App\Traits\Financial\Condition;

use App\Models\Financial\Condition\AssetLiability;
use App\Models\Financial\Condition\IncomeExpense;
use App\Models\SA\Master\FinancialCheckUp\Ratio;
use DB;

trait FinancialRatio
{
	private function fin_qry($type, $inv_id, $select='', $where=[])
	{
        $union	= $type == 'Assets' ? $this->assets_outstanding($type, $inv_id, $select, $where) : $this->liability_outstanding($inv_id, $select);
        $qry    = AssetLiability::join('m_financials', 't_assets_liabilities.financial_id', '=', 'm_financials.financial_id')
                ->where(array_merge([['t_assets_liabilities.investor_id', $inv_id], ['m_financials.financial_type', $type], ['t_assets_liabilities.is_active', 'Yes'], ['m_financials.is_active', 'Yes']], $where))
                ->where('t_assets_liabilities.amount', '>=', 1);
        
        return (object) ['qry' => $qry, 'union' => $union];
	}
    
    private function ratio($total, $inv=[], $score='N')
    {
        $al     = $total['assets-liabilities'];
        $ie     = $total['income-expense'];
        $ratio  = $this->ratio_published(true);
        $data   = [];
        $val    = 0;
        if (!empty($ratio))
        {
            foreach ($ratio as $rt)
            {
                switch ($rt->ratio_method)
                {
                    case 'DebtToAssetRatio'     : $res = $al->assets > 0 ? $al->liabilities / $al->assets : 0; break;
                    case 'DebtToIncome'         : $res = $ie->income > 0 ? $al->liabilities / $ie->income : 0; break;
                    case 'EmergencyRatio'       : $res = $ie->expense > 0 ? $al->assets / $ie->expense : 0; break;
                    case 'InsuranceCover'       : 
                        $insurance  = $this->total_with_name($inv->investor_id, 'Insurance', 'Assets', true);
                        $res        = $al->assets > 0 ? $insurance / $al->assets : 0; 
                        break;
                    case 'SavingToIncomeRatio'  : 
                        $saving = $this->total_with_name($inv->investor_id, 'Saving', 'Assets', true);
                        $res    = $ie->income > 0 ? $saving / $ie->income : 0; 
                        break;
                    //case 'SolvencyRatio'        : $res = $al->liabilities > 0 ? $ie->income / $al->liabilities : 0; break;
                    default                     : $res = 0;
                        
                }
                
                $rtp    = $rt->ratio_type == 'Percent' ? '%' : '';
                $res    = $rt->ratio_type == 'Percent' ? $res * 100 : $res;
                $rt_opr = $this->ratio_operator($rt, $res, $rtp);
                $val   += $rt_opr['score'];
                $data[] = [
                    'description'   => $rt->description,
                    'ideal'         => $rt->perfect_value . $rtp,
                    'ratio'         => $rt_opr,
                    'ratio_method'  => $rt->ratio_method,
                    'ratio_name'    => $rt->ratio_name
                ];
            }
            $val = $val/count($ratio);
        }
        return $score == 'Y' ? floor($val) != $val ? number_format($val, 1) : $val : $data;
    }
    
    private function ratio_operator($dt, $res, $rtp)
    {
        $val        = 0;
        $color      = 'danger';
        $status     = 'bad';
        $operator   = ['perfect' => 10, 'warning' => 5, 'bad' => 0];
        foreach ($operator as $opr => $opn)
        {
            $opr_k  = $opr.'_operator';
            $opr_v  = $opr.'_value';
            $opr_v2 = $opr.'_value2';
            switch ($dt->$opr_k)
            {
                case 'Equal' :
                    $math = $dt->$opr_v == $res ? true : false;
                    if ($opr == 'perfect') { $opt = '='; }
                    break;
                case 'Less than' :
                    $math = $res < $dt->$opr_v ? true : false;
                    if ($opr == 'perfect') { $opt = '<'; }
                    break;
                case 'Less than equal to' :
                    $math = $res <= $dt->$opr_v ? true : false;
                    if ($opr == 'perfect') { $opt = '<='; }
                    break;
                case 'Greater than' :
                    $math = $res > $dt->$opr_v ? true : false;
                    if ($opr == 'perfect') { $opt = '>'; }
                    break;
                case 'Greater than equal to' :
                    $math = $res >= $dt->$opr_v ? true : false;
                    if ($opr == 'perfect') { $opt = '>='; }
                    break;
                case 'Between' :
                    $math = $res >= $dt->$opr_v && $res <= $dt->$opr_v2 ? true : false;
                    if ($opr == 'perfect') { $opt = '-'; }
                    break;
                default :
                    $math = false;
                    if ($opr == 'perfect') { $opt = ''; }
            }
            
            if ($math)
            {
                $val    = $opr != 'bad' ? $opn : 0;
                $status = $opr;
                $color  = $status != 'bad' ? $status == 'perfect' ? 'success' : 'warning' : 'danger';
            }
        }
        return ['color' => $color, 'operator' => $opt, 'result' => number_format($res, 2) . $rtp, 'status' => ucwords($status), 'score' => $val];
    }
    
    public function ratio_published($res = false)
    {
        try
        {
            $ratio = Ratio::where([['effective_date', '<=', $this->app_date()], ['published', 'Yes'], ['is_active', 'Yes']])->orderBy('sequence_to', 'asc')->get();
            return !$res ? $this->app_response('Ratio Published', ['key' => 'ratio_id', 'list' => $ratio]) : $ratio;
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function total_assets_liabilities($request, $inv_id, $typ = [], $result = false)
    {
        try
        {
            $sum    = [];
            $total  = 0;
            $typ    = !empty($request->input('type')) ? $request->input('type') : $typ;
            foreach ($typ as $t)
            {
                if ($t != end($typ))
                {
                    if ($t == 'assets')
                    {
                        $where      = !empty($request->liquidity) && $request->liquidity == 'yes' ? [['m_financials.is_liquidity', true]] : [];
                        $fin_asset  = $this->fin_qry('Assets', $inv_id, 'balance_amount as amount', $where);
                        $res        = $fin_asset->qry->selectRaw('amount')->unionAll($fin_asset->union)->sum('amount');
                    }
                    elseif ($t == 'liabilities')
                    {
                        $fin_liability  = $this->fin_qry('Liabilities', $inv_id, 'outstanding_balance as amount');
                        $res	        = $fin_liability->qry->selectRaw('amount')->unionAll($fin_liability->union)->sum('amount');
                    }
                    $total  = $total == 0 ? $res : $total - $res;
                }
                 $sum[$t] = $t == end($typ) ? floatval($total) : floatval($res);
            }
            return !$result ? $this->app_response('Total Assets & Liabilities', $sum) : (object) $sum;
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function total_income_expense($request, $inv_id, $typ = [], $result = false)
    {
        try
        {
            $sum    = [];
            $total  = 0;
            $typ    = !empty($request->input('type')) ? $request->input('type') : $typ;
            foreach ($typ as $t)
            {
                if ($t != end($typ))
                {
                    $res    = 0;
                    $data   = IncomeExpense::join('m_financials as b', 't_income_expense.financial_id', '=', 'b.financial_id')
                            ->where([['investor_id', $inv_id], ['b.financial_type', ucwords($t)], ['t_income_expense.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
                    foreach ($data as $dt)
                    {
                        //$res += $dt->period_of_time == 'Yearly' ? $dt->amount : $dt->amount * 12;

                 		if (!empty($request->sum_type) && $request->sum_type == 'month')
                            $res += $dt->period_of_time == 'Yearly' ? $dt->amount/12 : $dt->amount;
                        else
                            $res += $dt->period_of_time == 'Yearly' ? $dt->amount : $dt->amount * 12;
                    }                    
                    $total = $total == 0 ? $res : $total - $res;
                }
                $sum[$t] = $t == end($typ) ? $total : $res;
            }
            return !$result ? $this->app_response('Total Income & Expense', $sum) : (object) $sum;
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function total_with_name($inv_id, $name, $typ, $res = false)
    {
        try
        {
            if ($typ == 'Assets')
            {
                $fin_asset  = $this->fin_qry('Assets', $inv_id, 'balance_amount as amount', [['financial_name', $name]]);
                $assets = DB::table('t_assets_liabilities as tal')
                    ->join('m_financials as mf', 'mf.financial_id', 'tal.financial_id')
                    ->where([['tal.investor_id', $inv_id], ['mf.financial_type', 'Assets'], ['tal.is_active', 'Yes'], 
                            ['mf.is_active', 'Yes'], ['mf.financial_name', $name], ['tal.amount', '>=', 1]])
                    ->sum('tal.amount');

                $assetsAmount = DB::table('t_assets_outstanding as tao')
                    ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                    ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->join('m_financials_assets as mfa', 'mfa.asset_class_id', 'mac.asset_class_id')
                    ->join('m_financials as mf', 'mf.financial_id', 'mfa.financial_id')
                    ->where([['tao.investor_id', $inv_id], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], 
                            ['mfa.is_active', 'Yes'], ['mf.is_active', 'Yes'], ['mf.financial_type', 'Assets'],
                            ['tao.outstanding_date', DB::raw('CURRENT_DATE')], ['tao.balance_amount', '>=', 1],
                            ['mf.financial_name', $name]])
                    ->sum('tao.balance_amount');

                $sum = $assets + $assetsAmount;
            }
            elseif ($typ == 'Liabilities')
            {
                $liabilities = DB::table('t_assets_liabilities as tal')
                    ->join('m_financials as mf', 'mf.financial_id', 'tal.financial_id')
                    ->where([['tal.investor_id', $inv_id], ['mf.financial_type', 'Liabilities'], ['tal.is_active', 'Yes'], 
                            ['mf.is_active', 'Yes'], ['mf.financial_name', $name]])
                    ->sum('tal.amount');
                
                $liabilitiesAmount = DB::table('t_liabilities_outstanding')
                    ->where([['investor_id', $inv_id], ['is_active', 'Yes'], ['outstanding_date', DB::raw('CURRENT_DATE')]])
                    ->sum('outstanding_balance');

                $sum = $liabilities + $liabilitiesAmount;
            }
            elseif ($typ == 'Expense' || $typ == 'Income')
            {
                $ie     = DB::table('t_income_expense as tie')
                        ->join('m_financials as mf', 'mf.financial_id', 'tie.financial_id')
                        ->where([['tie.investor_id', $inv_id], ['mf.financial_name', $name], ['mf.financial_type', $typ], 
                            ['tie.is_active', 'Yes'], ['mf.is_active', 'Yes']])
                        ->select('tie.investor_id', DB::raw("SUM(CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END) as total"))
                        ->groupBy('tie.investor_id')
                        ->first();
                $sum    = $ie->total ?? 0;
            }
            return !$res ? $this->app_response('Total', floatval($sum)) : floatval($sum);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}