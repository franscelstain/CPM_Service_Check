<?php

namespace App\Interfaces\Balance;
use Illuminate\Http\Request;

interface AssetOutstandingRepositoryInterface
{
    public function getTotalAssetsLiabilities(Request $request, $id);
    public function getTotalReturnAssets(Request $request, $id);
    public function getAssetBank(Request $request, $id);
    public function getAssetBonds(Request $request, $id);
    public function getAssetCategory(Request $request, $id);
    public function getAssetMutual(Request $request, $id);
    public function getAssetInsurance(Request $request, $id);
    public function getAssetMutualClass(Request $request, $id);
    public function getIntegration();
}