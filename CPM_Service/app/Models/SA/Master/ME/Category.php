<?php

namespace App\Models\SA\Master\ME;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Category extends Model
{
    protected $table        = 'm_macro_economic_categories';
    protected $primaryKey   = 'me_category_id';
    protected $fillable     = ['me_category_name', 'description', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'me_category_name' => ['required', Rule::unique('m_macro_economic_categories')->ignore($id,'me_category_id')->where(function ($query) {
                return $query->where('is_active', 'Yes');
            })]
		];
    }
}
