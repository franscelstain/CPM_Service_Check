<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Issuer extends Model
{
    protected $table        = 'm_issuer';
    protected $primaryKey   = 'issuer_id';
    protected $guarded      = ['issuer_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules($id = null, $request)
    {
    	$rules = [
            'issuer_name'   => ['required', Rule::unique('m_issuer')->ignore($id, 'issuer_id')->where(function ($query){ return $query->where('is_active', 'Yes'); })]
        ];
        if($request->file('issuer_logo'))
        {
        	$rules = array_merge($rules, ['issuer_logo' => 'required|image|mimes:jpeg,png,jpg,gif']);
        }

        return $rules;
    }
}
