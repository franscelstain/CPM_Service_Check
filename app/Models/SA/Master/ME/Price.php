<?php

namespace App\Models\SA\Master\ME;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Price extends Model
{
    protected $table        = 'm_macro_economic_prices';
    protected $primaryKey   = 'me_price_id';
    protected $fillable     = ['me_category_id', 'effective_date', 'me_price', 'me_type', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'me_category_id'    => ['required', Rule::unique('m_macro_economic_prices')->ignore($id, 'me_price_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'me_price'          => 'required',
            'me_type'           => 'required',
            'effective_date'    => 'required|date'
		];
    }
}
