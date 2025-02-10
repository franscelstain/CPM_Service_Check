<?php

namespace App\Models\Investor\Notify;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table 	= 'h_notification_investor';
    protected $guarded 	= ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
}
