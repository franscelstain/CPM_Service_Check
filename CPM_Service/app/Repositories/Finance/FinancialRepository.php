<?php

namespace App\Repositories\Finance;

use App\Interfaces\Finance\FinancialRepositoryInterface;
use Illuminate\Support\Collection;
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

    public function getFinancialSummary(array $investorIds): Collection
    {
        $summary = DB::table('u_investors as ui')
            ->leftJoin('t_income_expense as tie', 'tie.investor_id', '=', 'ui.investor_id')
            ->leftJoin('m_financials as mfi', 'mfi.financial_id', '=', 'tie.financial_id')
            ->leftJoin('t_assets_liabilities as tal', 'tal.investor_id', '=', 'ui.investor_id')
            ->leftJoin('m_financials as mfa', 'mfa.financial_id', '=', 'tal.financial_id')
            ->whereIn('ui.investor_id', $investorIds)
            ->where('ui.is_active', 'Yes')
            ->groupBy('ui.investor_id')
            ->select(
                'ui.investor_id',
                DB::raw("SUM(CASE WHEN mfi.financial_type = 'Income' THEN CASE WHEN tie.period_of_time = 'Monthly' THEN tie.amount * 12 ELSE tie.amount END ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN mfi.financial_type = 'Expense' THEN CASE WHEN tie.period_of_time = 'Monthly' THEN tie.amount * 12 ELSE tie.amount END ELSE 0 END) as expense"),
                DB::raw("SUM(CASE WHEN mfa.financial_type = 'Assets' THEN tal.amount ELSE 0 END) as assets"),
                DB::raw("SUM(CASE WHEN mfa.financial_type = 'Liabilities' THEN tal.amount ELSE 0 END) as liabilities")
            )
            ->get()
            ->keyBy('investor_id');

        if ($summary->isEmpty()) {
            return collect();
        }

        $ids = implode(',', array_map('intval', $investorIds));

        $assetOutstanding = DB::select("SELECT investor_id, SUM(balance_amount) as assets_outstanding
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.investor_id, tao.balance_amount
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON tao.product_id = mp.product_id
                JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id
                JOIN m_asset_categories mact ON mac.asset_category_id = mact.asset_category_id
                WHERE tao.investor_id IN ($ids)
                    AND tao.outstanding_date = CURRENT_DATE
                    AND tao.balance_amount >= 1
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, tao.data_date DESC, tao.outstanding_id DESC
            ) AS filtered
            GROUP BY investor_id");

        $liabilityOutstanding = DB::select("SELECT investor_id, SUM(outstanding_balance) as liabilities_outstanding
            FROM (
                SELECT DISTINCT ON (tlo.investor_id, tlo.liabilities_id)
                    tlo.investor_id, tlo.outstanding_balance
                FROM t_liabilities_outstanding tlo
                JOIN u_investors ui ON tlo.investor_id = ui.investor_id
                WHERE tlo.investor_id IN ($ids)
                    AND tlo.outstanding_date = CURRENT_DATE
                    AND tlo.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                ORDER BY tlo.investor_id, tlo.liabilities_id, tlo.data_date DESC, tlo.liabilities_outstanding_id DESC
            ) AS filtered
            GROUP BY investor_id");

        foreach ($assetOutstanding as $item) {
            $id = $item->investor_id;
            $summary[$id] = $summary[$id] ?? (object) [
                'investor_id' => $id,
                'income' => 0,
                'expense' => 0,
                'assets' => 0,
                'liabilities' => 0
            ];
            $summary[$id]->assets += $item->assets_outstanding;
        }

        foreach ($liabilityOutstanding as $item) {
            $id = $item->investor_id;
            $summary[$id] = $summary[$id] ?? (object) [
                'investor_id' => $id,
                'income' => 0,
                'expense' => 0,
                'assets' => 0,
                'liabilities' => 0
            ];
            $summary[$id]->liabilities += $item->liabilities_outstanding;
        }

        return collect($summary)->values();
    }

    public function getAssetTotalByName(int $investorId, string $name): float
    {
        $assets = DB::table('t_assets_liabilities as tal')
            ->join('m_financials as mf', 'mf.financial_id', 'tal.financial_id')
            ->where([
                ['tal.investor_id', $investorId],
                ['mf.financial_type', 'Assets'],
                ['tal.is_active', 'Yes'],
                ['mf.is_active', 'Yes'],
                ['mf.financial_name', $name],
                ['tal.amount', '>=', 1]
            ])
            ->sum('tal.amount');

        $assetsAmount = DB::selectOne("SELECT SUM(balance_amount) AS total_balance_amount
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.balance_amount
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON tao.product_id = mp.product_id
                JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id
                JOIN m_asset_categories mact ON mac.asset_category_id = mact.asset_category_id
                JOIN m_financials_assets mfa ON mac.asset_class_id = mfa.asset_class_id
                JOIN m_financials mf ON mfa.financial_id = mf.financial_id
                WHERE tao.investor_id = ?
                    AND tao.outstanding_date = CURRENT_DATE
                    AND tao.balance_amount >= 1
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                    AND mfa.is_active = 'Yes'
                    AND mf.is_active = 'Yes'
                    AND mf.financial_type = 'Assets'
                    AND mf.financial_name = ?
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, tao.data_date DESC, tao.outstanding_id DESC
            ) AS filtered", [$investorId, $name]);

        return (float) $assets + (float) ($assetsAmount->total_balance_amount ?? 0);
    }
}
