<?php

namespace App\Models\SA\Reference\Bank;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Bank extends Model
{
    protected $table        = 'm_bank';
    protected $primaryKey   = 'bank_id';
    protected $fillable     = ['bank_name', 'bank_code', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
        return [
            // 'bank_name' => ['required', Rule::unique('m_bank')->ignore($id, 'bank_id')->where(function ($query) {
            //                     return $query->where('is_active', 'Yes');
            //                })],
            'bank_code' => ['required', Rule::unique('m_bank')->ignore($id, 'bank_id')->where(function ($query) { 
                                return $query->where('is_active', 'Yes');
                           })]
        ];
    }
}
