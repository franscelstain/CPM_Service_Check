<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AssetOutstanding extends Model 
{    
    protected $table        = 't_assets_outstanding';
    protected $primaryKey   = 'outstanding_id';
    protected $guarded      = ['outstanding_id','created_at','updated_at','is_active', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'product_id' => 'required',
        ];
    }
}