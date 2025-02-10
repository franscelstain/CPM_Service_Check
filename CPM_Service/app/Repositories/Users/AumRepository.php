<?php

namespace App\Repositories\Users;

use App\Interfaces\Users\AumRepositoryInterface;
use App\Models\SA\Assets\AumTarget;
use Auth;
use DB;
use Illuminate\Support\Str;

class AumRepository implements AumRepositoryInterface
{
    public function getActiveAumTarget()
    {
        return AumTarget::where('is_active', 'Yes')
                ->where('effective_date', '<=', date('Y-m-d'))
                ->where('status_active', 'Active')
                ->orderByDesc('effective_date')
                ->first();
    }

    public function getInvestorsByCategoryWithCurrentBalance($query, $startDate, $targetAum)
    {
        $rankedPriorities = DB::table('u_investors_card_priorities')
                            ->select(
                                'cif',
                                'is_priority',
                                'pre_approve',
                                DB::raw('ROW_NUMBER() OVER (PARTITION BY cif ORDER BY card_expired DESC) AS rank')
                            )
                            ->where('is_active', 'Yes');

        return $query->leftJoinSub($rankedPriorities, 'uicp', function ($join) {
                        $join->on('ui.cif', '=', 'uicp.cif')
                            ->where('uicp.rank', 1);
                    })
                    ->whereDate('tao.outstanding_date', $startDate)                   
                    ->havingRaw('SUM(tao.balance_amount) <= ' . $targetAum);
    }

    public function getInvestorsByCategoryWithDowngradeBalance($baseQuery, $salesId, $startDate)
    {
        return $baseQuery->joinSub(function ($query) use ($startDate) {
                $query->select('investor_id', DB::raw('MAX(outstanding_date) as max_outstanding_date'))
                    ->from('t_assets_outstanding')
                    ->where('is_active', 'Yes')
                    ->whereDate('outstanding_date', '<', $startDate)
                    ->groupBy('investor_id');
            }, 'max_outstanding_dates', function ($join) {
                $join->on('tao.investor_id', '=', 'max_outstanding_dates.investor_id')
                    ->on('tao.outstanding_date', '=', 'max_outstanding_dates.max_outstanding_date');
            })
            ->where('ui.sales_id', $salesId)
            ->selectRaw("
                tao.investor_id,
                SUM(tao.balance_amount) AS downgrade_aum
            ")
            ->groupBy('tao.investor_id')
            ->get();
    }

    public function listAumPriority($baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort)
    {
        $query = $this->getInvestorsByCategoryWithCurrentBalance($baseQuery, $startDate, $targetAum);       
        
        if (!empty($search)) {
            $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($qry) use ($search, $like) {
                $qry->where('ui.cif', $like, "%$search%")
                    ->orWhere('ui.fullname', $like, "%$search%");
            });
        }

        if (!empty($colName) && !empty($colSort)) {
            if ($colName == 'cif') {
                $colName = 'ui.cif';
            }
            $query->orderBy($colName, $colSort);
        }
        
        return $query->where('ui.sales_id', $salesId)
                ->selectRaw("
                    ui.investor_id,
                    ui.cif,
                    ui.fullname,
                    uicp.is_priority,
                    uicp.pre_approve,
                    MAX(tao.outstanding_date) AS current_date,
                    SUM(tao.balance_amount) AS current_aum
                ")
                ->groupBy(
                    'ui.investor_id', 
                    'ui.cif', 
                    'ui.fullname',
                    'uicp.is_priority',
                    'uicp.pre_approve'
                )
                ->paginate($limit, ['*'], 'page', $page);
    }

    public function listDropFund($baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort)
    {
        $query = $this->getInvestorsByCategoryWithCurrentBalance($baseQuery, $startDate, $targetAum);        
        
        if (!empty($search)) {
            $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($qry) use ($search, $like) {
                $qry->where('ui.cif', $like, "%$search%")
                    ->orWhere('ui.fullname', $like, "%$search%");
            });
        }

        if (!empty($colName) && !empty($colSort)) {
            if ($colName == 'cif') {
                $colName = 'ui.cif';
            }
            $query->orderBy($colName, $colSort);
        }

        return $query->where('ui.sales_id', $salesId)
                ->selectRaw("
                    ui.cif,
                    ui.fullname,
                    uicp.is_priority,
                    uicp.pre_approve,
                    MAX(tao.outstanding_date) AS current_date,
                    SUM(tao.balance_amount) AS current_aum
                ")
                ->groupBy(
                    'ui.cif', 
                    'ui.fullname',
                    'uicp.is_priority',
                    'uicp.pre_approve'
                )
                ->paginate($limit, ['*'], 'page', $page);
    }
}
