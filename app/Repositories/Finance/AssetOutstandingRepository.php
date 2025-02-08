<?php

namespace App\Repositories\Finance;

use App\Interfaces\Finance\AssetOutstandingRepositoryInterface;
use App\Models\Crm\ImportedAssetDpk;
use App\Models\Crm\ImportedAssetInvest;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\SA\Assets\Products\Price;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AssetOutstandingRepository implements AssetOutstandingRepositoryInterface
{
    public function assetLatestDate($investorId, $outDate)
    {
        return DB::table('t_assets_outstanding')
            ->where('investor_id', $investorId)
            ->whereBetween('outstanding_date', $outDate)
            ->where('is_active', 'Yes')
            ->max('outstanding_date');
    }

    public function baseQueryInvestorsByCategoryWithCurrentBalance($latestData, $categoryIds)
    {
        return DB::table('t_assets_outstanding AS tao')                
                ->joinSub($latestData, 'latest_data', function ($join) {
                    $join->on('tao.investor_id', '=', 'latest_data.investor_id')
                        ->on('tao.product_id', '=', 'latest_data.product_id')
                        ->on('tao.account_no', '=', 'latest_data.account_no')
                        ->where(function ($query) {
                            $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                                  ->orWhereNull('latest_data.latest_data_date');
                        });
                })
                ->join('u_investors AS ui', 'tao.investor_id', '=', 'ui.investor_id')
                ->join('m_products AS mp', 'tao.product_id', '=', 'mp.product_id')
                ->join('m_asset_class AS mac', 'mp.asset_class_id', '=', 'mac.asset_class_id')
                ->join('m_asset_categories AS mact', 'mac.asset_category_id', '=', 'mact.asset_category_id')
                ->whereIn('mact.asset_category_id', $categoryIds)
                ->where('tao.is_active', 'Yes')
                ->where('ui.is_active', 'Yes')
                ->where('mp.is_active', 'Yes')
                ->where('mac.is_active', 'Yes')
                ->where('mact.is_active', 'Yes');
    }

    public function countInvestorsByCategoryWithCurrentBalance($latestData, $outDate, $categoryIds, $targetAum, $salesId = null)
    {
        $query = $this->baseQueryInvestorsByCategoryWithCurrentBalance($latestData, $categoryIds);
        if ($salesId) {
            $query->where('ui.sales_id', $salesId);
        }
        return $query->whereDate('tao.outstanding_date', $outDate)
                ->selectRaw("
                    tao.investor_id,
                    SUM(tao.balance_amount) AS downgrade_aum
                ")
                ->groupBy('tao.investor_id')
                ->havingRaw('SUM(tao.balance_amount) <= ' . $targetAum)
                ->count();
    }

    public function getAssetMutualClass($investorId, $latestDate)
    {    
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = DB::table('t_assets_outstanding as tao')
            ->select(
                'tao.product_id', 
                'tao.account_no', 
                DB::raw('MAX(tao.data_date) as max_data_date'),
                DB::raw('MAX(tao.created_at) as max_created_at')
            )
            ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
            ->where([
                ['tao.investor_id', $investorId],
                ['tao.is_active', 'Yes'],
                ['mp.is_active', 'Yes'],
                ['tao.outstanding_date', $latestDate],
            ])
            ->groupBy('tao.product_id', 'tao.account_no');

        return  DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.data_date', '=', 'latest_data.max_data_date')
                    ->on('tao.created_at', '=', 'latest_data.max_created_at');
            })
            ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
            ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
            ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['mutual fund'])
            ->where([
                ['tao.investor_id', $investorId], 
                ['tao.is_active', 'Yes'], 
                ['mp.is_active', 'Yes'], 
                ['mac.is_active', 'Yes'], 
                ['mact.is_active', 'Yes'], 
                ['tao.outstanding_date', $latestDate],
                ['tao.outstanding_unit', '>=', 1]
            ])
            ->whereExists(function ($qry) { 
                $qry->select(DB::raw(1))
                    ->from('u_investors as ui')
                    ->whereColumn('ui.investor_id', 'tao.investor_id')
                    ->where('ui.is_active', 'Yes');
            })
            ->select(
                'mac.asset_class_name', 
                'mac.asset_class_color', 
                DB::raw('SUM(tao.balance_amount) as balance')
            )
            ->groupBy('mac.asset_class_name', 'mac.asset_class_color')
            ->get();
    }

    public function getCategoryBalance($investorId, $category, $latestDate)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = DB::table('t_assets_outstanding as tao')
            ->select(
                'tao.product_id', 
                'tao.account_no', 
                DB::raw('MAX(tao.data_date) as max_data_date')
            )
            ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
            ->where([
                ['tao.investor_id', $investorId],
                ['tao.is_active', 'Yes'],
                ['mp.is_active', 'Yes'],
                ['tao.outstanding_date', $latestDate],
            ])
            ->groupBy('tao.product_id', 'tao.account_no');

        $query = DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->where(function($qry) {
                        $qry->whereColumn('tao.data_date', '=', 'latest_data.max_data_date')
                            ->orWhereNull('latest_data.max_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
            ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
            ->where([
                ['tao.investor_id', $investorId],
                ['tao.is_active', 'Yes'],
                ['mp.is_active', 'Yes'],
                ['mac.is_active', 'Yes'],
                ['mact.is_active', 'Yes'],
                ['tao.outstanding_date', $latestDate],
            ])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('u_investors as ui')
                    ->whereColumn('ui.investor_id', 'tao.investor_id')
                    ->where('ui.is_active', 'Yes');
            });

        switch ($category) {
            case 'mutual_fund':
                return $query->where(DB::raw('LOWER(mact.asset_category_name)'), 'mutual fund')->sum('balance_amount');

            case 'bonds':
                return $query->where(DB::raw('LOWER(mact.asset_category_name)'), 'bonds')->sum('balance_amount');

            case 'saving':
                return $query->where(DB::raw('LOWER(mact.asset_category_name)'), 'dpk')
                    ->whereIn(DB::raw('LOWER(mac.asset_class_name)'), ['saving', 'tabungan'])
                    ->sum('balance_amount');

            case 'insurance':
                return $query->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['insurance', 'bancassurance'])->sum('balance_amount');

            case 'deposit':
                return $query->where(DB::raw('LOWER(mact.asset_category_name)'), 'dpk')
                    ->whereIn(DB::raw('LOWER(mac.asset_class_name)'), ['deposit', 'deposito'])
                    ->sum('balance_amount');

            default:
                return 0;
        }
    }

    public function getIntegration()
    {
        try {
            // $db_cpm     = env('DB_DATABASE');
            // $db_crm     = env('DB2_DATABASE');
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $update = AssetOutstanding::where([
                ['outstanding_date', $today],
                ['created_by', 'system.dpk'],
                ['is_active', 'Yes']
            ])->delete();

            $balance = ImportedAssetDpk::join('u_investors as b', function ($query) {
                $query->on('b.cif', 'imported_asset_dpk.cif')->where('b.is_active', 'Yes');
            })
                ->join('m_products as c', function ($que) {
                    $que->on('c.product_name', 'imported_asset_dpk.product_name')->select('c.product_id')
                        ->where('c.is_active', 'Yes');
                })
                ->where([['imported_asset_dpk.deleted', false], ['imported_asset_dpk.priority', 'Y']])
                ->select(
                    'b.investor_id as investor_id',
                    'c.product_id as product_id',
                    'imported_asset_dpk.account_number as account_no',
                    'imported_asset_dpk.original_currency_amt as balance_amount',
                    'imported_asset_dpk.idr_equiv_amt as convert_balance',
                    'imported_asset_dpk.interest_rate as return_percentage',
                    'imported_asset_dpk.principal as return_amount',
                    'imported_asset_dpk.currency as currency',
                    'imported_asset_dpk.kurs as kurs',
                    DB::raw("CASE WHEN imported_asset_dpk.balance_date IS NOT NULL 
                                              THEN CONCAT(RIGHT(imported_asset_dpk.balance_date, 4), '-', 
                                                          SUBSTR(imported_asset_dpk.balance_date, 4, 2), '-', 
                                                          LEFT(imported_asset_dpk.balance_date, 2)) 
                                          ELSE NULL END AS data_date"),
                    DB::raw("CASE WHEN imported_asset_dpk.maturity_date !='00/00/0000' 
                                              THEN CONCAT(RIGHT(imported_asset_dpk.maturity_date, 4), '-', 
                                                          SUBSTR(imported_asset_dpk.maturity_date, 4, 2), '-',
                                                          LEFT(imported_asset_dpk.maturity_date, 2)) 
                                              ELSE NULL END AS due_date"),
                    DB::raw("'$today' as outstanding_date"),
                    DB::raw("'Yes' as is_active"),
                    DB::raw("'system.dpk' as created_by"),
                    DB::raw("'::1' as created_host"),
                    DB::raw("'$now' as created_at"),
                )->distinct();
            $insert = AssetOutstanding::insertUsing([
                'investor_id',
                'product_id',
                'account_no',
                'balance_amount',
                'convert_balance',
                'return_percentage',
                'return_amount',
                'currency',
                'kurs',
                'data_date',
                'due_date',
                'outstanding_date',
                'is_active',
                'created_by',
                'created_host',
                'created_at'
            ], $balance);

            $updateInvest = AssetOutstanding::where([
                ['outstanding_date', $today],
                ['created_by', 'system.invest'],
                ['is_active', 'Yes']
            ])->delete();

            $balanceInvest = ImportedAssetInvest::join('u_investors as b', function ($query) {
                $query->on('b.cif', 'imported_asset_investment.cif')->where('b.is_active', 'Yes');
            })
                ->join('m_products as c', function ($que) {
                    $que->on('c.product_code', 'imported_asset_investment.product_code')->select('c.product_id')
                        ->where('c.is_active', 'Yes');
                })
                ->whereNotExists(function ($qry) use ($today) {
                    $qry->select(DB::raw(1))
                        ->from('t_assets_outstanding')
                        ->whereColumn([
                            ['t_assets_outstanding.account_no', 'imported_asset_investment.account_number'],
                            ['t_assets_outstanding.investor_id', 'b.investor_id'],
                            ['t_assets_outstanding.product_id', 'c.product_id']
                        ])
                        ->where('outstanding_date', $today);
                })
                ->select(
                    DB::raw('DATE_FORMAT(imported_asset_investment.date, "%Y-%m-%d") as subscription_date'),
                    'b.investor_id as investor_id',
                    'imported_asset_investment.ifua as ifua',
                    'c.product_id as product_id',
                    'imported_asset_investment.currency as currency',
                    'imported_asset_investment.account_number as account_no',
                    'imported_asset_investment.unit as outstanding_unit',
                    'imported_asset_investment.balance as balance_amount_original',
                    'imported_asset_investment.balance_converted as balance_amount',
                    'imported_asset_investment.avg_cost  as avg_unit_cost',
                    'imported_asset_investment.investment_amount  as investment_amount_original',
                    'imported_asset_investment.investment_amount_converted  as investment_amount',
                    'imported_asset_investment.ugl_amount  as return_amount_original',
                    'imported_asset_investment.ugl_amount_converted  as return_amount',
                    'imported_asset_investment.ugl_percentage  as return_percentage',
                    DB::raw("'$today' as outstanding_date"),
                    DB::raw("'Yes' as is_active"),
                    DB::raw("'system.invest' as created_by"),
                    DB::raw("'::1' as created_host"),
                    DB::raw("'$now' as created_at")
                )->distinct();
            $insertInvest = AssetOutstanding::insertUsing([
                'subscription_date',
                'investor_id',
                'ifua',
                'product_id',
                'currency',
                'account_no',
                'outstanding_unit',
                'balance_amount_original',
                'balance_amount',
                'avg_unit_cost',
                'investment_amount_original',
                'investment_amount',
                'return_amount_original',
                'return_amount',
                'return_percentage',
                'outstanding_date',
                'is_active',
                'created_by',
                'created_host',
                'created_at'
            ], $balanceInvest);
            return [
                'insert' => $insert,
                'update' => $update,
                'insertInvest' => $insertInvest,
                'updateInvest' => $updateInvest
            ];
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function latestDataDate($outDate) {
        return DB::table('t_assets_outstanding AS tao')
            ->select(
                'tao.investor_id',
                'tao.product_id',
                'tao.account_no',
                DB::raw('MAX(tao.data_date) AS latest_data_date')
            )
            ->where('tao.is_active', 'Yes')
            ->whereDate('tao.outstanding_date', $outDate)
            ->groupBy('tao.investor_id', 'tao.product_id', 'tao.account_no');
    }

    public function latestDataDateDowngrade($outDate) {
        return DB::table('t_assets_outstanding AS tao')
            ->joinSub(function ($query) use ($outDate) {
                $query->select('investor_id', DB::raw('MAX(outstanding_date) as max_outstanding_date'))
                    ->from('t_assets_outstanding')
                    ->where('is_active', 'Yes')
                    ->whereDate('outstanding_date', '<', $outDate)
                    ->groupBy('investor_id');
            }, 'max_outstanding_dates', function ($join) {
                $join->on('tao.investor_id', '=', 'max_outstanding_dates.investor_id')
                    ->on('tao.outstanding_date', '=', 'max_outstanding_dates.max_outstanding_date');
            })
            ->select(
                'tao.investor_id',
                'tao.product_id',
                'tao.account_no',
                DB::raw('MAX(tao.data_date) AS latest_data_date')
            )
            ->where('tao.is_active', 'Yes')
            ->groupBy('tao.investor_id', 'tao.product_id', 'tao.account_no');
    }

    public function listAssetBank($investorId, $latestDate)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = $this->latestDataDate($latestDate);

        return DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.investor_id', '=', 'latest_data.investor_id')
                    ->where(function ($query) {
                        $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                              ->orWhereNull('latest_data.latest_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
            ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
            ->leftJoin('t_stg_exchange_rates as tse', function($join) {
                $join->on('tao.currency', '=', 'tse.currency')
                     ->on('tao.data_date', '=', 'tse.date')
                     ->where('tse.currency', '!=', 'IDR');
            })
            ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['dpk'])
            ->where([
                ['tao.investor_id', $investorId],
                ['tao.is_active', 'Yes'],
                ['mp.is_active', 'Yes'],
                ['mac.is_active', 'Yes'],
                ['mact.is_active', 'Yes'],
                ['tao.balance_amount', '>=', 1],
                ['tao.outstanding_date', $latestDate],
            ])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('u_investors as ui')
                    ->whereColumn('ui.investor_id', 'tao.investor_id')
                    ->where('ui.is_active', 'Yes');
            })
            ->select(
                'mp.product_name',
                'tao.account_no',
                'tao.currency',
                'tao.balance_amount',
                'tao.kurs',
                'tao.data_date',
                'tao.balance_amount_original',
                'tao.realized_gl_original',
                'tao.realized_gl',
                'tse.rate',
                DB::raw('MAX(tao.data_date) OVER () as latest_data_date')
            )
            ->distinct()
            ->get();
    }

    public function listBondsAsset($investorId, $latestDate)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = $this->latestDataDate($latestDate);
            
        return DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.investor_id', '=', 'latest_data.investor_id')
                    ->where(function ($query) {
                        $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                              ->orWhereNull('latest_data.latest_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
            ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
            ->leftJoin('t_stg_exchange_rates as tse', function($join) {
                $join->on('tao.currency', '=', 'tse.currency')
                     ->on('tao.data_date', '=', 'tse.date')
                     ->where('tao.currency', '!=', 'IDR');
            })
            ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['bonds'])
            ->where([
                ['tao.investor_id', $investorId],
                ['tao.is_active', 'Yes'],
                ['mp.is_active', 'Yes'],
                ['mac.is_active', 'Yes'],
                ['mact.is_active', 'Yes'],
                ['tao.balance_amount', '>=', 1],
                ['tao.outstanding_date', $latestDate],
            ])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('u_investors as ui')
                    ->whereColumn('ui.investor_id', 'tao.investor_id')
                    ->where('ui.is_active', 'Yes');
            })
            ->select(
                'mp.product_id',
                'mp.product_name',
                'tao.account_no',
                'tao.currency',
                'tao.balance_amount',
                'tao.kurs',
                'tao.data_date',
                'tao.balance_amount_original',
                'tao.return_percentage',
                'tao.unrealized_gl_original',
                'tao.return_amount',
                'tao.placement_amount_original',
                'tse.rate',
                DB::raw('MAX(tao.data_date) OVER () as latest_data_date')
            )
            ->distinct()
            ->get();
    }

    public function listInsuranceAsset($investorId, $latestDate)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = $this->latestDataDate($latestDate);

        // Query utama untuk mengambil data berdasarkan subquery
        return DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.investor_id', '=', 'latest_data.investor_id')
                    ->where(function ($query) {
                        $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                              ->orWhereNull('latest_data.latest_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', '=', 'mp.asset_class_id')
            ->join('m_asset_categories as mact', 'mact.asset_category_id', '=', 'mac.asset_category_id')
            ->leftJoin('m_issuer as mi', function ($join) {
                $join->on('mi.issuer_id', '=', 'mp.issuer_id')
                    ->where('mi.is_active', 'Yes');
            })
            ->leftJoin('t_stg_exchange_rates as tse', function($join) {
                $join->on('tao.currency', '=', 'tse.currency')
                     ->on('tao.data_date', '=', 'tse.date')
                     ->where('tao.currency', '!=', 'IDR');
            })
            ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['insurance', 'bancassurance'])
            ->where([
                ['tao.investor_id', $investorId],
                ['tao.is_active', 'Yes'],
                ['mp.is_active', 'Yes'],
                ['mac.is_active', 'Yes'],
                ['mact.is_active', 'Yes'],
                ['tao.outstanding_date', $latestDate],
            ])
            ->select(
                'mp.product_name',
                'tao.account_no',
                'tao.currency',
                'tao.balance_amount',
                'tao.kurs',
                'tao.data_date',
                'tao.balance_amount_original',
                'tao.placement_amount',
                'tao.premium_amount',
                'mi.issuer_name',
                'tse.rate',
                DB::raw('MAX(tao.data_date) OVER () as latest_data_date')
            )
            ->distinct()
            ->get();
    }

    public function listMutualFundAsset($investorId, $latestDate)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = $this->latestDataDate($latestDate);

        return DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.investor_id', '=', 'latest_data.investor_id')
                    ->where(function ($query) {
                        $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                              ->orWhereNull('latest_data.latest_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
            ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
            ->leftJoin('t_stg_exchange_rates as tse', function($join) {
                $join->on('tao.currency', '=', 'tse.currency')
                     ->on('tao.data_date', '=', 'tse.date')
                     ->where('tao.currency', '!=', 'IDR');
            })
            ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['mutual fund'])
            ->where([
                ['tao.investor_id', $investorId],
                ['tao.is_active', 'Yes'],
                ['mp.is_active', 'Yes'],
                ['mac.is_active', 'Yes'],
                ['mact.is_active', 'Yes'],
                ['tao.outstanding_unit', '>=', 1],
                ['tao.outstanding_date', $latestDate],
            ])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('u_investors as ui')
                    ->whereColumn('ui.investor_id', 'tao.investor_id')
                    ->where('ui.is_active', 'Yes');
            })
            ->select(
                'mp.product_id',
                'mp.product_name',
                'tao.account_no',
                'tao.currency',
                'tao.balance_amount',
                'tao.kurs',
                'tao.data_date',
                'tao.balance_amount_original',
                'tao.return_percentage',
                'tao.unrealized_gl_original',
                'tao.return_amount',
                'tao.outstanding_unit',
                'mac.asset_class_name',
                'tse.rate',
                DB::raw('MAX(tao.data_date) OVER () as latest_data_date')
            )            
            ->distinct()
            ->get();
    }
    
    public function totalAsset($investorId, $outDate)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = $this->latestDataDate($outDate);

        return DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.investor_id', '=', 'latest_data.investor_id')
                    ->where(function ($query) {
                        $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                              ->orWhereNull('latest_data.latest_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
            ->where('tao.investor_id', $investorId)
            ->where('tao.outstanding_date', $outDate)
            ->where('tao.is_active', 'Yes')
            ->where('mp.is_active', 'Yes')
            ->where('tao.balance_amount', '>=', 1)
            ->sum('tao.balance_amount');
    }

    public function totalRealizedGL($investorId, $outstandingDate, $assetClass, $assetCategory)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = $this->latestDataDate($outstandingDate);

        $query = DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.investor_id', '=', 'latest_data.investor_id')
                    ->where(function ($query) {
                        $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                              ->orWhereNull('latest_data.latest_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', '=', 'mp.asset_class_id')
            ->join('m_asset_categories as mac2', 'mac2.asset_category_id', '=', 'mac.asset_category_id')
            ->where('tao.outstanding_date', $outstandingDate)
            ->where('tao.investor_id', $investorId)
            ->where('tao.is_active', 'Yes')
            ->where('mp.is_active', 'Yes')
            ->where('mac.is_active', 'Yes')
            ->where('mac2.is_active', 'Yes')
            ->where(DB::raw('LOWER(mac2.asset_category_name)'), strtolower($assetCategory));

        if (strtolower($assetClass) == 'saving' || strtolower($assetClass) == 'tabungan') {
            $query->whereIn(DB::raw('LOWER(mac.asset_class_name)'), ['saving', 'tabungan']);
        } else {
            $query->where(DB::raw('LOWER(mac.asset_class_name)'), strtolower($assetClass));
        }
        
        return $query->sum('tao.realized_gl');
    }

    public function totalUnrealizedReturn($investorId, $outstandingDate, $assetCategory)
    {
        // Subquery untuk mendapatkan data_date terbaru per product_name dan account_no
        $subQuery = $this->latestDataDate($outstandingDate);

        return DB::table('t_assets_outstanding as tao')
            ->joinSub($subQuery, 'latest_data', function ($join) {
                $join->on('tao.product_id', '=', 'latest_data.product_id')
                    ->on('tao.account_no', '=', 'latest_data.account_no')
                    ->on('tao.investor_id', '=', 'latest_data.investor_id')
                    ->where(function ($query) {
                        $query->whereColumn('tao.data_date', '=', 'latest_data.latest_data_date')
                              ->orWhereNull('latest_data.latest_data_date');
                    });
            })
            ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
            ->join('m_asset_class as mac', 'mac.asset_class_id', '=', 'mp.asset_class_id')
            ->join('m_asset_categories as mac2', 'mac2.asset_category_id', '=', 'mac.asset_category_id')
            ->where('tao.outstanding_date', $outstandingDate)
            ->where('tao.investor_id', $investorId)
            ->where('tao.is_active', 'Yes')
            ->where('mp.is_active', 'Yes')
            ->where('mac.is_active', 'Yes')
            ->where('mac2.is_active', 'Yes')
            ->where(DB::raw('LOWER(mac2.asset_category_name)'), strtolower($assetCategory))
            ->sum('tao.return_amount');
    }
}