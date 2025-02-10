<?php

namespace App\Interfaces\Finance;

interface LiabilitiesOutstandingRepositoryInterface
{
    public function getIntegration();
    public function liabilityHasanahCard($investorId, $latestDate);
    public function liabilityLatestDate($investorId, $outDate);
    public function liabilityPembiayaan($investorId, $latestDate);
    public function totalLiability($investorId, $outDate);
}