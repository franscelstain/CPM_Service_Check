<?php

namespace App\Services\Handlers\Users\Investor;

use App\Interfaces\Users\InvestorRepositoryInterface;
use App\Interfaces\Finance\TransHistoryRepositoryInterface;
use Auth;

class InvestorService
{
    protected $invRepo;
    protected $histRepo;

    public function __construct(InvestorRepositoryInterface $invRepo, TransHistoryRepositoryInterface $histRepo)
    {
        $this->invRepo = $invRepo;
        $this->histRepo = $histRepo;
    }

    public function listWithGoalsWithSales($salesId, $search, $limit, $page, $colName, $colSort) {
        $baseQuery = $this->invRepo->baseInvestorSales($salesId);
        $inv = $this->invRepo->listWithGoalsForSales($baseQuery, $search, $limit, $page, $colName, $colSort);
        $total = !empty($search) ? $this->invRepo->countInvestorsBySales($salesId) : $inv->total();
        if ($inv->total() > 0) {
            // Ambil hanya investor_id yang ada di $inv
            $investorIds = $inv->pluck('investor_id')->toArray();
            $histGoals = $this->histRepo->getBalanceGoalByInvestor($investorIds)->keyBy('investor_id');
            $inv->map(function ($item) use ($histGoals) {
                $balanceGoals = $histGoals->get($item->investor_id)->balance ?? null;
                $item->balance_goals = $balanceGoals;
            });
        }
        
        return [
            'item' => $inv->items(),
            'current_page' => $inv->currentPage(),
            'last_page' => $inv->lastPage(),
            'per_page' => $inv->perPage(),
            'total' => $total,
            'totalFiltered' => $inv->total(),
        ];
    }
}