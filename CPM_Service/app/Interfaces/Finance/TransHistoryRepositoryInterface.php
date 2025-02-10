<?php

namespace App\Interfaces\Finance;

interface TransHistoryRepositoryInterface
{
    public function getBalanceGoalByInvestor(array $investorIds);
}