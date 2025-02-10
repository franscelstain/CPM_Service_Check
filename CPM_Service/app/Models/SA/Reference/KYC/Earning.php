<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Earning extends Model
{
    protected $table        = 'm_earnings';
    protected $primaryKey   = 'earning_id';
    protected $fillable     = ['earning_name', 'ext_code', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'earning_name'  => ['required', Rule::unique('m_earnings')->ignore($id, 'earning_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })]
    	];
    }
}
