<?php

namespace App\Models\SA\UI\Landings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Module extends Model
{
    protected $table        = 'm_modules';
    protected $primaryKey   = 'module_id';
    protected $fillable     = ['module_title', 'module_subtitle', 'module_text', 'module_img', 'sequence_to', 'created_by', 'created_host'];
	
	public static function rules($id = null, $request)
	{
		$rules = [
            'module_title'      => ['required', Rule::unique('m_modules')->ignore($id, 'module_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'module_subtitle'   => 'required',
            'module_text'		=> 'required',
            'sequence_to'       => 'required|numeric|min:1'
		];

        if($request->file('module_img'))
        {
            $rules = array_merge($rules, ['module_img' => 'required|image|mimes:jpeg,png,jpg'
                                            ]);
        }

        return $rules;
	}
}
