<?php

namespace App\Http\Controllers\Sales\Balance;

use App\Http\Controllers\AppController;
use App\Interfaces\Balance\AssetOutstandingRepositoryInterface;
use Illuminate\Http\Request;

class AssetOutstandingController extends AppController
{
    private $assetRepo;
    
    public function __construct(AssetOutstandingRepositoryInterface $assetRepo)
    {
        $this->assetRepo = $assetRepo;
    }

    public function totalReturnAssets(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Total Return Assets', $this->assetRepo->getTotalReturnAssets($request, $id));
    }

    public function totalAssetsLiabilities(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Total Asset Liabilities', $this->assetRepo->getTotalAssetsLiabilities($request, $id));
    }

    public function bank(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Bank', $this->assetRepo->getAssetBank($request, $id));
    }

    public function bonds(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Bonds', $this->assetRepo->getAssetBonds($request, $id));
    }

    public function category(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Bank', $this->assetRepo->getAssetCategory($request, $id));
    }

    public function insurance(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Insurance', $this->assetRepo->getAssetInsurance($request, $id));
    }

    public function integration_data()
    {
        return $this->app_response('Assets Outstanding - Integration', $this->assetRepo->getIntegration());
    }

    public function mutual_fund(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Mutual Fund', $this->assetRepo->getAssetMutual($request, $id));
    }

    public function mutual_fund_class(Request $request, $id)
    {
        return $this->app_response('Assets Outstanding - Mutual Fund', $this->assetRepo->getAssetMutualClass($request, $id));
    }
}