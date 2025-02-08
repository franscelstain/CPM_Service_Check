<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Occupation extends Model
{
    protected $table        = 'm_occupations';
    protected $primaryKey   = 'occupation_id';
    protected $fillable     = ['occupation_name', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'occupation_name'   => ['required', Rule::unique('m_occupations')->ignore($id, 'occupation_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'description'       => 'max:225'
    	];
    }
}
