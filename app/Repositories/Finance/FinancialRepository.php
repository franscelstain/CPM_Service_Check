<?php

namespace App\Repositories\Finance;

use App\Interfaces\Finance\FinancialRepositoryInterface;
use DB;

class FinancialRepository implements FinancialRepositoryInterface
{
    public function getAssetsByInvestorId(array $investorIds)
    {
        return DB::table('t_assets_liabilities as tal')
            ->join('m_financials as mf', 'mf.financial_id', '=', 'tal.financial_id')
            ->where([['mf.financial_type', 'Assets'], ['tal.is_active', 'Yes'], ['mf.is_active', 'Yes']])
            ->where('tal.amount', '>=', 1)
            ->whereIn('tal.investor_id', $investorIds)
            ->select('tal.investor_id', DB::raw("SUM(tal.amount) as total"))
            ->groupBy('tal.investor_id')
            ->get();
    }

    public function getAssetsAmountByInvestorId(array $investorIds)
    {
        return DB::table('t_assets_outstanding as tao')
            ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
            ->join('m_financials_assets as mfa', 'mfa.asset_class_id', 'mac.asset_class_id')
            ->join('m_financials as mf', 'mf.financial_id', 'mfa.financial_id')
            ->whereIn('tao.investor_id', $investorIds)
            ->where([['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], 
                    ['mfa.is_active', 'Yes'], ['mf.is_active', 'Yes'], ['mf.financial_type', 'Assets'],
                    ['tao.outstanding_date', DB::raw('CURRENT_DATE')], ['tao.balance_amount', '>=', 1]])
            ->select('tao.investor_id', DB::raw("SUM(tao.balance_amount) as total"))
            ->groupBy('tao.investor_id');
    }

    public function getExpenseByInvestorId(array $investorIds)
    {
        return DB::table('t_income_expense as tie')
            ->join('m_financials as mf', 'mf.financial_id', '=', 'tie.financial_id')
            ->where([['mf.financial_type', 'Expense'], ['tie.is_active', 'Yes'], ['mf.is_active', 'Yes']])
            ->whereIn('tie.investor_id', $investorIds)
            ->select('tie.investor_id', DB::raw("SUM(CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END) as total"))
            ->groupBy('tie.investor_id')
            ->get();
    }

    public function getIncomeByInvestorId(array $investorIds)
    {
        return DB::table('t_income_expense as tie')
            ->join('m_financials as mf', 'mf.financial_id', '=', 'tie.financial_id')
            ->where([['mf.financial_type', 'Income'], ['tie.is_active', 'Yes'], ['mf.is_active', 'Yes']])
            ->whereIn('tie.investor_id', $investorIds)
            ->select('tie.investor_id', DB::raw("SUM(CASE WHEN tie.period_of_time = 'Yearly' THEN tie.amount ELSE tie.amount * 12 END) as total"))
            ->groupBy('tie.investor_id')
            ->get();
    }

    public function getLiabilitiesByInvestorId(array $investorIds)
    {
        return DB::table('t_assets_liabilities as tal')
            ->join('m_financials as mf', 'mf.financial_id', '=', 'tal.financial_id')
            ->where([['mf.financial_type', 'Liabilities'], ['tal.is_active', 'Yes'], ['mf.is_active', 'Yes']])
            ->where('tal.amount', '>=', 1)
            ->whereIn('tal.investor_id', $investorIds)
            ->select('tal.investor_id', DB::raw("SUM(tal.amount) as total"))
            ->groupBy('tal.investor_id')
            ->get();
    }

    public function getLiabilitiesAmountByInvestorId(array $investorIds)
    {
        return DB::table('t_liabilities_outstanding')
            ->whereIn('investor_id', $investorIds)
            ->where([['is_active', 'Yes'], ['outstanding_date', DB::raw('CURRENT_DATE')]])
            ->select('investor_id', DB::raw("SUM(outstanding_balance) as total"))
            ->groupBy('investor_id');
    }
}
