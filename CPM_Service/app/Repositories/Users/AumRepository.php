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

    public function getInvestorsByCategoryWithCurrentBalance($query, $startDate)
    {
        return $query->leftJoin(DB::raw("(
                        SELECT DISTINCT ON (cif)
                            cif,
                            is_priority,
                            pre_approve
                        FROM u_investors_card_priorities
                        WHERE is_active = 'Yes'
                        ORDER BY cif, card_expired DESC
                    ) as uicp"), 'ui.cif', '=', 'uicp.cif')
                    ->whereDate('tao.outstanding_date', $startDate)
                    ->selectRaw('DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                        tao.investor_id,
                        ui.cif,
                        ui.fullname,
                        tao.balance_amount,
                        tao.data_date,
                        uicp.is_priority,
                        uicp.pre_approve,
                        ui.is_enable
                    ');
    }

    public function getInvestorsByCategoryWithDowngradeBalance($baseQuery, $investorIds, $startDate)
    {
        $baseQuery->whereIn('tao.investor_id', $investorIds)
            ->whereDate('tao.outstanding_date', '<', $startDate)
            ->selectRaw("
                tao.investor_id,
                tao.balance_amount
            ");
        
        return DB::table(DB::raw("({$baseQuery->toSql()}) as a"))
                ->mergeBindings($baseQuery)
                ->selectRaw("
                    investor_id,
                    SUM(balance_amount) AS downgrade_aum
                ")
                ->groupBy('investor_id')
                ->get();
    }

    public function listAumPriority($baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort)
    {
        $subquery = $this->getInvestorsByCategoryWithCurrentBalance($baseQuery, $startDate);       
        
        $subquery->where('ui.sales_id', $salesId)
                ->when(!empty($search), function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('ui.cif', 'like', "%$search%")
                        ->orWhere('ui.fullname', 'like', "%$search%");
                    });
                });
        
        return DB::table(DB::raw("({$subquery->toSql()}) as a"))
                ->mergeBindings($subquery)
                ->selectRaw("
                    investor_id,
                    cif,
                    fullname,
                    is_priority,
                    pre_approve,
                    MAX(data_date) AS current_date,
                    SUM(balance_amount) AS current_aum
                ")
                ->groupBy(
                    'investor_id', 
                    'cif', 
                    'fullname',
                    'is_priority',
                    'pre_approve'
                )           
                ->havingRaw('SUM(balance_amount) <= ' . $targetAum)
                ->when(!empty($colName) && !empty($colSort) && in_array($colName, ['cif', 'fullname', 'current_aum']), function ($q) use ($colName, $colSort) {
                    $q->orderBy($colName, $colSort);
                })
                ->paginate($limit, ['*'], 'page', $page);
    }

    public function listDropFund($baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort)
    {
        $subquery = $this->getInvestorsByCategoryWithCurrentBalance($baseQuery, $startDate);
        
        $subquery->where('ui.sales_id', $salesId)
                ->when(!empty($search), function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('ui.cif', 'like', "%$search%")
                        ->orWhere('ui.fullname', 'like', "%$search%");
                    });
                });

        return DB::table(DB::raw("({$subquery->toSql()}) as a"))
                ->mergeBindings($subquery)
                ->selectRaw("
                    cif,
                    fullname,
                    is_enable,
                    is_priority,
                    pre_approve,
                    MAX(data_date) AS current_date,
                    SUM(balance_amount) AS current_aum
                ")
                ->groupBy(
                    'cif', 
                    'fullname',
                    'is_enable',
                    'is_priority',
                    'pre_approve'
                )
                ->havingRaw('SUM(balance_amount) <= ' . $targetAum)                
                ->when(!empty($colName) && !empty($colSort), function ($q) use ($colName, $colSort) {
                    $q->orderBy($colName, $colSort);
                })
                ->paginate($limit, ['*'], 'page', $page);
    }
}
