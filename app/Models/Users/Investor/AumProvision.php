<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AumProvision extends Model
{
    protected $table        = 'u_investors_aum_provision';
    protected $primaryKey   = 'investors_aum_id';
    protected $guarded      = ['investors_aum_id', 'created_at', 'updated_at'];
    
    public static function rules($id = null)
    {
        return [
            
        ];
    }
}