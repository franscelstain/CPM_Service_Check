<?php

namespace App\Models\Administrative\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Service extends Model
{
    protected $table        = 'c_api_services';
    protected $primaryKey   = 'service_id';
    protected $fillable     = ['api_id', 'service_name', 'service_key', 'service_path', 'service_method', 'description', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
        return [
            'service_name'      => ['required', Rule::unique('c_api_services')->ignore($id, 'service_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'service_key'       => 'required',
            'api_id'            => 'required',
            'service_method'    => 'required'
    	];
    }
}
