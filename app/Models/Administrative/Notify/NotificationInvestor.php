<?php

namespace App\Models\Administrative\Notify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class NotificationInvestor extends Model
{

    protected $table        = 'h_notification_investor';
    protected $primaryKey   = 'id';
    protected $guarded      = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            
        ];
    }
}
