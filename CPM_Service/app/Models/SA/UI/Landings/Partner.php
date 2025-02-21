<?php

namespace App\Models\SA\UI\Landings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Partner extends Model
{
    protected $table        = 'm_partners';
    protected $primaryKey   = 'partner_id';
    protected $fillable     = ['partner_name', 'partner_img', 'partner_view', 'sequence_to', 'created_by', 'created_host'];
	
	public static function rules($id = null, $request)
	{
		$rules = [
            'partner_name'  => ['required', Rule::unique('m_partners')->ignore($id, 'partner_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'sequence_to'   => 'required|numeric|min:1',
            'partner_view'  => 'required',
		];

        if($request->file('partner_img'))
        {
            $rules = array_merge($rules, ['partner_img' => 'required|image|mimes:jpeg,png,jpg']);
        }

        return $rules;
	}
}
