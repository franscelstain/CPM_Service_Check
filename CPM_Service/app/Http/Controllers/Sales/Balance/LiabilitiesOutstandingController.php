<?php

namespace App\Http\Controllers\Sales\Balance;

use App\Http\Controllers\AppController;
use App\Interfaces\Balance\LiabilitiesOutstandingRepositoryInterface;
use Illuminate\Http\Request;

class LiabilitiesOutstandingController extends AppController
{
    private $liabilityRepo;
    
    public function __construct(LiabilitiesOutstandingRepositoryInterface $liabilityRepo)
    {
        $this->liabilityRepo = $liabilityRepo;
    }

    public function integration_data()
    {
        return $this->app_response('Labilities Outstanding - Integration', $this->liabilityRepo->getIntegration());
    }

    public function liabilities(Request $request, $id)
    {        
        return $this->app_response('Liabilities', $this->liabilityRepo->getLiabilities($request, $id));
    }
}