<?php

namespace App\Interfaces\Finance;

interface FinancialRepositoryInterface
{
    public function getIncomeByInvestorId(array $investorIds);
    public function getExpenseByInvestorId(array $investorIds);
    public function getAssetsByInvestorId(array $investorIds);
    public function getLiabilitiesByInvestorId(array $investorIds);
}