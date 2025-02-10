<?php

namespace App\Models\Administrative\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Notification extends Model
{
    protected $table      =  'm_notifications';
    protected $primaryKey = 'id';
    protected $guarded    = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules()
    {
        return [
            'title'             => 'required',
            'text_message'      => 'required',
            'notif_code'        => 'required',
            'reminder'          => 'required|array',
            'reminder.*'        => 'in:H,H-,H+',
            'email_content_id'  => 'nullable|integer',
            'count_reminder'    => 'nullable|array',
            'count_reminder'    => 'nullable|array',
            'count_reminder.*'  => 'nullable|numeric|min:1',
            'assign_to'         => 'nullable|array',  
            'assign_to.*'       => 'nullable|regex:/^\d+$/',
            'continuous'        => 'nullable|array',
            'continuous.*'      => 'boolean',
        ];
    }
}
