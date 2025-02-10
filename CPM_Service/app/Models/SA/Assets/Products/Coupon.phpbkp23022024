<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Coupon extends Model
{
    protected $table        = 't_coupon';
    protected $primaryKey   = 'coupon_id';
    protected $guarded      = ['coupon_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'product_name'  => 'required',
            'coupon_rate'   => 'required',
            'coupon_date'   => 'required',
            'coupon_type'   => 'required',
        ];
    }
    
    public static function rulesImport()
    {
        return [
            // 'file_import' => 'required|max:2048|mimes:xls,xlsx'
            'file_import' => 'required|max:2048|mimes:xlsx'
        ];
    }
}
