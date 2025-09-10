<?php

namespace App\Interfaces\Products;

interface ProductRepositoryInterface
{
    public function countProductByAssetCategory($category);
    public function mutualFundProductList($search, $limit, $page, $colName, $colSort);
}