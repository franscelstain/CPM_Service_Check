<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Price extends Model
{
    protected $table        = 'm_products_prices';
    protected $primaryKey   = 'price_id';
    // protected $fillable		= ['product_id', 'price_date', 'price_value', 'aum', 'open_price', 'closed_rice',
    //                            'adj_closed_price', 'high_price', 'low_price', 'bid', 'volume', 'created_by', 'created_host'];
    protected $guarded      = ['price_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'product_id'        => 'required',
            //'aum'  				=> 'required',
            'price_date'        => 'required|date',
            'price_value'       => 'required|numeric',
        ];
    }

    public static function rules_import($id = null)
    {
        return [
            'product_id'        => 'required',
            'price_date'        => 'required|date',
            'price_value'       => 'required|numeric',
        ];
    }
}
