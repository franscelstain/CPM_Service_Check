<?php

namespace App\Models\Administrative\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ServiceIndex extends Model
{
    protected $table        = 'c_api_services_index';
    protected $primaryKey   = 'index_id';
    protected $fillable     = ['service_id', 'index_name', 'sequence_to', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
        //
    }
}
