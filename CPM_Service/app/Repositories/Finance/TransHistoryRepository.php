<?php

namespace App\Repositories\Finance;

use App\Interfaces\Finance\TransHistoryRepositoryInterface;
use DB;

class TransHistoryRepository implements TransHistoryRepositoryInterface
{
    public function getBalanceGoalByInvestor(array $investorIds) {
        return DB::table('t_trans_histories_days as thd')
                ->whereIn('thd.investor_id', $investorIds)
                ->whereDate('thd.history_date', DB::raw('CURRENT_DATE'))
                ->where([['thd.is_active', 'Yes'], [DB::raw("LEFT(thd.portfolio_id, 1)"), '2']])
                ->select('thd.investor_id', DB::raw("SUM(thd.current_balance) AS balance"))
                ->groupBy('thd.investor_id')
                ->get();
    }
}