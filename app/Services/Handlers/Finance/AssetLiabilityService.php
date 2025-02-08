<?php

namespace App\Services\Handlers\Finance;

use App\Interfaces\Finance\AssetOutstandingRepositoryInterface;
use App\Interfaces\Finance\LiabilitiesOutstandingRepositoryInterface;
use App\Services\Utils\DateHelper;

class AssetLiabilityService
{
    protected $assetRepo;
    protected $liabilityRepo;

    public function __construct(AssetOutstandingRepositoryInterface $assetRepo, LiabilitiesOutstandingRepositoryInterface $liabilityRepo)
    {
        $this->assetRepo = $assetRepo;
        $this->liabilityRepo = $liabilityRepo;
    }

    public function totalAssetLiability($request, $investorId)
    {
        $endDate = $request->out_date ?? date('Y-m-d');
        $asset = $this->assetRepo->totalAsset($investorId, $endDate);
        $liability = $this->liabilityRepo->totalLiability($investorId, $endDate);

        $totalAsset = $asset ?? 0;
        $totalLiability = $liability ?? 0;

        return [
            'asset' => $totalAsset,
            'liability' => $totalLiability,
            'networth' => $totalAsset - $totalLiability
        ];
    }
}