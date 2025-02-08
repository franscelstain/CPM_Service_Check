<?php

namespace App\Models\Administrative\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class HttpCode extends Model
{
    protected $table        = 'c_api_http_code';
    protected $primaryKey   = 'http_id';
    protected $fillable     = ['http_code', 'title', 'description', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
        return [
            'http_code'     => ['required', Rule::unique('c_api_http_code')->ignore($id, 'http_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'title'         => 'required',
            'description'   => 'required'
    	];
    }
}
