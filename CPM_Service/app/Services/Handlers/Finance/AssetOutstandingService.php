<?php

namespace App\Services\Handlers\Finance;

use App\Interfaces\Finance\AssetOutstandingRepositoryInterface;
use App\Interfaces\Products\CouponRepositoryInterface;
use App\Interfaces\Products\PriceRepositoryInterface;
use App\Services\Utils\DateHelper;

class AssetOutstandingService
{
    protected $assetRepo;
    protected $priceRepo;
    protected $couponRepo;

    public function __construct(
        AssetOutstandingRepositoryInterface $assetRepo,
        PriceRepositoryInterface $priceRepo,
        CouponRepositoryInterface $couponRepo
    ) {
        $this->assetRepo = $assetRepo;
        $this->priceRepo = $priceRepo;
        $this->couponRepo = $couponRepo;
    }

    public function assetLatestDate($request, $investorId)
    {
        $month = $request->month ?? date('m');
        $year = $request->year ?? date('Y');
        $endDate = DateHelper::getStartAndEndDateMonth($month, $year);

        return ['date' => $this->assetRepo->assetLatestDate($investorId, $endDate)];
    }

    public function getAssetCategory($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');
        $categories = [
            'mutual_fund' => 'Mutual Fund',
            'bonds' => 'Bonds',
            'saving' => 'Saving',
            'insurance' => 'Insurance',
            'deposit' => 'Deposit',
        ];

        $result = [];
        foreach ($categories as $key => $name) {
            $balance = $this->assetRepo->getCategoryBalance($investorId, $key, $latestDate);
            $result[] = [
                'asset_category_name' => $name,
                'balance' => (float) $balance,
            ];
        }

        return $result;
    }

    public function getAssetMutualClass($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');
        return $this->assetRepo->getAssetMutualClass($investorId, $latestDate);
    }

    public function getIntegration()
    {
        return $this->assetRepo->getIntegration();
    }

    public function listAssetBank($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');
        return $this->assetRepo->listAssetBank($investorId, $latestDate);
    }

    public function listBondsAsset($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');
        $bondsAssets = $this->assetRepo->listBondsAsset($investorId, $latestDate);
        if ($bondsAssets->isEmpty()) {
            return [];
        }

        $productIds = $bondsAssets->pluck('product_id')->toArray();
        $productPrices = $this->priceRepo->getLatestProductPrices($productIds);
        $coupons = $this->couponRepo->couponWithProduct($productIds);

        return $bondsAssets->map(function ($asset) use ($productPrices, $coupons) {
            $price = $productPrices->firstWhere('product_id', $asset->product_id);
            $coupon = $coupons->firstWhere('product_id', $asset->product_id);

            $asset->price_value = $price->price_value ?? null;
            $asset->price_date = $price->price_date ?? null;
            $asset->coupon_date = $coupon->coupon_date ?? null;

            return $asset;
        });
    }

    public function listInsuranceAsset($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');
        return $this->assetRepo->listInsuranceAsset($investorId, $latestDate);
    }
    
    public function listMutualFundAsset($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');

        $mutualFundAssets = $this->assetRepo->listMutualFundAsset($investorId, $latestDate);
        if ($mutualFundAssets->isEmpty()) {
            return [];
        }

        $productIds = $mutualFundAssets->pluck('product_id')->toArray();
        $productPrices = $this->priceRepo->getLatestProductPrices($productIds);

        $mappedAssets = $mutualFundAssets->map(function ($asset) use ($productPrices) {
            $price = $productPrices->firstWhere('product_id', $asset->product_id);
            $asset->price_value = $price->price_value ?? null;
            $asset->price_date = $price->price_date ?? null;
            $asset->latest_nav_date = !empty($price->latest_nav_date) || !empty($price->price_date) ? !empty($price->latest_nav_date) ? $price->latest_nav_date : $price->price_date : null;
            return $asset;
        });

        // Get global latest_nav_date (maximum value)
        $globalLatestNavDate = $mappedAssets->pluck('latest_nav_date')->filter()->max();

        // Add global latest_nav_date to each asset
        $updatedAssets = $mappedAssets->each(function ($asset) use ($globalLatestNavDate) {
            $asset->global_latest_nav_date = $globalLatestNavDate;
        });

        return $updatedAssets;
    }

    public function totalReturnAsset($request, $investorId)
    {
        $endDate = $request->out_date ?? date('Y-m-d');

        // Realized gain/loss
        $saving = (float) $this->assetRepo->totalRealizedGL($investorId, $endDate, 'saving', 'dpk');
        $deposit = (float) $this->assetRepo->totalRealizedGL($investorId, $endDate, 'deposit', 'dpk');

        // Unrealized return
        $bonds = (float) $this->assetRepo->totalUnrealizedReturn($investorId, $endDate, 'bonds');
        $mutualFund = (float) $this->assetRepo->totalUnrealizedReturn($investorId, $endDate, 'mutual fund');

        // Hitung total
        $realized = $saving + $deposit;
        $unrealized = $bonds + $mutualFund;
        $total = $realized + $unrealized;

        return [
            'saving' => $saving,
            'deposit' => $deposit,
            'bonds' => $bonds,
            'mutual_fund' => $mutualFund,
            'realized' => $realized,
            'unrealized' => $unrealized,
            'total' => $total,
            'realized_percent' => $total > 0 ? ($realized / $total * 100) : 0,
            'unrealized_percent' => $total > 0 ? ($unrealized / $total * 100) : 0,
        ];
    }
}
