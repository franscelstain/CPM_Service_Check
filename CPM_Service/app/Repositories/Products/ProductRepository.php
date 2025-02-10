<?php

namespace App\Repositories\Products;

use App\Interfaces\Products\ProductRepositoryInterface;
use App\Models\SA\Assets\Products\Product;

class ProductRepository implements ProductRepositoryInterface
{
    public function countProductByAssetCategory($category)
    {
        return Product::join('m_asset_class as mac', 'm_products.asset_class_id', '=', 'mac.asset_class_id')
            ->join('m_asset_categories as mact', 'mac.asset_category_id', '=', 'mact.asset_category_id')
            ->where('mact.asset_category_name', $category)
            ->where('m_products.is_active', 'Yes')
            ->where('mac.is_active', 'Yes')
            ->where('mact.is_active', 'Yes')
            ->count();
    }

    public function mutualFundProductList($search, $limit = 10, $page = 1, $colName = 'product_name', $colSort = 'asc')
    {
        $query = Product::join('m_asset_class as mac', 'm_products.asset_class_id', '=', 'mac.asset_class_id')
            ->join('m_asset_categories as mact', 'mac.asset_category_id', '=', 'mact.asset_category_id')
            ->leftJoin('m_issuer as mi', function($join) {
                $join->on('m_products.issuer_id', '=', 'mi.issuer_id')
                     ->where('mi.is_active', 'Yes');
            })
            ->leftJoin('m_products_period as mpp', function($join) { 
                $join->on('m_products.product_id', '=', 'mpp.product_id')
                     ->where('mpp.is_active', 'Yes'); 
            })
            ->where('mact.asset_category_name', 'Mutual Fund')
            ->where('m_products.is_active', 'Yes')
            ->where('mac.is_active', 'Yes')
            ->where('mact.is_active', 'Yes');

        if (!empty($search)) {
            $like = env('DB_CONNECTION') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function($qry) use ($search, $like) {
                $qry->where('m_products.product_name', $like, '%' . $search . '%')
                    ->orWhere('mi.issuer_name', $like, '%' . $search . '%')
                    ->orWhere('mac.asset_class_name', $like, '%' . $search . '%');
            });
        }

        return $query->orderBy($colName, $colSort)
                ->select('m_products.product_id', 'm_products.product_name', 'mac.asset_class_name', 
                        'mi.issuer_name', 'mi.issuer_logo', 'mpp.return_1day', 'mpp.price')
                ->paginate($limit, ['*'], 'page', $page);
    }
}