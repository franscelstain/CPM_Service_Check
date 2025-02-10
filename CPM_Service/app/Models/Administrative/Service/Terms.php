<?php

namespace App\Models\Administrative\Service;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Terms extends Model
{
    protected $table        = 'c_terms_conditions';
    protected $primaryKey   = 'terms_id';
    protected $fillable 	= ['terms_code', 'terms_name', 'terms_value', 'created_by', 'created_host'];

    public static function rules($id=null, $request) 
    {
    	$rules = [
    		'terms_code'	=> ['required', 'min:2', 'max:100', Rule::unique('c_terms_conditions')->ignore($id, 'terms_id')->where(function ($query){
                                    return $query->where('is_active', 'Yes');
                               })],
    		'terms_name'	=> ['required', 'min:2', 'max:100', Rule::unique('c_terms_conditions')->ignore($id, 'terms_id')->where(function ($query){
                                    return $query->where('is_active', 'Yes');
                               })],
    	];
        return $rules;
    }
}
