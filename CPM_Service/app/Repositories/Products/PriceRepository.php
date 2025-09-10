<?php

namespace App\Repositories\Products;

use App\Interfaces\Products\PriceRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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
        $results = [];

        // Ambil harga NAV terbaru per produk, cache per produk
        foreach ($productIds as $id) {
            $cacheKey = "latest_price_{$id}_" . Carbon::now()->toDateString();

            $price = Cache::remember($cacheKey, 3600, function () use ($id) {
                return DB::table('m_products_prices')
                    ->where('product_id', $id)
                    ->where('is_active', 'Yes')
                    ->orderByDesc('price_date')
                    ->limit(1)
                    ->select('product_id', 'price_date', 'price_value')
                    ->first();
            });

            if ($price) {
                $results[] = $price;
            }
        }

        // Ambil tanggal NAV paling baru secara global
        $latestNavDate = Cache::remember('latest_nav_date_' . Carbon::now()->toDateString(), 3600, function () {
            return DB::table('m_products_prices')
                ->where('is_active', 'Yes')
                ->max('price_date');
        });

        // Tambahkan latest_nav_date ke setiap baris hasil
        $results = array_map(function ($row) use ($latestNavDate) {
            $row->latest_nav_date = $latestNavDate;
            return $row;
        }, $results);

        return $results;
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