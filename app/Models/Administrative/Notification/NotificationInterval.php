<?php

namespace App\Models\Administrative\Notification;

use Illuminate\Database\Eloquent\Model;

class NotificationInterval extends Model
{

    protected $table    = 'm_notifications_interval';
    protected $guarded  = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            
        ];
    }
}
