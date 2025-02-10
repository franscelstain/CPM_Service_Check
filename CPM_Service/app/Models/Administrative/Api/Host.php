<?php

namespace App\Models\Administrative\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Host extends Model
{
    protected $table        = 'c_api';
    protected $primaryKey   = 'api_id';
    protected $guarded      = ['api_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'api_name'      => ['required', Rule::unique('c_api')->ignore($id, 'api_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'slug'          => ['required', Rule::unique('c_api')->ignore($id, 'api_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'data_key'      => 'required',
            'content_type'  => 'required',
            'get_token'     => 'required',
            'user_label'    => 'required_if:authorization,auth',
            'username'      => 'required_if:authorization,auth',
            'pass_label'    => 'required_if:authorization,auth',
            'password'      => 'required_if:authorization,auth'
    	];
    }
}
