<?php

namespace App\Services\Handlers\Finance;

use App\Http\Controllers\AppController;
use App\Models\Users\Investor\Investor;
use App\Models\Transaction\StagingAsset;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SukukMiddlewareService
{
    protected $foundData = [];

    public function processRange($offset = 0, $exhaust = false)
    {
        try {
            Log::info("[SukukMiddleware] Job started with offset={$offset}, exhaust={$exhaust}");

            $chunkSize = 200;
            $processed = 0;
            $limit = 10000;
            $page = 0;
            $this->foundData = [];

            do {
                $currentOffset = $offset + ($page * $chunkSize);

                $investors = Investor::where('is_active', 'Yes')
                    ->orderBy('investor_id', 'asc')
                    ->offset($currentOffset)
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
                    'concurrency' => 50,
                    'fulfilled' => function () {},
                    'rejected' => function ($reason) {
                        Log::error("[TransactionHistories] Request failed: {$reason}");
                    },
                ]);

                $pool->promise()->wait();

                $processed += $investors->count();
                $page++;

            } while ($exhaust || $processed < $limit);
            

            DB::statement('SELECT move_stg_asset_sukuk()');
            
            Log::info("[SukukMiddleware] Job completed. Processed: {$processed} records");

            return $this->foundData;
        } catch (\Exception $e) {
            Log::error("[SukukMiddleware] Error: {$e->getMessage()}");
            return [];
        }
    }    

    private function processInvestor($controller, $inv)
    {
        $data = $controller->api_ws([
            'sn' => 'InvestorAsset',
            'val' => [$inv->cif]
        ])->original['data'] ?? [];

        foreach ($data as $item) {
            $this->saveSukuk($inv, (object) $item);
        }

        if (!empty($data)) {
            $this->foundData[$inv->investor_id] = $data;
        }
    }

    private function saveSukuk($inv, $dt)
    {
        try {
            $data = [
                'cif'                   => $dt->cif ?? null,
                'fullname'              => $inv->fullname ?? null, 
                'product_code'          => $dt->productCode ?? null,
                'product_name'          => $dt->productName ?? null, 
                'product_type'          => 'SUKUK', 
                'account_no'            => $dt->accountNo ?? null, 
                'outstanding_date'      => !empty($dt->balanceDate) ? $dt->balanceDate : '1900-01-01', 
                'outstanding_unit'      => $dt->unitOutstanding ?? null,
                'currency'              => $dt->currencyCode ?? null,
                'placement_amt'         => $dt->balanceAmount ?? null, 
                'created_by'            => 'MDW', 
                'created_host'          => 'MDW'
            ];
                     
            StagingAsset::create($data);
        } catch (\Exception $e) {
            Log::error("[SukukMiddleware] Save error for investor {$inv->investor_id}: {$e->getMessage()}");
        }
    }
}