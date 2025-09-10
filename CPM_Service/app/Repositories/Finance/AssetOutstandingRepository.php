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

    public function baseQueryInvestorsByCategoryWithCurrentBalance($categoryIds)
    {
        return DB::table('t_assets_outstanding as tao')
                ->join('u_investors as ui', 'tao.investor_id', '=', 'ui.investor_id')
                ->join('m_products as mp', 'mp.product_id', '=', 'tao.product_id')
                ->join('m_asset_class as mac', 'mac.asset_class_id', '=', 'mp.asset_class_id')
                ->join('m_asset_categories as mact', 'mact.asset_category_id', '=', 'mac.asset_category_id')
                ->whereIn('mact.asset_category_id', $categoryIds)
                ->where('tao.is_active', 'Yes')
                ->where('ui.is_active', 'Yes')
                ->where('mp.is_active', 'Yes')
                ->where('mac.is_active', 'Yes')
                ->where('mact.is_active', 'Yes')
                ->orderBy('tao.investor_id')
                ->orderBy('tao.account_no')
                ->orderBy('tao.product_id')
                ->orderByDesc('tao.data_date')
                ->orderByDesc('tao.outstanding_id');
    }

    public function countInvestorsByCategoryWithCurrentBalance($outDate, $categoryIds, $targetAum, $salesId = null)
    {
        $subquery = $this->baseQueryInvestorsByCategoryWithCurrentBalance($categoryIds);
        $subquery->selectRaw('DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.investor_id,
                    tao.balance_amount
                ')
                ->whereDate('tao.outstanding_date', $outDate);
        if ($salesId) {
            $subquery->where('ui.sales_id', $salesId);
        }
        return DB::table(DB::raw("({$subquery->toSql()}) as a"))
                ->mergeBindings($subquery)
                ->select('investor_id')
                ->groupBy('investor_id')
                ->havingRaw('SUM(balance_amount) <= ' . $targetAum)
                ->count();
    }

    public function getAssetMutualClass($investorId, $latestDate)
    {    
        return DB::select("
            SELECT
                asset_class_name,
                asset_class_color,
                SUM(balance_amount) AS balance
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.balance_amount,
                    mac.asset_class_name,
                    mac.asset_class_color
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON mp.product_id = tao.product_id
                JOIN m_asset_class mac ON mac.asset_class_id = mp.asset_class_id
                JOIN m_asset_categories mact ON mact.asset_category_id = mac.asset_category_id
                WHERE tao.investor_id = ?
                    AND tao.outstanding_date = ?
                    AND LOWER(mact.asset_category_name) = 'mutual fund'
                    AND tao.outstanding_unit >= 1
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
            ) AS assets
            GROUP BY asset_class_name, asset_class_color
        ", [$investorId, $latestDate]);
    }

    public function getCategoryBalance($investorId, $latestDate)
    {
        return DB::select("
            SELECT
                CASE
                    WHEN LOWER(asset_category_name) = 'mutual fund' THEN 'mutual_fund'
                    WHEN LOWER(asset_category_name) = 'bonds' THEN 'bonds'
                    WHEN LOWER(asset_category_name) IN ('insurance', 'bancassurance') THEN 'insurance'
                    WHEN LOWER(asset_category_name) = 'dpk' AND LOWER(asset_class_name) IN ('saving', 'tabungan') THEN 'saving'
                    WHEN LOWER(asset_category_name) = 'dpk' AND LOWER(asset_class_name) IN ('deposit', 'deposito') THEN 'deposit'
                    ELSE 'other'
                END AS category_key,
                SUM(balance_amount) AS total_balance
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.balance_amount,
                    mac.asset_class_name,
                    mact.asset_category_name
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON mp.product_id = tao.product_id
                JOIN m_asset_class mac ON mac.asset_class_id = mp.asset_class_id
                JOIN m_asset_categories mact ON mact.asset_category_id = mac.asset_category_id
                WHERE tao.investor_id = ?
                    AND tao.outstanding_date = ?
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
            ) AS latest_assets
            GROUP BY category_key
        ", [$investorId, $latestDate]);
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
        return DB::select("
            SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                mp.product_name,
                tao.account_no,
                tao.currency,
                tao.balance_amount,
                tao.kurs,
                tao.data_date,
                tao.balance_amount_original,
                tao.realized_gl_original,
                tao.realized_gl,
                CASE WHEN tao.currency != 'IDR' THEN tse.rate ELSE 1 END AS rate
            FROM t_assets_outstanding tao
            JOIN u_investors ui ON tao.investor_id = ui.investor_id
            JOIN m_products mp ON mp.product_id = tao.product_id
            JOIN m_asset_class mac ON mac.asset_class_id = mp.asset_class_id
            JOIN m_asset_categories mact ON mact.asset_category_id = mac.asset_category_id
            LEFT JOIN t_stg_exchange_rates as tse ON tao.currency = tse.currency AND tao.outstanding_date = tse.date
            WHERE tao.investor_id = ?
                AND tao.outstanding_date = ?
                AND LOWER(mact.asset_category_name) = 'dpk'
                AND tao.balance_amount >= 1
                AND tao.is_active = 'Yes'
                AND ui.is_active = 'Yes'
                AND mp.is_active = 'Yes'
                AND mac.is_active = 'Yes'
                AND mact.is_active = 'Yes'
            ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
        ", [$investorId, $latestDate]);        
    }

    public function listBondsAsset($investorId, $latestDate)
    {
        return DB::select("
            SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                tao.product_id,
                mp.product_name,
                tao.account_no,
                tao.currency,
                tao.balance_amount,
                tao.kurs,
                tao.data_date,
                tao.balance_amount_original,
                tao.unrealized_gl_original,
                tao.unrealized_gl,
                tao.unrealized_gl_pct,
                tao.return_amount,
                tao.placement_amount_original,
                CASE WHEN tao.currency != 'IDR' THEN tse.rate ELSE 1 END AS rate
            FROM t_assets_outstanding tao
            JOIN u_investors ui ON tao.investor_id = ui.investor_id
            JOIN m_products mp ON mp.product_id = tao.product_id
            JOIN m_asset_class mac ON mac.asset_class_id = mp.asset_class_id
            JOIN m_asset_categories mact ON mact.asset_category_id = mac.asset_category_id
            LEFT JOIN t_stg_exchange_rates as tse ON tao.currency = tse.currency AND tao.outstanding_date = tse.date
            WHERE tao.investor_id = ?
                AND tao.outstanding_date = ?
                AND LOWER(mact.asset_category_name) = 'bonds'
                AND tao.balance_amount >= 1
                AND tao.is_active = 'Yes'
                AND ui.is_active = 'Yes'
                AND mp.is_active = 'Yes'
                AND mac.is_active = 'Yes'
                AND mact.is_active = 'Yes'
            ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
        ", [$investorId, $latestDate]);
    }

    public function listInsuranceAsset($investorId, $latestDate)
    {
        return DB::select("
            SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                mp.product_name,
                tao.account_no,
                tao.currency,
                tao.balance_amount,
                tao.kurs,
                tao.data_date,
                tao.balance_amount_original,
                tao.placement_amount,
                tao.premium_amount,
                mi.issuer_name,
                CASE WHEN tao.currency != 'IDR' THEN tse.rate ELSE 1 END AS rate
            FROM t_assets_outstanding tao
            JOIN u_investors ui ON tao.investor_id = ui.investor_id
            JOIN m_products mp ON mp.product_id = tao.product_id
            JOIN m_asset_class mac ON mac.asset_class_id = mp.asset_class_id
            JOIN m_asset_categories mact ON mact.asset_category_id = mac.asset_category_id
            LEFT JOIN m_issuer as mi ON mi.issuer_id = mp.issuer_id AND mi.is_active = 'Yes'
            LEFT JOIN t_stg_exchange_rates as tse ON tao.currency = tse.currency AND tao.outstanding_date = tse.date
            WHERE tao.investor_id = ?
                AND tao.outstanding_date = ?
                AND LOWER(mact.asset_category_name) IN ('insurance', 'bancassurance')
                AND tao.is_active = 'Yes'
                AND ui.is_active = 'Yes'
                AND mp.is_active = 'Yes'
                AND mac.is_active = 'Yes'
                AND mact.is_active = 'Yes'
            ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
        ", [$investorId, $latestDate]);
    }

    public function listMutualFundAsset($investorId, $latestDate)
    {
        return DB::select("
            SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                tao.product_id,
                mp.product_name,
                tao.account_no,
                tao.currency,
                tao.balance_amount,
                tao.kurs,
                tao.data_date,
                tao.balance_amount_original,
                tao.return_percentage,
                tao.unrealized_gl_original,
                tao.return_amount,
                tao.outstanding_unit,
                mac.asset_class_name,
                tao.kurs AS rate
            FROM t_assets_outstanding tao
            JOIN u_investors ui ON tao.investor_id = ui.investor_id
            JOIN m_products mp ON mp.product_id = tao.product_id
            JOIN m_asset_class mac ON mac.asset_class_id = mp.asset_class_id
            JOIN m_asset_categories mact ON mact.asset_category_id = mac.asset_category_id
            LEFT JOIN t_stg_exchange_rates as tse ON tao.currency = tse.currency AND tao.outstanding_date = tse.date
            WHERE tao.investor_id = ?
                AND tao.outstanding_date = ?
                AND LOWER(mact.asset_category_name) = 'mutual fund'
                AND tao.outstanding_unit >= 1
                AND tao.is_active = 'Yes'
                AND ui.is_active = 'Yes'
                AND mp.is_active = 'Yes'
                AND mac.is_active = 'Yes'
                AND mact.is_active = 'Yes'
            ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
        ", [$investorId, $latestDate]);
    }
    
    public function totalAsset($investorId, $outDate)
    {
        $query = DB::selectOne("
            SELECT SUM(balance_amount) AS total_balance_amount
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.balance_amount
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON tao.product_id = mp.product_id
                JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id
                JOIN m_asset_categories mact ON mac.asset_category_id = mact.asset_category_id
                WHERE tao.investor_id = ?
                    AND tao.outstanding_date = ?
                    AND tao.balance_amount >= 1
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
            ) AS filtered
        ", [$investorId, $outDate]);

        return $query->total_balance_amount ?? 0;
    }

    public function totalRealizedGL($investorId, $outstandingDate, $assetClass, $assetCategory)
    {
        $query = DB::selectOne("
            SELECT SUM(realized_gl) AS total_realized_gl
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.realized_gl
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON tao.product_id = mp.product_id
                JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id
                JOIN m_asset_categories mact ON mac.asset_category_id = mact.asset_category_id
                WHERE tao.investor_id = ?
                    AND tao.outstanding_date = ?
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                    AND LOWER(mact.asset_category_name) = ?
                    " . (
                        in_array(strtolower($assetClass), ['saving', 'tabungan']) 
                        ? "AND LOWER(mac.asset_class_name) IN ('saving', 'tabungan')" 
                        : "AND LOWER(mac.asset_class_name) = ?"
                    ) . "
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
            ) AS filtered
        ", in_array(strtolower($assetClass), ['saving', 'tabungan'])
            ? [$investorId, $outstandingDate, strtolower($assetCategory)]
            : [$investorId, $outstandingDate, strtolower($assetCategory), strtolower($assetClass)]
        );

        return $query->total_realized_gl ?? 0;
    }

    public function totalUnrealizedReturn($investorId, $outstandingDate, $assetCategory)
    {
        $query = DB::selectOne("
            SELECT SUM(return_amount) AS total_return_amount
            FROM (
                SELECT DISTINCT ON (tao.investor_id, tao.account_no, tao.product_id)
                    tao.return_amount
                FROM t_assets_outstanding tao
                JOIN u_investors ui ON tao.investor_id = ui.investor_id
                JOIN m_products mp ON tao.product_id = mp.product_id
                JOIN m_asset_class mac ON mp.asset_class_id = mac.asset_class_id
                JOIN m_asset_categories mact ON mac.asset_category_id = mact.asset_category_id
                WHERE tao.investor_id = ?
                    AND tao.outstanding_date = ?
                    AND tao.is_active = 'Yes'
                    AND ui.is_active = 'Yes'
                    AND mp.is_active = 'Yes'
                    AND mac.is_active = 'Yes'
                    AND mact.is_active = 'Yes'
                    AND LOWER(mact.asset_category_name) = ?
                ORDER BY tao.investor_id, tao.account_no, tao.product_id, (tao.data_date IS NULL), tao.data_date DESC, tao.outstanding_id DESC
            ) AS filtered
        ", [$investorId, $outstandingDate, strtolower($assetCategory)]);

        return $query->total_return_amount ?? 0;
    }
}