<?php

namespace App\Services\Handlers\Finance;

use App\Interfaces\Finance\LiabilitiesOutstandingRepositoryInterface;

class LiabilityOutstandingService
{
    protected $liabilityRepo;

    public function __construct(LiabilitiesOutstandingRepositoryInterface $liabilityRepo)
    {
        $this->liabilityRepo = $liabilityRepo;
    }

    public function getIntegration()
    {
        return $this->liabilityRepo->getIntegration();
    }

    public function listLiability($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');

        $pembiayaan = collect($this->liabilityRepo->liabilityPembiayaan($investorId, $latestDate));
        $hasanahCard = collect($this->liabilityRepo->liabilityHasanahCard($investorId, $latestDate));

        if (!$pembiayaan->isEmpty()) {
            $pembiayaanLatestDate = $pembiayaan->pluck('data_date')->filter()->max();
            $pembiayaan = $pembiayaan->map(function ($item) use ($pembiayaanLatestDate) {                
                $item->latest_data_date = $pembiayaanLatestDate;
                return $item;
            });
        }

        if (!$hasanahCard->isEmpty()) {
            $hasanahCardLatestDate = $hasanahCard->pluck('data_date')->filter()->max();
            $hasanahCard = $hasanahCard->map(function ($item) use ($hasanahCardLatestDate) {                
                $item->latest_data_date = $hasanahCardLatestDate;
                return $item;
            });
        }        

        return ['pembiayaan' => $pembiayaan, 'hasanah_card' => $hasanahCard];
    }
}