<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Fee extends Model
{
    protected $table        = 'm_products_fee';
    protected $primaryKey   = 'fee_product_id';
    protected $guarded      = ['fee_product_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null, $request)
    {
        return [
            'product_id'        => ['required', Rule::unique('m_products_fee')->ignore($id, 'fee_product_id')->where(function ($query) use ($request){
                                        return $query->where([['is_active', 'Yes'],['fee_id', $request->fee_id]]);
                                   })],
            'fee_value'         => 'required|numeric',
            'effective_date'    => 'required|date|date_format:Y-m-d',
            'description'       => 'max:225',
            'fee_id'            => 'required',
            'value_type'        => 'required'
        ];
    }
}
