<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\AppController;
use App\Interfaces\Users\InvestorRepositoryInterface;
use Illuminate\Http\Request;

class InvestorsController extends AppController
{
    private $invRepo;
    
    public function __construct(InvestorRepositoryInterface $invRepo)
    {
        $this->invRepo = $invRepo;
    }

    public function detailInvestor($id)
    {
        return $this->responseJson('Investor - Detail', $this->invRepo->detailInvestor($id));
    }

    public function eStatement()
    {
        return $this->responseJson('Investor - eStatement', $this->invRepo->eStatement());
    }

    public function listInvestor(Request $request)
    {
        return $this->responseJson('Investor', $this->invRepo->listInvestor($request));
    }

    public function totalInvestor()
    {
        return $this->responseJson('Investor', $this->invRepo->totalInvestor());
    }
}