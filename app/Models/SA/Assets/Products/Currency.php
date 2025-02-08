<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Currency extends Model
{
    protected $table        = 'm_currency';
    protected $primaryKey   = 'currency_id';
    protected $guarded      = ['currency_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return  
        [
            'currency_code' => ['required', Rule::unique('m_currency')->ignore($id, 'currency_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'currency_name' => ['required', 'max:50', Rule::unique('m_currency')->ignore($id, 'currency_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'symbol'        => 'required'
        ];
    }
}
