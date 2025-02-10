<?php

namespace App\Repositories\Products;

use App\Interfaces\Products\PriceRepositoryInterface;
use DB;

class PriceRepository implements PriceRepositoryInterface
{
    public function countProduct()
    {
        return DB::table('m_products_prices as mpp')
                ->join('m_products as mp', 'mpp.product_id', '=', 'mp.product_id')
                ->where('mpp.is_active', 'Yes')
                ->where('mp.is_active', 'Yes')
                ->count();
    }

    public function getLatestProductPrices(array $productIds)
    {
        $latestPrice = DB::table('m_products_prices')
            ->where('is_active', 'Yes')
            ->whereIn('product_id', $productIds)
            ->select('product_id', DB::raw("max(price_date) as latest_date"))
            ->groupBy('product_id');

        return DB::table('m_products_prices as mpp')
            ->joinSub($latestPrice, 'latest', function ($join) {
                $join->on('mpp.product_id', 'latest.product_id')
                     ->on('mpp.price_date', 'latest.latest_date');
            })
            ->select(
                'mpp.product_id', 
                'mpp.price_date', 
                'mpp.price_value', 
                DB::raw('MAX(mpp.price_date) OVER () as latest_nav_date'
            ))
            ->get();
    }

    public function listData($filters, $limit = 10, $page = 1, $colName = 'product_name', $colSort = 'asc')
    {
        $query = DB::table('m_products_prices as mpp')
                ->join('m_products as mp', 'mpp.product_id', '=', 'mp.product_id')
                ->where('mpp.is_active', 'Yes')
                ->where('mp.is_active', 'Yes');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $like = env('DB_CONNECTION') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function($qry) use ($search, $like) {
                $qry->where('mp.product_name', $like, '%' . $search . '%')
                    ->orWhere('mpp.price_date', $like, '%' . $search . '%')
                    ->orWhere('mpp.price_value', $like, '%' . $search . ' %');
            });
        }

        // Tambahkan filter berdasarkan price_date jika ada
        if (!empty($filters['price_date'])) {
            $query->where('mpp.price_date', '=', $filters['price_date']);
        }

        // Tambahkan filter berdasarkan price_value jika ada
        if (!empty($filters['price_value'])) {
            $query->where('mpp.price_value', '=', $filters['price_value']);
        }

        return $query->orderBy($colName, $colSort)
                ->select('mpp.price_id', 'mp.product_id', 'mp.product_name', 'mpp.price_date', 'mpp.price_value')
                ->paginate($limit, ['*'], 'page', $page);
    }
}