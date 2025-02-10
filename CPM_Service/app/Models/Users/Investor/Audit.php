<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Audit extends Model
{
    protected $table        = 'u_investors_audit';
    protected $primaryKey   = 'id';
    protected $guarded      = ['id', 'created_at', 'updated_at'];
    
    public static function rules($id = null)
    {
        return [
            
        ];
    }
}