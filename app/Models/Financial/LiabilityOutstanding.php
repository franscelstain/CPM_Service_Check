<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class LiabilityOutstanding extends Model 
{    
    protected $table        = 't_liabilities_outstanding';
    protected $primaryKey   = 'liabilities_outstanding_id';
    protected $guarded      = ['liabilities_outstanding_id','created_at','updated_at','is_active', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [

        ];
    }
}