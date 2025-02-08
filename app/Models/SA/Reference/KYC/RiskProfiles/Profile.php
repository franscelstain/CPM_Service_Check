<?php

namespace App\Models\SA\Reference\KYC\RiskProfiles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Profile extends Model
{
    protected $table        = 'm_risk_profiles';
    protected $primaryKey   = 'profile_id';
    protected $guarded      = ['profile_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null, $request)
    {
    	$rules = [
            'profile_name'  => ['required', Rule::unique('m_risk_profiles')->ignore($id, 'profile_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'min'           => 'required',
            'max'           => 'required|gte:min',
            'sequence_to'   => 'required|numeric|min:1'
    	];

        if($request->file('profile_image'))
        {
            $rules = array_merge($rules, ['profile_image' => 'required|image|mimes:jpeg,png,jpg'
                                            ]);
        }

        return $rules;
    }
}
