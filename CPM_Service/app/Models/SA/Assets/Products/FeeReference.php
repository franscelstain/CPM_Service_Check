<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class FeeReference extends Model
{
    protected $table        = 'm_fee_reference';
    protected $primaryKey   = 'fee_reference_id';
    protected $guarded      = ['fee_reference_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'reference_type'    => 'required',
            'reference_value'   => 'required'
        ];
    }
}
