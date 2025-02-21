<?php

namespace App\Models\SA\Master\FinancialCheckUp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Ratio extends Model
{
    protected $table        = 'm_financials_ratio';
    protected $primaryKey   = 'ratio_id';
    protected $fillable     = ['ratio_name', 'effective_date', 'ratio_type', 'ratio_method', 'published', 'perfect_value',
                               'perfect_operator', 'bad_value', 'bad_operator', 'warning_value', 'warning_value2', 'warning_operator',
                               'sequence_to', 'description', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
        return [
            'ratio_name'        => ['required', Rule::unique('m_financials_ratio')->ignore($id, 'ratio_id')->where(function ($query) { return $query->where('is_active', 'Yes'); })],
            'ratio_method'      => ['required', Rule::unique('m_financials_ratio')->ignore($id, 'ratio_id')->where(function ($query){ return $query->where('is_active', 'Yes'); })],
            'effective_date'    => 'required|date|date_format:Y-m-d',
            'sequence_to'       => 'required|numeric|min:1',
            'ratio_type'        => 'required', 
            'perfect_value'     => 'required|numeric',
            'perfect_operator'  => 'required', 
            'bad_value'         => 'required|numeric', 
            'bad_operator'      => 'required', 
            'warning_value'     => 'required|numeric',
            'warning_operator'  => 'required',
            'description'       => 'max:160'
        ];
    }
}
