<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CutOffCurrency extends Model
{
    protected $table        = 'm_cut_off_time';
    protected $primaryKey   = 'cut_off_time_id';
    protected $guarded      = ['cut_off_time_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    // protected $guarded      = ['cut_off_time_id'];

    public static function rules($id = null)
    {
        return  
        [
            // 'currency_id' => ['required', Rule::unique('m_cut_off_time')->ignore($id, 'cut_off_time_id')->where(function ($query) {
            //                         return $query->where('is_active', 'Yes');
            //                    })]
        ];
    }
}
