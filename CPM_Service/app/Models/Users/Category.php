<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Category extends Model
{
    protected $table        = 'u_users_categories';
    protected $primaryKey   = 'usercategory_id';
    protected $fillable     = ['usercategory_name', 'description', 'created_by', 'created_host'];
	
	public static function rules($id = null)
	{
		return [
			'usercategory_name' => ['required', Rule::unique('u_users_categories')->ignore($id, 'usercategory_id')->where(function ($query) {
                return $query->where('is_active', 'Yes');
            })],
            'description' => 'required|min:1|max:225'
		];
	}
}
