<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Gender extends Model
{
    protected $table        = 'm_gender';
    protected $primaryKey   = 'gender_id';
    protected $fillable     = ['gender_name', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'gender_name'   => ['required', Rule::unique('m_gender')->ignore($id, 'gender_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'description'   => 'max:225'
    	];
    }
}
