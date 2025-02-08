<?php

namespace App\Interfaces\Products;

interface PriceRepositoryInterface
{
    public function countProduct();
    public function getLatestProductPrices(array $productIds);
    public function listData(array $filters, $limit, $page, $colName, $colSort);
}