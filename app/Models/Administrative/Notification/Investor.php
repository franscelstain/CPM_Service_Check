<?php

namespace App\Models\Administrative\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Investor extends Model
{
    protected $table    = 'h_notification_investor';
    protected $guarded  = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            
        ];
    }
}