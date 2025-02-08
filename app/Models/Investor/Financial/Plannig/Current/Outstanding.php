<?php

namespace App\Models\Investor\Financial\Plannig\Current;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Outstanding extends Model 
{    
    protected $table        = 't_assets_outstanding';
    protected $primaryKey   = 'outstanding_id';
    protected $guarded      = ['outstanding_id','created_at','updated_at','is_active'];
    
    public static function rules()
    {
        return [
            'product_id' => 'required',
        ];
    }
}