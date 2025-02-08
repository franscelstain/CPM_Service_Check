<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UserSalesDetail extends Model
{
    protected $table        = 'u_users_sales_detail';
    protected $primaryKey   = 'user_sales_detail_id';
    protected $guarded      = ['user_sales_detail_id'];
    
    public static function rules()
    {
        return[];
    }
}