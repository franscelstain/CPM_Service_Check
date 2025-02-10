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

        $pembiayaan = $this->liabilityRepo->liabilityPembiayaan($investorId, $latestDate);
        $hasanahCard = $this->liabilityRepo->liabilityHasanahCard($investorId, $latestDate);

        return ['pembiayaan' => $pembiayaan, 'hasanah_card' => $hasanahCard];
    }
}