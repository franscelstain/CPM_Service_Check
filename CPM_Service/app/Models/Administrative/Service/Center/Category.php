<?php

namespace App\Models\Administrative\Service\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Category extends Model
{
    protected $table        = 'c_help_center_categories';
    protected $primaryKey   = 'category_id';
    protected $fillable		= ['category_name', 'category_image', 'description', 'created_by', 'created_host'];
    
    public static function rules($id = null, $request)
    {
        $rules = [
        	'category_name' => ['required', Rule::unique('c_help_center_categories')->ignore($id, 'category_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })]
        ];

        if($request->hasFile('category_image'))
        {
            $rules = array_merge($rules, ['category_image' => 'required|image|mimes:jpeg,png,jpg,gif']);
        }

        return $rules;
    }
}