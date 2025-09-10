<?php

namespace App\Models\SA\UI\Landings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Slide extends Model
{
    protected $table        = 'm_slides';
    protected $primaryKey   = 'slide_id';
    protected $fillable     = ['slide_name', 'slide_img', 'slide_view', 'sequence_to', 'created_by', 'created_host'];
	
	public static function rules($id = null, $request)
	{
		$rules = [
            'slide_name'    => ['required', Rule::unique('m_slides')->ignore($id, 'slide_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'sequence_to'   => 'required|numeric|min:1',
            'slide_view'    => 'required',
		];

        if($request->file('slide_img'))
        {
            $rules = array_merge($rules, ['slide_img' => 'required|image|mimes:jpeg,png,jpg'
                                            ]);
        }

        return $rules;
	}
}
