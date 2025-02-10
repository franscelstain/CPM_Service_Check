<?php

namespace App\Models\Administrative\Notification;

use Illuminate\Database\Eloquent\Model;

class InvestorInterval extends Model
{

    protected $table    = 'm_notification_investor_interval';
    protected $guarded  = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            
        ];
    }
}
