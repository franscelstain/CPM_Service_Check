<?php

namespace App\Models\Administrative\Notify;
use Illuminate\Database\Eloquent\Model;

class NotificationUsers extends Model
{

    protected $table = 'h_notification_users';
    //Primary key
    public $primaryKey = 'id';
    //Timestamp
    public $timestamp = true;
    protected $fillable = [
        'notif_title', 'notif_desc', 'notif_status','notif_href','notif_status_batch','users_on',
        ];
    const CREATED_AT = 'created_at';

   
}
