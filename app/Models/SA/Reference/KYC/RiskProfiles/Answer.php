<?php

namespace App\Models\SA\Reference\KYC\RiskProfiles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Answer extends Model
{
    protected $table        = 'm_profile_answers';
    protected $primaryKey   = 'answer_id';
    protected $guarded      = ['answer_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null, $request)
    {
    	$rules = [
            'answer_score'   => 'numeric|min:1'
        ];
        
        if($request->file('icon'))
        {
            $rules = array_merge($rules, ['icon' => 'image|mimes:jpeg,png,jpg,gif'
                                            ]);
        }

        return $rules;
    }
}
