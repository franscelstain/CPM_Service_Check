<?php

namespace App\Models\SA\Campaign\Rewards;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class RewardExtra extends Model
{
    protected $table        = 'm_campaign_rewards_extra';
    protected $primaryKey   = 'extra_id';
    protected $fillable     = ['reward_id', 'extra_key', 'extra_value', 'extra_value2', 'extra_type', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
    		//
    	];
    }
}
