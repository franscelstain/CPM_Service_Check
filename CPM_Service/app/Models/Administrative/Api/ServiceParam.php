<?php

namespace App\Models\Administrative\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ServiceParam extends Model
{
    protected $table        = 'c_api_services_param';
    protected $primaryKey   = 'param_id';
    protected $fillable     = ['service_id', 'param_key', 'param_value', 'param_type', 'sequence_to', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
        //
    }
}
