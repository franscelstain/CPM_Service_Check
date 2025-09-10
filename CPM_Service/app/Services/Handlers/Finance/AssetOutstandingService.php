<?php

namespace App\Services\Handlers\Finance;

use App\Http\Controllers\AppController;
use App\Interfaces\Finance\AssetOutstandingRepositoryInterface;
use App\Interfaces\Products\CouponRepositoryInterface;
use App\Interfaces\Products\PriceRepositoryInterface;
use App\Services\Utils\DateHelper;
use App\Models\Users\Investor\Investor;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Pool;

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

        $balances = collect($this->assetRepo->getCategoryBalance($investorId, $latestDate))
            ->keyBy('category_key');

        $result = [];
        foreach ($categories as $key => $label) {
            $balance = isset($balances[$key]) ? $balances[$key]->total_balance : 0;
            $result[] = [
                'asset_category_name' => $label,
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
        $bankAssets = collect($this->assetRepo->listAssetBank($investorId, $latestDate));

        if ($bankAssets->isEmpty()) {
            return [];
        }

        $globalLatestDate = $bankAssets->pluck('data_date')->filter()->max();
        return $bankAssets->map(function ($asset) use ($globalLatestDate) {
            $asset->latest_data_date = $globalLatestDate;
            return $asset;
        });
    }

    public function listBondsAsset($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');
        $bondsAssets = collect($this->assetRepo->listBondsAsset($investorId, $latestDate));

        if ($bondsAssets->isEmpty()) {
            return [];
        }

        $productIds = $bondsAssets->pluck('product_id')->unique()->toArray();      
        $productPrices = collect($this->priceRepo->getLatestProductPrices($productIds))->keyBy('product_id');
        $coupons = $this->couponRepo->couponWithProduct($productIds)->keyBy('product_id');
        $globalLatestDate = $bondsAssets->pluck('data_date')->filter()->max();

        return $bondsAssets->map(function ($asset) use ($productPrices, $coupons, $globalLatestDate) {
            $price = $productPrices->get($asset->product_id);
            $coupon = $coupons->get($asset->product_id);

            $asset->price_value = isset($price->price_value) && $price->price_value !== null ? (float) $price->price_value : null;
            $asset->price_date = $price->price_date ?? null;
            $asset->coupon_date = $coupon->coupon_date ?? null;
            $asset->latest_data_date = $globalLatestDate;

            return $asset;
        });
    }

    public function listInsuranceAsset($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');
        $insuranceAssets = collect($this->assetRepo->listInsuranceAsset($investorId, $latestDate));

        if ($insuranceAssets->isEmpty()) {
            return [];
        }

        $globalLatestDate = $insuranceAssets->pluck('data_date')->filter()->max();
        return $insuranceAssets->map(function ($asset) use ($globalLatestDate) {
            $asset->latest_data_date = $globalLatestDate;
            return $asset;
        });
    }
    
    public function listMutualFundAsset($request, $investorId)
    {
        $latestDate = $request->out_date ?? date('Y-m-d');

        $mutualFundAssets = collect($this->assetRepo->listMutualFundAsset($investorId, $latestDate));
        
        if ($mutualFundAssets->isEmpty()) {
            return [];
        }

        $productIds = $mutualFundAssets->pluck('product_id')->unique()->toArray();
        $productPrices = collect($this->priceRepo->getLatestProductPrices($productIds))->keyBy('product_id');
        $globalLatestDate = $mutualFundAssets->pluck('data_date')->filter()->max();

        $mappedAssets = $mutualFundAssets->map(function ($asset) use ($productPrices, $globalLatestDate) {
            $price = $productPrices->get($asset->product_id);

            $asset->latest_data_date = $globalLatestDate;
            $asset->price_value = isset($price->price_value) ? (float) $price->price_value : null;
            $asset->global_latest_nav_date = $price->latest_nav_date ?? null;

            if (
                !isset($asset->latest_nav_date) ||
                (
                    isset($price->price_date) &&
                    strtotime($asset->latest_nav_date) < strtotime($price->price_date)
                )
            ) {
                $asset->latest_nav_date = $price->price_date ?? null;
            }

            // Biarkan angka lain juga null jika belum ada
            $asset->balance_amount = $asset->balance_amount !== null ? (float) $asset->balance_amount : null;
            $asset->return_percentage = $asset->return_percentage !== null ? (float) $asset->return_percentage : null;
            $asset->unrealized_gl_original = $asset->unrealized_gl_original !== null ? (float) $asset->unrealized_gl_original : null;
            $asset->return_amount = $asset->return_amount !== null ? (float) $asset->return_amount : null;
            $asset->outstanding_unit = $asset->outstanding_unit !== null ? (float) $asset->outstanding_unit : null;
            $asset->rate = $asset->rate !== null ? (float) $asset->rate : null;
            return $asset;
        });

        return $mappedAssets;
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

    public function processInChunks()
    {
        $totalTime = 0;
        $totalCalls = 0;
        $chunkSize = 200;
        $page = 0;
        $start = microtime(true);

        do {
            $investors = Investor::where('is_active', 'Yes')
                ->offset($page * $chunkSize)
                ->limit($chunkSize)
                ->get();

            if ($investors->isEmpty()) {
                break;
            }

            $controller = new AppController();
            $promises = [];

            foreach ($investors as $inv) {
                $promises[] = function () use ($controller, $inv) {
                    return new FulfilledPromise($this->processInvestor($controller, $inv));
                };
            }

            $pool = new Pool(new \GuzzleHttp\Client(), $promises, [
                'concurrency' => 20,
                'fulfilled' => function () use (&$totalCalls) {
                    $totalCalls++;
                },
                'rejected' => function ($reason) {
                    // Optional: handle error
                },
            ]);

            $pool->promise()->wait();
            $page++;

        } while (true);

        $totalTime = microtime(true) - $start;

        return [
            'total_time' => round($totalTime, 4),
            'total_calls' => $totalCalls,
            'average_time' => $totalCalls > 0 ? round($totalTime / $totalCalls, 4) : 0,
            'last_page' => $page
        ];
    }

    private function processInvestor($controller, $inv)
    {
        try {
            $data = $controller->api_ws(['sn' => 'InvestorAsset', 'val' => [$inv->cif]])->original['data'] ?? [];

            foreach ($data as $a) {
                $record = [
                    'investor_id'       => $inv->investor_id,
                    'product_id'        => $a['product_id'],
                    'account_no'        => $a['account_no'],
                    'outstanding_date'  => $a['outstanding_date'],
                    'balance_amount'    => $a['balance_amount'],
                    'currency'          => $a['currency'],
                    'is_month_end'      => isset($a['is_month_end']) ? $a['is_month_end'] : false,
                    'is_active'         => 'Yes',
                    'created_by'        => 'system',
                    'created_host'      => '127.0.0.1',
                    'unrealized_gl_pct' => $a['unrealized_gl_pct'] ?? 0,
                    'outstanding_unit'  => $a['outstanding_unit'] ?? 0,
                    'placement_amount'  => $a['placement_amount'] ?? 0,
                    'return_percentage' => $a['return_percentage'] ?? 0,
                    'realized_gl_pct'   => $a['realized_gl_pct'] ?? 0,
                    'premium_amount'    => $a['premium_amount'] ?? 0,
                ];

                $existing = $this->getOutstandingID(
                    $record['investor_id'],
                    $record['product_id'],
                    $record['account_no'],
                    $record['outstanding_date']
                );

                if ($existing) {
                    DB::table('t_assets_outstanding')
                        ->where('id', $existing->outstanding_id)
                        ->update(array_diff_key($record, array_flip([
                            'investor_id', 'product_id', 'account_no', 'outstanding_date'
                        ])));
                } else {
                    DB::table('t_assets_outstanding')->insert($record);
                }
            }
        } catch (\Throwable $e) {
            // Optional: handle error
        }
    }

    private function getOutstandingID($investor_id, $product_id, $account_no, $date)
    {
        return DB::table('t_assets_outstanding')
            ->where('investor_id', $investor_id)
            ->where('product_id', $product_id)
            ->where('account_no', $account_no)
            ->where('outstanding_date', $date)
            ->where('is_active', 'Yes')
            ->orderByDesc('outstanding_id')
            ->first();
    }
}
