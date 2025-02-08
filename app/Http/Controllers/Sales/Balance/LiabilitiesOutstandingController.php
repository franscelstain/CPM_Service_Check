<?php

namespace App\Http\Controllers\Sales\Balance;

use App\Http\Controllers\AppController;
use App\Services\Handlers\Finance\LiabilityOutstandingService;
use Illuminate\Http\Request;

class LiabilitiesOutstandingController extends AppController
{
    protected $liabilityService;
    
    public function __construct(LiabilityOutstandingService $liabilityService)
    {
        $this->liabilityService = $liabilityService;
    }

    public function integration_data()
    {
        return $this->app_response('Labilities Outstanding - Integration', $this->liabilityService->getIntegration());
    }

    public function listLiability(Request $request, $id)
    {
        try {
            $liab = $this->liabilityService->listLiability($request, $id);
            return $this->app_response('Liabilities', $liab);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }
}