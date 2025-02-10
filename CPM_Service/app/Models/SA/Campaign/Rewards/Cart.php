<?php

namespace App\Models\SA\Campaign\Rewards;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Cart extends Model
{
    protected $table        = 'm_campaign_rewards_carts';
    protected $primaryKey   = 'cart_id';
    protected $guarded      = ['cart_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
    	return [
            'cart_action_id'    => 'required', 
            'cart_name'         => 'required', 
            'cart_type'         => 'required', 
            'coupon'            => 'required', 
            'coupon_code'       => 'required_if:coupon,Specific Coupon', 
            'cart_date_from'    => 'required', 
            'cart_date_to'      => 'required', 
            'amount'            => 'required|numeric',
            'status'            => 'required',
            'customer_group'    => 'required|array',
            'extra_key'         => 'required|array',
            'extra_value'       => 'required|array'
    	];
    }
}
