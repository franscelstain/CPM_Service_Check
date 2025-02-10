<?php

namespace App\Http\Controllers\Users\Investor;

use App\Http\Controllers\AppController;
use App\Services\Handlers\Users\Investor\AumService;
use Illuminate\Http\Request;

class AumPriorityController extends AppController
{
    protected $aumService;

    public function __construct(AumService $aumService)
    {
        $this->aumService = $aumService;
    }

    public function listAumPriority(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $colName = $request->input('colName', 'cif');
        $colSort = $request->input('colSort', 'asc');

        $response = $this->aumService->getAumPriorityData($search, $limit, $page, $colName, $colSort);

        return $this->app_response('Investor - Aum', $response);
    }

    public function listDropFund(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $colName = $request->input('colName', 'cif');
        $colSort = $request->input('colSort', 'asc');

        $response = $this->aumService->listDropFund($search, $limit, $page, $colName, $colSort);

        return $this->app_response('Investor - Drop Fund', $response);
    }
}
