<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Edd extends Model
{
    protected $table        = 'u_investors_edd';
    protected $primaryKey   = 'investor_edd_id';
    protected $guarded      = ['investor_edd_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules()
    {
        return [
        	'investor_id'	=> 'required',
        	'edd_date'	  	=> 'required'
        ];
    }
}