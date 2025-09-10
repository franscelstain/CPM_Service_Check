<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Category extends Model
{
    protected $table        = 'u_investors_categories';
    protected $primaryKey   = 'investor_category_id';
    protected $fillable     = ['investor_category_name', 'description', 'created_by', 'created_host'];
	
	public static function rules($id = null)
	{
		return [
			'investor_category_name' => ['required', Rule::unique('u_investors_categories')->ignore($id, 'investor_category_id')->where(function ($query) {
                return $query->where('is_active', 'Yes');
            })],
            'description' => 'min:1|max:225'
		];
	}
}
