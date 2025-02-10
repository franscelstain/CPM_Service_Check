<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Nationality extends Model
{
    protected $table        = 'm_nationalities';
    protected $primaryKey   = 'nationality_id';
    protected $fillable     = ['nationality_name', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'nationality_name'  => ['required', Rule::unique('m_nationalities')->ignore($id, 'nationality_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })]
    	];
    }
}
