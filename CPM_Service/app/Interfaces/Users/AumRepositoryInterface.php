<?php

namespace App\Interfaces\Users;

interface AumRepositoryInterface
{
    public function getActiveAumTarget();
    public function getInvestorsByCategoryWithCurrentBalance(Builder $baseQuery, $startDate, $targetAum);
    public function getInvestorsByCategoryWithDowngradeBalance(Builder $baseQuery, $salesId, $startDate);
    public function listAumPriority(Builder $baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort);
    public function listDropFund(Builder $baseQuery, $salesId, $startDate, $targetAum, $search, $limit, $page, $colName, $colSort);
}
