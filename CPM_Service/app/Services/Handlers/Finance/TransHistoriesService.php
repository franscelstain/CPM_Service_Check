<?php

namespace App\Services\Handlers\Finance;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetFreeze;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Reference\KYC\Holiday;
use App\Models\SA\Transaction\Reference;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Transaction\TransactionInstallment;
use App\Models\Users\Investor\Investor;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TransHistoriesService
{
    protected $foundData = [];
    private static $productCache = [];
    private static $priceCache = [];
    private static $referenceCodeCache = [];
    private static $typeIdCache = [];
    private static $statusCache = [];
    private static $cutOffCache = [];
    private static $holidayCache = [];
    private static $statusODCache = null;

    public function processRange($offset = 0, $exhaust = false)
    {
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

        return $this->foundData;
    }

    private function processInvestor($controller, $inv)
    {
        $data = $controller->api_ws([
            'sn' => 'TransactionHistories',
            'val' => [$inv->cif, date('Y-m-d', strtotime('-7 days')), date('Y-m-d')]
        ])->original['data'] ?? [];

        foreach ($data as $item) {
            $this->saveTransaction($inv, (object) $item);
        }

        if (!empty($data)) {
            $this->foundData[$inv->investor_id] = $data;
        }

        $this->deactivateTodayTransactionHistory($inv);
        $this->saveHistoriesGoals($inv->investor_id);
        $this->saveHistoriesNonGoals($inv->investor_id);
    }

    private function saveTransaction($inv, $dt)
    {
        try {
            $product = $this->getProduct($dt);
            if (!$inv->investor_id || !$product) return;

            $code_ref = $this->getReferenceByCode($dt->transactionTypeCode);
            if (!$code_ref) return;

            $type_reference_id = $this->getTransactionTypeId($dt);
            $existing = $this->findExistingTransaction($dt, $type_reference_id);
            $ts_id = $this->getTransactionStatus($dt);

            $data = $this->buildTransactionData($inv, $dt, $product->product_id, $ts_id, $type_reference_id, $existing);
            $validator = Validator::make($data, TransactionHistory::rules());
            if ($validator->fails()) {
                Log::warning("[TransactionHistories] Validation failed for investor {$inv->investor_id}: " . json_encode($validator->errors()->all()));
                return;
            }
            $save = $this->saveOrUpdateTransaction($existing, $data);

            if (!empty($existing)) {
                if (in_array($ts_id->reference_code ?? '', ['Done', 'Paid', 'Canceled'])) {
                    $this->updateAssetFreeze($inv, $dt, $product, $save);
                }

                if (!empty($save->portfolio_id)) {
                    $this->updatePortfolioStatusIfNeeded($inv, $save);
                }
            }
        } catch (\Exception $e) {
            Log::error("[TransactionHistories] Save error for investor {$inv->investor_id}: {$e->getMessage()}");
        }
    }

    private function findExistingTransaction($dt, $type_reference_id)
    {
        if (!empty($dt->referenceNo)) {
            if (in_array($dt->transactionTypeCode, ['SWTOT', 'SWTIN'])) {
                return TransactionHistory::where('reference_no', $dt->referenceNo)
                        ->where('type_reference_id', $type_reference_id)
                        ->where('is_active', 'Yes')
                        ->first();
            } else {
                return TransactionHistory::where('reference_no', $dt->referenceNo)
                        ->where('is_active', 'Yes')
                        ->first();
            }
        }
        return null;
    }

    private function buildTransactionData($inv, $dt, $productId, $ts_id, $type_reference_id, $existing)
    {
        $action = empty($existing) ? 'cre' : 'upd';
        $data = [
            'investor_id' => $inv->investor_id,
            'product_id' => $productId,
            'trans_reference_id' => $ts_id->trans_reference_id ?? null,
            'type_reference_id' => $type_reference_id,
            'account_no' => $dt->accountNo ?? null,
            'transaction_date' => $dt->transactionDate ?? null,
            'price_date' => $dt->priceDate ?? null,
            'settle_date' => $dt->settleDate ?? null,
            'booking_date' => $dt->bookingDate ?? null,
            'maturity_date' => $dt->maturityDate ?? null,
            'amount' => $dt->amount ?? null,
            'price' => $dt->price ?? null,
            'net_amount' => $dt->netAmount ?? null,
            'unit' => $dt->units ?? null,
            'percentage' => $dt->percentage ?? null,
            'fee_amount' => $dt->feeAmount ?? null,
            'fee_unit' => $dt->feeUnit ?? null,
            'tax_amount' => $dt->feeTax ?? null,
            'charge' => $dt->charges ?? null,
            'approve_amount' => $dt->approvedAmount ?? null,
            'approve_unit' => $dt->approvedUnits ?? null,
            'payment_method' => $dt->paymentMethod ?? null,
            'reference_no' => $dt->referenceNo ?? null,
            'wms_remark' => $dt->remark ?? null,
            'wms_status' => $dt->status ?? null,
            'is_active' => 'Yes',
            'send_wms' => !empty($ts_id->trans_reference_id) ? 'true' : 'false',
            $action . 'ated_by' => 'System',
            $action . 'ated_host' => '::1'
        ];

        if ($dt->generatorId == 7 && $dt->entryUser === 'AUTOGENERATE') {
            $genDt = explode(',', $dt->generatorData1);
            $regId = $genDt[0] ?? null;
            $installment = TransactionInstallment::where('registered_id', $regId)
                ->where('is_active', 'Yes')
                ->select('portfolio_id')
                ->first();
            if (!empty($installment->portfolio_id)) {
                $data['portfolio_id'] = $installment->portfolio_id;
            }
        }

        return $data;
    }

    private function saveOrUpdateTransaction($existing, $data)
    {
        if (empty($existing) || empty($existing->trans_history_id)) {
            return TransactionHistory::create($data);
        }

        $existing->update($data);

        return $existing;
    }

    private function updateAssetFreeze($inv, $dt, $product, $save)
    {
        $freeze = AssetFreeze::where('investor_id', $inv->investor_id)
            ->where('product_id', $product->product_id)
            ->where('portfolio_id', $save->portfolio_id)
            ->where('account_no', $dt->accountNo)
            ->select('asset_freeze_id', 'freeze_unit')
            ->first();

        $act = empty($freeze) ? 'cre' : 'upd';
        $redeemFreezeUnit = max(0, ($freeze->freeze_unit ?? 0) - ($dt->units ?? 0));

        $freezeData = [
            'investor_id' => $inv->investor_id,
            'product_id' => $product->product_id,
            'portfolio_id' => $save->portfolio_id,
            'account_no' => $dt->accountNo ?? null,
            $act . 'ated_by' => 'System',
            $act . 'ated_host' => '::1'
        ];

        if (!empty($dt->units)) {
            $freezeData['freeze_unit'] = $redeemFreezeUnit;
        }

        if (empty($freeze)) {
            AssetFreeze::create($freezeData);
        } else {
            $freeze->update($freezeData);
        }
    }

    private function updatePortfolioStatusIfNeeded($inv, $save)
    {
        $hasActive = TransactionHistory::select()
            ->join('m_trans_reference as b', 't_trans_histories.status_reference_id', '=', 'b.trans_reference_id')
            ->where('t_trans_histories.investor_id', $inv->investor_id)
            ->where('t_trans_histories.is_active', 'Yes')
            ->where('t_trans_histories.portfolio_id', $save->portfolio_id)
            ->where('b.is_active', 'Yes')
            ->whereNotIn('b.reference_name', ['Canceled'])
            ->exists();

        if (!$hasActive) {
            $statusId = optional($this->getStatusOD())->trans_reference_id;

            Investment::where('portfolio_id', $save->portfolio_id)
                ->where('investor_id', $inv->investor_id)
                ->where('is_active', 'Yes')
                ->update(['status_id' => $statusId]);
        }
    }

    private function deactivateTodayTransactionHistory($inv)
    {
        $today = date('Y-m-d');

        TransactionHistoryDay::where([
            ['investor_id', $inv->investor_id],
            ['history_date', $today]
        ])->update(['is_active' => 'No']);
    }

    private function saveHistoriesGoals($inv_id)
    {
        $today = date('Y-m-d');
        $histories = $this->getHistoriesGoalsForSummary($inv_id);
        [$trans, $sub_amt, $sub_unit] = $this->calculateTransactionSummaries($histories, $inv_id, $today);
        
        foreach ($trans as $key => $val) {
            $avg_nav = ($sub_unit[$key] ?? 0) != 0 ? $sub_amt[$key] / $sub_unit[$key] : 0;
            $investment_amount = $avg_nav * $val['unit'];
            $earnings = $val['current_balance'] - $investment_amount;

            $data = [
                'investor_id' => $inv_id,
                'product_id' => $val['product_id'],
                'portfolio_id' => $val['portfolio_id'],
                'account_no' => $val['account_no'],
                'history_date' => $today,
                'unit' => $val['unit'],
                'avg_nav' => $avg_nav,
                'current_balance' => $val['current_balance'],
                'investment_amount' => $investment_amount,
                'earnings' => $earnings,
                'returns' => $investment_amount != 0 ? $earnings / $investment_amount * 100 : 0,
                'total_sub_amount' => $sub_amt[$key] ?? null,
                'total_sub_unit' => $sub_unit[$key] ?? null,
                'diversification_account' => $val['diversification_account'],
                'is_active' => 'Yes'
            ];

            TransactionHistoryDay::updateOrCreate(
                [
                    'investor_id' => $inv_id,
                    'product_id' => $val['product_id'],
                    'portfolio_id' => $val['portfolio_id'],
                    'account_no' => $val['account_no'],
                    'history_date' => $today
                ],
                $data
            );
        }
    }

    private function getHistoriesGoalsForSummary($inv_id)
    {
        return TransactionHistory::select('t_trans_histories.*', 'mtr2.reference_code', 'mact.diversification_account')
            ->join('m_trans_reference as mtr1', 't_trans_histories.trans_reference_id', '=', 'mtr1.trans_reference_id')
            ->leftJoin('m_trans_reference as mtr2', function ($qry) {
                return $qry->on('t_trans_histories.type_reference_id', '=', 'mtr2.trans_reference_id')
                    ->where([['mtr2.reference_type', 'Transaction Type'], ['mtr2.is_active', 'Yes']]);
            })
            ->join('m_products as mp', 't_trans_histories.product_id', '=', 'mp.product_id')
            ->leftJoin('m_asset_class as mac', function ($qry) {
                $qry->on('mp.asset_class_id', '=', 'mac.asset_class_id')->where('mac.is_active', 'Yes');
            })
            ->leftJoin('m_asset_categories as mact', function ($qry) {
                $qry->on('mac.asset_category_id', '=', 'mact.asset_category_id')->where('mact.is_active', 'Yes');
            })
            ->where('t_trans_histories.investor_id', $inv_id)
            ->where('t_trans_histories.is_active', 'Yes')
            ->where('mtr1.reference_type', 'Transaction Status')
            ->where('mtr1.reference_code', 'Done')
            ->where('mtr1.is_active', 'Yes')
            ->where('mp.is_active', 'Yes')
            ->whereRaw("t_trans_histories.portfolio_id LIKE '2%'")
            ->get();
    }

    private function calculateTransactionSummaries($hist, $inv_id, $today)
    {
        $trans = $sub_amt = $sub_unit = [];

        if (empty($hist) || !is_iterable($hist)) {
            Log::warning('[TransactionHistories] No histories to process in calculateTransactionSummaries');
            return [$trans, $sub_amt, $sub_unit];
        }

        foreach ($hist as $h) {
            $id = md5($inv_id . $h->product_id . $h->portfolio_id . $h->account_no);
            $unit = !empty($h->approve_unit)
                ? (in_array($h->reference_code, ['SUB', 'TOPUP', 'SWTIN', 'ADJUP', 'CDIV']) ? $h->approve_unit : $h->approve_unit * -1)
                : 0;

            if (in_array($h->reference_code, ['SUB', 'TOPUP', 'SWTIN', 'ADJUP', 'CDIV'])) {
                $sub_amt[$id] = ($sub_amt[$id] ?? 0) + floatval($h->net_amount);
                $sub_unit[$id] = ($sub_unit[$id] ?? 0) + floatval($h->approve_unit);
            }

            if (isset($trans[$id])) {
                $trans[$id]['unit'] += $unit;
                $trans[$id]['current_balance'] += $unit * $trans[$id]['price'];
            } else {
                $price = $this->getPrice($h->product_id, $today);

                $trans[$id] = [
                    'product_id' => $h->product_id,
                    'portfolio_id' => $h->portfolio_id,
                    'account_no' => $h->account_no,
                    'unit' => $unit,
                    'price' => $price->price_value ?? 0,
                    'current_balance' => ($price->price_value ?? 0) * $unit,
                    'diversification_account' => $h->diversification_account,
                    'is_active' => $h->is_active
                ];
            }
        }

        return [$trans, $sub_amt, $sub_unit];
    }

    private function saveHistoriesNonGoals($inv_id)
    {
        $products = [];
        $today = $this->getToday();
        $assets = DB::select("
            SELECT account_no, product_id, diversification_account, regular_payment,
                SUM(outstanding_unit) AS unit, SUM(total_subscription) AS total_sub,
                SUM(total_unit) AS total_unit, SUM(balance_amount) AS balance
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                        tao.account_no, mp.product_id, mact.diversification_account, tao.regular_payment, 
                        tao.outstanding_unit, tao.total_subscription, tao.total_unit, tao.balance_amount
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON tao.product_id = mp.product_id
                JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id
                JOIN m_asset_categories mact ON mac.asset_category_id = mact.asset_category_id
                WHERE tao.investor_id = ?
                    AND tao.outstanding_date = CURRENT_DATE
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, tao.data_date DESC, tao.outstanding_id DESC
            ) AS assets
            GROUP BY account_no, product_id, diversification_account, regular_payment
        ", [$inv_id]);
        
        if (!empty($assets)) {
            foreach ($assets as $a) {
                $save = true;
                $unit = $avg = $amt = $current = $earnings = $returns = null;

                if ($a->diversification_account) {
                    if (!empty($a->unit)) {
                        if (!isset($products[$a->product_id])) {
                            $price = $this->getPrice($a->product_id, $today);
                            $products[$a->product_id] = $price->price_value ?? 0;
                        }

                        $cut = $this->getCutOffData($a->product_id);
                        $txDate = $cut['transaction_date_allocation'] ?? $today;

                        $trans = TransactionHistoryDay::selectRaw('SUM(unit) as unit, SUM(total_sub_amount) as total_amount, SUM(total_sub_unit) as total_unit')
                            ->where([
                                ['investor_id', $inv_id],
                                ['product_id', $a->product_id],
                                ['account_no', $a->account_no],
                                ['history_date', $txDate],
                                ['is_active', 'Yes']
                            ])
                            ->whereRaw("portfolio_id LIKE '2%'")
                            ->first();

                        $unit = !empty($trans->unit) ? $a->unit - $trans->unit : $a->unit;

                        if ($unit > 0) {
                            $current = $unit * $products[$a->product_id];
                            $total_sub = !empty($trans->total_amount) ? $a->total_sub - $trans->total_amount : $a->total_sub;
                            $total_unit = !empty($trans->unit) ? $a->total_unit - $trans->total_unit : $a->total_unit;
                            $avg = $total_unit != 0 ? $total_sub / $total_unit : 0;
                            $amt = $avg * $unit;
                            $earnings = $current - $amt;
                            $returns = $amt != 0 ? $earnings / $amt * 100 : 0;
                        } else {
                            $save = false;
                        }
                    }
                } else {
                    $current = $a->balance;
                }

                if ($save) {
                    $data = [
                        'investor_id' => $inv_id,
                        'product_id' => $a->product_id,
                        'account_no' => $a->account_no,
                        'history_date' => $today,
                        'unit' => $unit,
                        'avg_nav' => $avg,
                        'current_balance' => $current,
                        'investment_amount' => $amt,
                        'earnings' => $earnings,
                        'returns' => $returns,
                        'total_sub_amount' => $a->total_sub,
                        'total_sub_unit' => $a->total_unit,
                        'diversification_account' => $a->diversification_account,
                        'regular_payment' => $a->regular_payment ?? null,
                        'is_active' => 'Yes'
                    ];

                    $existing = TransactionHistoryDay::where([
                        ['investor_id', $inv_id],
                        ['product_id', $a->product_id],
                        ['account_no', $a->account_no],
                        ['history_date', $today]
                    ])->whereNull('portfolio_id')->first();

                    if (empty($existing->trans_history_day_id)) {
                        TransactionHistoryDay::create($data);
                    } else {
                        TransactionHistoryDay::where('trans_history_day_id', $existing->trans_history_day_id)->update($data);
                    }
                }
            }
        }
    }

    private function getProduct($dt)
    {
        $key = $dt->productCode;
        if (!isset(self::$productCache[$key])) {
            self::$productCache[$key] = Product::where('ext_code', $key)
                ->where('is_active', 'Yes')
                ->select('product_id')
                ->first();
        }
        return self::$productCache[$key];
    }

    private function getPrice($productId, $today)
    {
        $key = $productId . '_' . $today;

        if (!isset(self::$priceCache[$key])) {
            self::$priceCache[$key] = Price::where('product_id', $productId)
                ->where('price_date', '<=', $today)
                ->where('is_active', 'Yes')
                ->orderByDesc('price_date')
                ->select('price_value')
                ->first();
        }

        return self::$priceCache[$key];
    }

    private function getTransactionStatus($dt)
    {
        $key = ucwords(strtolower($dt->status ?? ''));
        if (!isset(self::$statusCache[$key])) {
            self::$statusCache[$key] = Reference::whereJsonContains('reference_ext', $key)
                ->where('reference_type', 'Transaction Status')
                ->where('is_active', 'Yes')
                ->select('trans_reference_id', 'reference_code')
                ->first();
        }
        return self::$statusCache[$key];
    }

    private function getReferenceByCode($code)
    {
        if (!isset(self::$referenceCodeCache[$code])) {
            self::$referenceCodeCache[$code] = Reference::where('reference_code', $code)
                ->where('is_active', 'Yes')
                ->first();
        }
        return self::$referenceCodeCache[$code];
    }

    private function getTransactionTypeId($dt)
    {
        $key = $dt->transactionTypeCode;
        if (!isset(self::$typeIdCache[$key])) {
            $result = Reference::where('reference_code', $key)
                ->where('reference_type', 'Transaction Type')
                ->where('is_active', 'Yes')
                ->select('trans_reference_id')
                ->first();
            self::$typeIdCache[$key] = $result ? $result->trans_reference_id : null;
        }
        return self::$typeIdCache[$key];
    }

    private function getStatusOD()
    {
        if (!self::$statusODCache) {
            self::$statusODCache = Reference::where('is_active', 'Yes')
                ->where('reference_code', 'OD')
                ->select('trans_reference_id')
                ->first();
        }
        return self::$statusODCache;
    }

    private function getCutOffData($product_id)
    {
        if (!isset(self::$cutOffCache[$product_id])) {
            $rst = Product::where('m_products.is_active', 'Yes')
                ->where('product_id', $product_id)
                ->leftJoin('m_cut_off_time as b', function ($qry) {
                    return $qry->on('m_products.currency_id', '=', 'b.currency_id')->where('b.is_active', 'Yes');
                })
                ->leftJoin('m_currency as c', function ($qry) {
                    return $qry->on('c.currency_id', '=', 'b.currency_id')->where('c.is_active', 'Yes');
                })
                ->select(
                    'm_products.product_id',
                    'm_products.product_name',
                    'b.cut_off_time_value',
                    'b.currency_id',
                    'c.currency_name'
                )
                ->first();

            $holiday = $this->getHoliday($rst->currency_id, $this->getToday());
            $is_holiday = !empty($holiday) ? 'Yes' : 'No';

            $day = strtoupper(date('N'));
            $is_working_day = in_array($day, ['6', '7']) ? 'No' : 'Yes';

            $is_working_time = date("Hi") <= str_replace(':', '', $rst->cut_off_time_value) ? 'Yes' : 'No';
            $date_check = $is_working_time === 'Yes' ? $this->getToday() : date("Y-m-d", strtotime("+1 day"));

            $transaction_date = $this->cut_of_time_get_available_date($date_check, $rst->currency_id);

            self::$cutOffCache[$product_id] = [
                'product_id' => $rst->product_id,
                'product_name' => $rst->product_name,
                'currency' => $rst->currency_name,
                'cut_of_time' => $rst->cut_off_time_value,
                'current_date' => $this->getToday(),
                'current_time' => date('H:i'),
                'current_day' => strtoupper(date('l')),
                'is_holiday' => $is_holiday,
                'is_working_day' => $is_working_day,
                'is_working_time' => $is_working_time,
                'transaction_date_allocation' => $transaction_date
            ];
        }

        return self::$cutOffCache[$product_id];
    }

    private function cut_of_time_get_available_date($dateCheck, $currencyId)
    {
        $holiday = $this->getHoliday($currencyId, $dateCheck);

        $available = true;
        if (!empty($holiday)) {
            $available = false;
        }

        $day = strtoupper(date('N', strtotime($dateCheck)));
        if (in_array($day, ['6', '7'])) {
            $available = false;
        }

        if ($available) {
            return $dateCheck;
        } else {
            $nextDate = date('Y-m-d', strtotime('+1 day', strtotime($dateCheck)));
            return $this->cut_of_time_get_available_date($nextDate, $currencyId);
        }
    }

    private function getHoliday($currencyId, $date)
    {
        $key = $currencyId . '|' . $date;
        if (!isset(self::$holidayCache[$key])) {
            self::$holidayCache[$key] = Holiday::where('is_active', 'Yes')
                ->where('currency_id', $currencyId)
                ->where('effective_date', $date)
                ->first();
        }
        return self::$holidayCache[$key];
    }

    private function getToday()
    {
        return date('Y-m-d');
    }
}
