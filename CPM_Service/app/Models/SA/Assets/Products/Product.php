<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Product extends Model
{
    protected $table        = 'm_products';
    protected $primaryKey   = 'product_id';
    protected $guarded      = ['product_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'product_code'          => ['required', Rule::unique('m_products')->ignore($id, 'product_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                    })],
            'product_name'          => 'required',
            'asset_class_id'        => 'required',
            'currency_id'           => 'required',
            'issuer_id'             => 'required',
            'profile_id'            => 'required',
            'product_type'          => 'required',
            'launch_date'           => 'required|date_format:Y-m-d',
            'min_buy'               => 'required',
            'max_buy'               => 'required',
            'min_sell'              => 'required',
            'max_sell'              => 'required',
            'multiple_purchase'     => 'required',
            'offering_period_start' => 'date_format:Y-m-d',
            'offering_period_end'   => 'date_format:Y-m-d',
            'exit_windows_start'    => 'date_format:Y-m-d',
            'exit_windows_end'      => 'date_format:Y-m-d',
            'maturity_date'         => 'date_format:Y-m-d',
        ];
    }
}
