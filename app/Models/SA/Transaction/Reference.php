<?php

namespace App\Models\SA\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Reference extends Model
{
    protected $table        = 'm_trans_reference';
    protected $primaryKey   = 'trans_reference_id';
    protected $guarded      = ['trans_reference_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    protected $casts        = ['reference_ext' => 'array'];

    public static function rules($id = null)
    {
        return [
            'reference_code'        => ['required', Rule::unique('m_trans_reference')->ignore($id, 'trans_reference_id')->where(function ($query) {  return $query->where('is_active', 'Yes');
            })],
            'reference_name'       => 'required'
        ];
    }
}
