<?php

namespace App\Models\Administrative\Notify;

use Illuminate\Database\Eloquent\Model;

class InvestorIntervalSetup extends Model
{

    protected $table        = 'm_notification_investor_interval';
    protected $primaryKey   = 'id';
    protected $guarded      = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            
        ];
    }
}
