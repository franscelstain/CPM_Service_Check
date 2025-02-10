<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ThirdPartyCategory extends Model
{
    protected $table        = 'm_thirdparty_categories';
    protected $primaryKey   = 'thirdpartycategory_id';
    protected $guarded      = ['thirdpartycategory_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'thirdpartycategory_name'   => ['required', Rule::unique('m_thirdparty_categories')->ignore($id, 'thirdpartycategory_id')->where(function ($query) { 
                                                return $query->where('is_active', 'Yes');
                                           })]
        ];
    }
}
