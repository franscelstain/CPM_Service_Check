<?php

namespace App\Imports\SA\Assets\Products;

use App\Models\SA\Assets\Products\Coupon;
use Maatwebsite\Excel\Concerns\ToModel;

class CouponImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Coupon([
            'product_id'    => $row[0],
            'coupon_type'   => $row[1],
            'coupon_rate'   => $row[2],
            'coupon_date'   => $row[3]
        ]);
    }
}
