<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AssetFreeze extends Model 
{    
    protected $table        = 't_assets_freeze';
    protected $primaryKey   = 'asset_freeze_id';
    protected $guarded      = ['asset_freeze_id','created_at','updated_at','updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'product_id' => 'required',
            'investor_id'=> 'required'
        ];
    }
}