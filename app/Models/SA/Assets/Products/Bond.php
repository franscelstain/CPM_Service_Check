<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Bond extends Model
{
    protected $table        = 'm_products_bonds';
    protected $primaryKey   = 'bond_id';
    protected $guarded      = ['bond_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null, $request)
    {
        return [
            'product_id'        => ['required', Rule::unique('m_products_fee')->ignore($id, 'bond_id')->where(function ($query) use ($request){
                                        return $query->where([['is_active', 'Yes'],['bond_id', $request->bond_id]]);
                                   })],
            'bond_id'            => 'required'
        ];
    }
}
