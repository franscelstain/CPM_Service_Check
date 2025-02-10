<?php

namespace App\Interfaces\Users;

interface InvestorRepositoryInterface
{
    public function detailInvestor($id);
    public function eStatement();
    public function listInvestor(array $request);
    public function totalInvestor();
}