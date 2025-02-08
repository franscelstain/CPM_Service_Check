<?php

namespace App\Models\Administrative\Notify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CategorySetup extends Model
{

    protected $table        = 'm_notification_categories';
    protected $primaryKey   = 'id';
    protected $guarded      = ['id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            'category_name' => ['required', Rule::unique('m_notification_categories')->ignore($id, 'id')->where(function ($query) {
                                    return $query->where('is_active', 'Yes');
                               })],
            'assign_to'     => 'required'
        ];
    }
}
