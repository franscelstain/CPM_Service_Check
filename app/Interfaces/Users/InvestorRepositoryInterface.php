<?php

namespace App\Interfaces\Users;

interface InvestorRepositoryInterface
{
    public function baseInvestorSales($sales_id);
    public function countInvestorsBySales($salesId);
    public function countInvestorPriority();
    public function deactivateInvestorByEmail(string $email);
    public function detailInvestor($id);
    public function detailInvestorBySales($inv_id, $sales_id);
    public function eStatement();
    public function findByEmail(string $email);
    public function getInvestorsBySalesWithPagination($salesId, $search, $start, $length, $colName, $colSort);
    public function listInvestor(array $request);
    public function listPriorityCard($search, $limit, $page, $colName, $colSort);
    public function listWithBalanceForSales($salesId, $search, $limit, $page, $colName, $colSort);
    public function listWithGoalsForSales(Builder $query, $search, $limit, $page, $colName, $colSort);
    public function totalInvestor();
    public function updateLastActivityByEmail(string $email, $token);
}