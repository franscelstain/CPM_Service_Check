<?php

namespace App\Http\Controllers\Sales\Balance;

use App\Http\Controllers\AppController;
use App\Services\Handlers\Finance\AssetLiabilityService;
use Illuminate\Http\Request;

class AssetsLiabilitiesController extends AppController
{
    protected $assetLiabService;

    public function __construct(AssetLiabilityService $assetLiabService)
    {
        $this->assetLiabService = $assetLiabService;
    }    

    public function totalAssetsLiabilities(Request $request, $id)
    {
        try {
            $totalAssetLiab = $this->assetLiabService->totalAssetLiability($request, $id);
            return $this->app_response('Total Asset Liabilities', $totalAssetLiab);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }
}