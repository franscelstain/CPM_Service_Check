<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Address extends Model
{
    protected $table        = 'u_investors_addresses';
    protected $primaryKey   = 'investor_address_id';
    protected $guarded      = ['investor_address_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        //
    }
}