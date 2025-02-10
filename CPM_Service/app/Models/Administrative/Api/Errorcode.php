<?php

namespace App\Models\Administrative\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Errorcode extends Model
{
    protected $table        = 'c_api_error_code';
    protected $primaryKey   = 'error_id';
    protected $fillable     = ['http_id', 'error_code', 'message_en', 'message_id', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
        return [
            'error_code'    => ['required', Rule::unique('c_api_error_code')->ignore($id, 'error_id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'http_id'       => 'required',
            'message_en'    => 'required',
            'message_id'    => 'required'
    	];
    }
}
