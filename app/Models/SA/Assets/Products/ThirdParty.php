<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ThirdParty extends Model
{
    protected $table        = 'm_thirdparty';
    protected $primaryKey   = 'thirdparty_id';
    protected $guarded      = ['thirdparty_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'thirdparty_name'   => ['required', Rule::unique('m_thirdparty')->ignore($id, 'thirdparty_id')->where(function ($query) { 
                                        return $query->where('is_active', 'Yes');
                                   })],
            'description'       => 'max:225'
        ];
    }
}
