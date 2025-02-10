<?php

namespace App\Models\SA\UI\Landings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Feature extends Model
{
    protected $table        = 'm_features';
    protected $primaryKey   = 'feature_id';
    protected $fillable     = ['feature_name', 'feature_img', 'sequence_to', 'description', 'created_by', 'created_host'];

    public static function rules($id = null, $request)
    {
    	$rules = [
            'feature_name'  => ['required', Rule::unique('m_features')->ignore($id, 'feature_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'sequence_to'   => 'required|numeric|min:1',
            'description'   => 'max:160',
    	];

        if($request->file('feature_img'))
        {
            $rules = array_merge($rules, ['feature_img' => 'required|image|mimes:jpeg,png,jpg']);
        }

        return $rules;
    }
}
