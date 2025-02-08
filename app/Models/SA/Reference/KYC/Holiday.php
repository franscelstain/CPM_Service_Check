<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Holiday extends Model
{
    protected $table        = 'm_holiday';
    protected $primaryKey   = 'holiday_id';
    protected $fillable     = ['currency_id', 'effective_date', 'ext_code', 'description', 'is_data', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'effective_date'    => ['required', Rule::unique('m_holiday')->ignore($id, 'holiday_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'currency_id'       => 'required',
            'description'       => 'required'
    	];
    }
}
