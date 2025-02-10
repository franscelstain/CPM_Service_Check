<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Education extends Model
{
    protected $table        = 'm_educations';
    protected $primaryKey   = 'education_id';
    protected $fillable     = ['education_name', 'ext_code', 'description', 'is_data', 'education_shortname', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'education_name'    => ['required', Rule::unique('m_educations')->ignore($id, 'education_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })]
    	];
    }
}
