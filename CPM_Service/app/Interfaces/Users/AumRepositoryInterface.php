<?php

namespace App\Interfaces\Users;

interface AumRepositoryInterface
{
    public function getActiveAumTarget();
    public function getInvestorsByCategoryWithCurrentBalance(Builder $baseQuery, $startDate);
    public function getInvestorsByCategoryWithDowngradeBalance(Builder $baseQuery, array $investorIds, $startDate);
    public function listAumPriority(Builder $baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort);
    public function listDropFund(Builder $baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort);
}
