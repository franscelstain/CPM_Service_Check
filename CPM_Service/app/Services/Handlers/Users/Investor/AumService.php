<?php

namespace App\Services\Handlers\Users\Investor;

use App\Interfaces\Finance\AssetOutstandingRepositoryInterface;
use App\Interfaces\Users\AumRepositoryInterface;
use Auth;
use Illuminate\Support\Str;

class AumService
{
    protected $assetRepo;
    protected $aumRepo;

    public function __construct(AssetOutstandingRepositoryInterface $assetRepo, AumRepositoryInterface $aumRepo)
    {
        $this->assetRepo = $assetRepo;
        $this->aumRepo = $aumRepo;
    }

    public function getAumPriorityData($search, $limit, $page, $colName, $colSort)
    {
        $user = Auth::guard('admin')->user();
        $salesId = $user->id ?? null;
        $aum = $this->aumRepo->getActiveAumTarget();
        if (!$aum && !empty($aum->asset_category)) {
            return ['item' => [], 'total' => 0, 'totalFiltered' => 0];
        }

        $categoryIds = (array) $aum->asset_category ?? [];
        $targetAum = $aum->target_aum ?? 0;
        $aumDate = date('Y-m-t', strtotime('last month'));

        // Get investors
        $latestDataCurrent = $this->assetRepo->latestDataDate($aumDate);
        $baseQueryCurrent = $this->assetRepo->baseQueryInvestorsByCategoryWithCurrentBalance($latestDataCurrent, $categoryIds);
        $currentData = $this->aumRepo->listAumPriority($baseQueryCurrent, $salesId, $aumDate, $targetAum, $search, $limit, $page, $colName, $colSort);
        $total = !empty($search) ? $this->assetRepo->countInvestorsByCategoryWithCurrentBalance($latestDataCurrent, $aumDate, $categoryIds, $targetAum, $salesId) : $currentData->total();
        if ($currentData->total() > 0) {
            $latestDataDown = $this->assetRepo->latestDataDateDowngrade($aumDate);
            $baseQueryDown = $this->assetRepo->baseQueryInvestorsByCategoryWithCurrentBalance($latestDataDown, $categoryIds);
            $downgradeData = $this->aumRepo->getInvestorsByCategoryWithDowngradeBalance($baseQueryDown, $salesId, $aumDate);
            $currentData->map(function ($item) use ($downgradeData) {
                $downData = $downgradeData->firstWhere('investor_id', $item->investor_id);
                $item->current_aum = (float) $item->current_aum ?? 0;

                
                $item->target_days = $item->current_date ? (strtotime(date('Y-m-d')) - strtotime($item->current_date)) / (60 * 60 * 24) : null;
                
                if ($item->is_priority && !$item->pre_approve) {
                    $item->downgrade_date = isset($item->current_date) ? date('Y-m-d', strtotime($item->current_date . ' +9 months')) : null;
                } else {
                    $item->downgrade_date = null;
                }
                
                if ($downData) {
                    $item->downgrade_aum = (float) $downData->downgrade_aum ?? null;
                } else {
                    $item->downgrade_aum = null; // Atur nilai default
                }
            });
        }

        return [
            'item' => $currentData->items(),
            'current_page' => $currentData->currentPage(),
            'last_page' => $currentData->lastPage(),
            'per_page' => $currentData->perPage(),
            'total' => $total,
            'totalFiltered' => $currentData->total(),
        ];
    }
    
    public function listDropFund($search, $limit, $page, $colName, $colSort)
    {
        $user = Auth::guard('admin')->user();
        $salesId = $user->id ?? null;
        $aum = $this->aumRepo->getActiveAumTarget();
        if (!$aum && !empty($aum->asset_category)) {
            return ['item' => [], 'total' => 0, 'totalFiltered' => 0];
        }

        $categoryIds = (array) $aum->asset_category ?? [];
        $targetAum = $aum->target_aum ?? 0;
        $aumDate = date('Y-m-t', strtotime('last month'));

        // Get investors
        $latestData = $this->assetRepo->latestDataDate($aumDate);
        $baseQueryCurrent = $this->assetRepo->baseQueryInvestorsByCategoryWithCurrentBalance($latestData, $categoryIds);
        $currentData = $this->aumRepo->listDropFund($baseQueryCurrent, $salesId, $aumDate, $targetAum, $search, $limit, $page, $colName, $colSort);
        $total = !empty($search) ? $this->assetRepo->countInvestorsByCategoryWithCurrentBalance($latestData, $aumDate, $categoryIds, $targetAum, $salesId) : $currentData->total();
        
        return [
            'item' => $currentData->items(),
            'current_page' => $currentData->currentPage(),
            'last_page' => $currentData->lastPage(),
            'per_page' => $currentData->perPage(),
            'total' => $total,
            'totalFiltered' => $currentData->total(),
        ];
    }
}
