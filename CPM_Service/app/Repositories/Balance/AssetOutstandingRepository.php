<?php

namespace App\Repositories\Balance;

use App\Interfaces\Balance\AssetOutstandingRepositoryInterface;
use App\Models\Crm\ImportedAssetDpk;
use App\Models\Crm\ImportedAssetInvest;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\SA\Assets\Products\Price;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AssetOutstandingRepository implements AssetOutstandingRepositoryInterface
{
    public function getTotalAssetsLiabilities(Request $request, $id)
    {
        try
        {
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));

            $latestOut = DB::table('t_assets_outstanding')
                        ->where([['is_active', 'Yes'],
                                ['investor_id', $id],
                                ['outstanding_date', '>=', $start_date],
                                ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');

            $assets = DB::table('t_assets_outstanding')
                    ->where([
                        ['investor_id', $id],
                        ['balance_amount', '>=', 1],
                        ['is_active', 'Yes'],
                        ['outstanding_date', $latestOut]
                    ])
                    ->sum('balance_amount');

            $latestLiab = DB::table('t_liabilities_outstanding')
                        ->where([['is_active', 'Yes'],
                            ['investor_id', $id],
                            ['outstanding_date', '>=', $start_date],
                            ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');
            
            $liabilities = DB::table('t_liabilities_outstanding')
                        ->where([['investor_id', $id], ['outstanding_date', $latestLiab], ['is_active', 'Yes']])
                        ->sum('outstanding_balance');

            return [
                'assets' => (float) $assets ?? 0,
                'liabilities' => (float) $liabilities ?? 0,
                'outstanding_date' => $latestLiab != null ? date('d F Y', strtotime($latestLiab)) : ''
            ];
        } catch (\Exception $e) {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function getTotalReturnAssets(Request $request, $id)
    {
        try {
            $month = !empty($request->month) ? $request->month : 'm';
            $year = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date = date('Y-m-t', strtotime($start_date));
            $data = DB::select("SELECT
            (
                SELECT
                    SUM(realized_gl)
                FROM
                    t_assets_outstanding tao
                INNER JOIN (
                        SELECT
                            investor_id,
                            MAX(outstanding_date) AS out_date
                        FROM
                            t_assets_outstanding
                        WHERE
                            is_active = 'Yes'
                            AND outstanding_date >= '$start_date'
                            AND outstanding_date <= '$end_date'
                            AND investor_id = '$id'
                        GROUP BY
                            investor_id
                    )AS b ON b.investor_id = tao.investor_id
                JOIN m_products mp ON
                    mp.product_id = tao.product_id
                JOIN m_asset_class mac ON
                    mac.asset_class_id = mp.asset_class_id
                JOIN m_asset_categories mac2 ON
                    mac2.asset_category_id = mac.asset_category_id
                WHERE
                    LOWER(mac.asset_class_name) IN ('saving','tabungan')
                    AND LOWER(mac2.asset_category_name) = 'dpk'
                    AND outstanding_date = out_date
            ) AS saving,
            (
                SELECT
                    SUM(realized_gl)
                FROM
                    t_assets_outstanding tao
                INNER JOIN (
                        SELECT
                            investor_id,
                            MAX(outstanding_date) AS out_date
                        FROM
                            t_assets_outstanding
                        WHERE
                            is_active = 'Yes'
                            AND outstanding_date >= '$start_date'
                            AND outstanding_date <= '$end_date'
                            AND investor_id = '$id'
                        GROUP BY
                            investor_id
                    )AS b ON b.investor_id = tao.investor_id
                JOIN m_products mp ON
                    mp.product_id = tao.product_id
                JOIN m_asset_class mac ON
                    mac.asset_class_id = mp.asset_class_id
                JOIN m_asset_categories mac2 ON
                    mac2.asset_category_id = mac.asset_category_id
                WHERE
                    LOWER(mac.asset_class_name) IN ('deposit','deposito')
                    AND LOWER(mac2.asset_category_name) = 'dpk'
                    AND outstanding_date = out_date
            ) AS deposit,
            (
                SELECT
                    SUM(return_amount)
                FROM
                    t_assets_outstanding tao
                INNER JOIN (
                        SELECT
                            investor_id,
                            MAX(outstanding_date) AS out_date
                        FROM
                            t_assets_outstanding
                        WHERE
                            is_active = 'Yes'
                            AND outstanding_date >= '$start_date'
                            AND outstanding_date <= '$end_date'
                            AND investor_id = '$id'
                        GROUP BY
                            investor_id
                    )AS b ON b.investor_id = tao.investor_id
                JOIN m_products mp ON
                    mp.product_id = tao.product_id
                JOIN m_asset_class mac ON
                    mac.asset_class_id = mp.asset_class_id
                JOIN m_asset_categories mac2 ON
                    mac2.asset_category_id = mac.asset_category_id
                WHERE
                    LOWER(mac2.asset_category_name) = 'bonds'
                    AND outstanding_date = out_date
            ) AS bonds,
            (
                SELECT
                    SUM(return_amount)
                FROM
                    t_assets_outstanding tao
                INNER JOIN (
                        SELECT
                            investor_id,
                            MAX(outstanding_date) AS out_date
                        FROM
                            t_assets_outstanding
                        WHERE
                            is_active = 'Yes'
                            AND outstanding_date >= '$start_date'
                            AND outstanding_date <= '$end_date'
                            AND investor_id = '$id'
                        GROUP BY
                            investor_id
                    )AS b ON b.investor_id = tao.investor_id
                JOIN m_products mp ON
                    mp.product_id = tao.product_id
                JOIN m_asset_class mac ON
                    mac.asset_class_id = mp.asset_class_id
                JOIN m_asset_categories mac2 ON
                    mac2.asset_category_id = mac.asset_category_id
                WHERE
                    LOWER(mac2.asset_category_name) = 'mutual fund'
                    AND outstanding_date = out_date
            ) AS mutual_fund");
            if (!empty($data[0])) {
                $data = $data[0];
                $realized = $data->saving + $data->deposit;
                $unrealized = $data->bonds + $data->mutual_fund;
                $total = $realized + $unrealized;
                if ($total) {
                    $realized_percent = $realized / $total * 100;
                    $unrealized_percent = $unrealized / $total * 100;
                } else {
                    $realized_percent = 0;
                    $unrealized_percent = 0;
                }
                $data = array_merge((array) $data, (array) ['realized' => $realized, 'unrealized' => $unrealized, 'total' => $total, 'realized_percent' => $realized_percent, 'unrealized_percent' => $unrealized_percent]);
            }
            return $data;
        } catch (\Exception $e) {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function getAssetBank(Request $request, $id)
    {
        try
        {
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));
            $latest     = DB::table('t_assets_outstanding')
                        ->where([['is_active', 'Yes'],
                            ['investor_id', $id],
                            ['outstanding_date', '>=', $start_date],
                            ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');

            return DB::table('t_assets_outstanding as tao')
                    ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                    ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                    ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['dpk'])
                    ->where([['tao.investor_id', $id], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], 
                            ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['tao.balance_amount', '>=', 1],
                            ['tao.outstanding_date', $latest]])
                    ->whereExists(function ($qry) { 
                        $qry->select(DB::raw(1))
                            ->from('u_investors as ui')
                            ->whereColumn('ui.investor_id', 'tao.investor_id')
                            ->where('ui.is_active', 'Yes');
                    })
                    ->select('mp.product_name', 'tao.account_no', 'tao.currency', 'tao.balance_amount', 'tao.kurs',
                            'tao.data_date', 'tao.balance_amount_original', 'tao.balance_amount',
                            'tao.realized_gl_original', 'tao.realized_gl')
                    ->orderBy('tao.data_date', 'desc')
                    ->get();
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function getAssetBonds(Request $request, $id)
    {
        try
        {
            $data       = [];
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));
            $latest     = DB::table('t_assets_outstanding')
                        ->where([['is_active', 'Yes'],
                            ['investor_id', $id],
                            ['outstanding_date', '>=', $start_date],
                            ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');
            
            $assets     = DB::table('t_assets_outstanding as tao')
                        ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                        ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                        ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                        ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['bonds'])
                        ->where([['tao.investor_id', $id], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], 
                                ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['tao.balance_amount', '>=', 1],
                                ['tao.outstanding_date', $latest]])
                        ->whereExists(function ($qry) { 
                            $qry->select(DB::raw(1))
                                ->from('u_investors as ui')
                                ->whereColumn('ui.investor_id', 'tao.investor_id')
                                ->where('ui.is_active', 'Yes');
                        })
                        ->select('mp.product_id', 'mp.product_name', 'tao.account_no', 'tao.currency', 'tao.balance_amount', 'tao.kurs',
                                'tao.data_date', 'tao.balance_amount_original', 'tao.balance_amount', 'tao.return_percentage',
                                'tao.unrealized_gl_original', 'tao.return_amount', 'tao.placement_amount_original');
    
            $asset_get  = $assets->orderBy('mp.product_name')->get();
    
            if (!$asset_get->isEmpty())
            {
                $product_id = $asset_get->pluck('product_id');

                $latest_price = DB::table('m_products_prices')->where('is_active', 'Yes')
                                ->whereIn('product_id', $product_id)
                                ->select('product_id', DB::raw("max(price_date) as latest_date"))
                                ->groupBy('product_id');

                $price_amount = DB::table('m_products_prices as mpp')
                                ->joinSub($latest_price, 'latest', function($join){
                                    $join->on('mpp.product_id', 'latest.product_id')
                                            ->on('mpp.price_date', 'latest.latest_date');
                                })
                                ->select('mpp.product_id', 'mpp.price_date', 'mpp.price_value')->get();
                
                $coupon = DB::table('t_coupon')
                            ->select(DB::raw('MIN(coupon_date) as coupon_date'), 'product_id')
                            ->where([['deleted', false], ['coupon_date', '>', date('Y-m-d')]])
                            ->whereIn('product_id', $product_id)
                            ->groupBy('product_id')
                            ->get();
                
                $data = $asset_get->map(function ($as_get) use ($price_amount, $coupon) {
                    $price = $price_amount->where('product_id', $as_get->product_id)->first();
                    $as_get->price_value = $price->price_value ?? null;
                    $as_get->price_date = $price->price_date ?? null;
                    $as_get->coupon_date = $coupon->where('product_id', $as_get->product_id)->first()->coupon_date ?? null;
                    return $as_get;
                });
            }
            
            return $data;
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function getAssetCategory(Request $request, $id)
    {
        try
        {
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));

            return [
                [
                    'asset_category_name' => 'Mutual Fund',
                    'balance' => (float) $this->getAssetCategoryBalance($id, 'mutual_fund', $start_date, $end_date)
                ],
                [
                    'asset_category_name' => 'Bonds',
                    'balance' => (float) $this->getAssetCategoryBalance($id, 'bonds', $start_date, $end_date)
                ],
                [
                    'asset_category_name' => 'Saving',
                    'balance' => (float) $this->getAssetCategoryBalance($id, 'saving', $start_date, $end_date)
                ],
                [
                    'asset_category_name' => 'Insurance',
                    'balance' => (float) $this->getAssetCategoryBalance($id, 'insurance', $start_date, $end_date)
                ],
                [
                    'asset_category_name' => 'Deposit',
                    'balance' => (float) $this->getAssetCategoryBalance($id, 'deposit', $start_date, $end_date)
                ],
            ];

        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    private function getAssetCategoryBalance($id, $category, $start_date, $end_date)
    {
        try
        {
            $latest     = DB::table('t_assets_outstanding')
                            ->where([['is_active', 'Yes'],
                                ['investor_id', $id],
                                ['outstanding_date', '>=', $start_date],
                                ['outstanding_date', '<=', $end_date]
                            ])
                            ->max('outstanding_date');

            $assets     = DB::table('t_assets_outstanding as tao')
                        ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                        ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                        ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                        ->where([['tao.investor_id', $id], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], 
                                ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['tao.outstanding_date', $latest]])
                        ->whereExists(function ($qry) { 
                            $qry->select(DB::raw(1))
                                ->from('u_investors as ui')
                                ->whereColumn('ui.investor_id', 'tao.investor_id')
                                ->where('ui.is_active', 'Yes');
                        });
            if ($category == 'mutual_fund')
            {
                return $assets->where(DB::raw('LOWER(mact.asset_category_name)'), 'mutual fund')->sum('balance_amount');
            }
            elseif ($category == 'bonds')
            {
                return $assets->where(DB::raw('LOWER(mact.asset_category_name)'), 'bonds')->sum('balance_amount');
            }
            elseif ($category == 'saving')
            {
                return $assets->where(DB::raw('LOWER(mact.asset_category_name)'), 'dpk')
                                ->whereIn(DB::raw('LOWER(mac.asset_class_name)'), ['saving', 'tabungan'])
                                ->sum('balance_amount');
            }
            elseif ($category == 'insurance')
            {
                return $assets->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['insurance', 'bancassurance'])->sum('balance_amount');
            }
            elseif ($category == 'deposit')
            {
                return $assets->where(DB::raw('LOWER(mact.asset_category_name)'), 'dpk')
                                ->whereIn(DB::raw('LOWER(mac.asset_class_name)'), ['deposit', 'deposito'])
                                ->sum('balance_amount');
            }
            else
            {
                return 0;
            }
        }
        catch (\Exception $e)
        {
            return 0;
        }
    }

    public function getAssetInsurance(Request $request, $id)
    {
        try
        {
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));
            $latest     = DB::table('t_assets_outstanding')
                        ->where([['is_active', 'Yes'],
                            ['investor_id', $id],
                            ['outstanding_date', '>=', $start_date],
                            ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');

            return  DB::table('t_assets_outstanding as tao')
                        ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                        ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                        ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                        ->leftJoin('m_issuer as mi', function($join) {{ $join->on('mi.issuer_id', 'mp.issuer_id')->where('mi.is_active', 'Yes'); }})
                        ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['insurance', 'bancassurance'])
                        ->where([['tao.investor_id', $id], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], 
                                ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['tao.outstanding_date', $latest]])
                        ->whereExists(function ($qry) { 
                            $qry->select(DB::raw(1))
                                ->from('u_investors as ui')
                                ->whereColumn('ui.investor_id', 'tao.investor_id')
                                ->where('ui.is_active', 'Yes');
                        })
                        ->select('mp.product_name', 'tao.account_no', 'tao.currency', 'tao.balance_amount', 'tao.kurs',
                                'tao.data_date', 'tao.balance_amount_original', 'tao.balance_amount',
                                'tao.placement_amount', 'tao.premium_amount', 'mi.issuer_name')
                        ->get();
        } catch (\Exception $e) {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function getAssetMutual(Request $request, $id)
    {
        try
        {       
            $data       = [];
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));     
            $latest     = DB::table('t_assets_outstanding')
                        ->where([['is_active', 'Yes'],
                            ['investor_id', $id],
                            ['outstanding_date', '>=', $start_date],
                            ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');

            $assets = DB::table('t_assets_outstanding as tao')
                    ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                    ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                    ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['mutual fund'])
                    ->where([['tao.investor_id', $id], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], 
                            ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['tao.outstanding_unit', '>=', 1],
                            ['tao.outstanding_date', $latest]])
                    ->whereExists(function ($qry) { 
                        $qry->select(DB::raw(1))
                            ->from('u_investors as ui')
                            ->whereColumn('ui.investor_id', 'tao.investor_id')
                            ->where('ui.is_active', 'Yes');
                    })
                    ->select('mp.product_id', 'mp.product_name', 'tao.account_no', 'tao.currency', 'tao.balance_amount', 'tao.kurs',
                            'tao.data_date', 'tao.balance_amount_original', 'tao.balance_amount', 'tao.return_percentage',
                            'tao.unrealized_gl_original', 'tao.return_amount', 'tao.outstanding_unit', 'mac.asset_class_name');

            $asset_get  = $assets->orderBy('tao.data_date', 'desc')->get();

            if (!$asset_get->isEmpty())
            {
                $product_id = $asset_get->pluck('product_id');

                $latest_price = DB::table('m_products_prices')->where('is_active', 'Yes')
                                ->whereIn('product_id', $product_id)
                                ->select('product_id', DB::raw("max(price_date) as latest_date"))
                                ->groupBy('product_id');

                $price_amount = DB::table('m_products_prices as mpp')
                                ->joinSub($latest_price, 'latest', function($join){
                                    $join->on('mpp.product_id', 'latest.product_id')
                                         ->on('mpp.price_date', 'latest.latest_date');
                                })
                                ->select('mpp.product_id', 'mpp.price_date', 'mpp.price_value')->get();
                
                $data = $asset_get->map(function ($as_get) use ($price_amount) {
                    $price = $price_amount->where('product_id', $as_get->product_id)->first();
                    $as_get->price_value = $price->price_value ?? null;
                    $as_get->price_date = $price->price_date ?? null;
                    return $as_get;
                });
            }

            return $data;
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function getAssetMutualClass(Request $request, $id)
    {
        try
        {
            $month      = !empty($request->month) ? $request->month : 'm';
            $year       = !empty($request->year) ? $request->year : 'Y';
            $start_date = date($year . '-' . $month . '-01');
            $end_date   = date('Y-m-t', strtotime($start_date));
            $latest     = DB::table('t_assets_outstanding')
                        ->where([['is_active', 'Yes'],
                            ['investor_id', $id],
                            ['outstanding_date', '>=', $start_date],
                            ['outstanding_date', '<=', $end_date]
                        ])
                        ->max('outstanding_date');
            
            return  DB::table('t_assets_outstanding as tao')
                        ->join('m_products as mp', 'mp.product_id', 'tao.product_id')
                        ->join('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                        ->join('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                        ->whereIn(DB::raw('LOWER(mact.asset_category_name)'), ['mutual fund'])
                        ->where([['tao.investor_id', $id], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], 
                                ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['tao.outstanding_date', $latest],
                                ['tao.outstanding_unit', '>=', 1]])
                        ->whereExists(function ($qry) { 
                            $qry->select(DB::raw(1))
                                ->from('u_investors as ui')
                                ->whereColumn('ui.investor_id', 'tao.investor_id')
                                ->where('ui.is_active', 'Yes');
                        })
                        ->select('mac.asset_class_name', 'mac.asset_class_color', DB::raw('SUM(tao.balance_amount) as balance'))
                        ->groupBy('mac.asset_class_name', 'mac.asset_class_color')
                        ->get();
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
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
}