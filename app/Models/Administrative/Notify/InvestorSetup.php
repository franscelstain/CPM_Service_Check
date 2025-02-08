<?php

namespace App\Models\Administrative\Notify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestorSetup extends Model
{

    protected $table        = 'm_notification_investor';
    protected $primaryKey   = 'id';
    protected $guarded      = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'title'             => 'required',
            'text_message'      => 'required',
            'category_id'       => 'required',
            'reminder'          => 'required|array',
            'count_reminder'    => 'array',
            'count_reminder.*'  => 'required_unless:reminder.*,H|numeric|min:1'
        ];
    }
}
