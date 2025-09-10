<?php

namespace App\Http\Controllers\Sales\Balance;

use App\Http\Controllers\AppController;
use App\Services\Handlers\Finance\AssetOutstandingService;
use Illuminate\Http\Request;

class AssetOutstandingController extends AppController
{
    protected $assetService;
    
    public function __construct(AssetOutstandingService $assetService)
    {
        $this->assetService = $assetService;
    }

    public function assetLatestDate(Request $request, $id)
    {
        try {
            $latestDate = $this->assetService->assetLatestDate($request, $id);
            return $this->app_response('Assets - Latest Date', $latestDate);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function getAssetCategory(Request $request, $id)
    {
        try {
            $category = $this->assetService->getAssetCategory($request, $id);
            return $this->app_response('Assets - Category', $category);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function getAssetMutualClass(Request $request, $id)
    {
        try {
            $category = $this->assetService->getAssetMutualClass($request, $id);
            return $this->app_response('Assets - Mutual Fund By Class', $category);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function integration_data()
    {
        return $this->app_response('Assets Outstanding - Integration', $this->assetService->getIntegration());
    }

    public function listAssetBank(Request $request, $id)
    {
        try {
            $bank = $this->assetService->listAssetBank($request, $id);
            return $this->app_response('Bank', $bank);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function listBondsAsset(Request $request, $id)
    {
        try {
            $bonds = $this->assetService->listBondsAsset($request, $id);
            return $this->app_response('Assets - Bonds', $bonds);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function listInsuranceAsset(Request $request, $id)
    {
        try {
            $insurance = $this->assetService->listInsuranceAsset($request, $id);
            return $this->app_response('Assets - Insurance', $insurance);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function listMutualFundAsset(Request $request, $id)
    {
        try {
            $mutual = $this->assetService->listMutualFundAsset($request, $id);
            return $this->app_response('Mutual Fund', $mutual);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function totalReturnAsset(Request $request, $id)
    {
        try {
            $returnAsset = $this->assetService->totalReturnAsset($request, $id);
            return $this->app_response('Total Return Assets', $returnAsset);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }
}