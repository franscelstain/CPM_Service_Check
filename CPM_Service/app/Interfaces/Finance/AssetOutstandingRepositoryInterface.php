<?php

namespace App\Interfaces\Finance;

interface AssetOutstandingRepositoryInterface
{
    public function assetLatestDate($investorId, $outDate);
    public function baseQueryInvestorsByCategoryWithCurrentBalance(Builder $latestData, array $categoryIds);
    public function countInvestorsByCategoryWithCurrentBalance(Builder $latestData, $aumDate, array $categoryIds, $targetAum, $salesId = null);
    public function getAssetMutualClass($investorId, $outDate);
    public function getCategoryBalance($investorId, $category, $outDate);
    public function getIntegration();
    public function latestDataDate($outDate);
    public function latestDataDateDowngrade($outDate);
    public function listAssetBank($investorId, $outDate);
    public function listBondsAsset($investorId, $outDate);
    public function listInsuranceAsset($investorId, $outDate);
    public function listMutualFundAsset($investorId, $outDate);
    public function totalAsset($investorId, $outDate);
    public function totalRealizedGL($investorId, $outstandingDate, $assetClass, $assetCategory);
    public function totalUnrealizedReturn($investorId, $outstandingDate, $assetCategory);
}