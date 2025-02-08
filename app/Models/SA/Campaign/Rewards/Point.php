<?php

namespace App\Models\SA\Campaign\Rewards;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Point extends Model
{
    protected $table        = 'm_campaign_rewards_points';
    protected $primaryKey   = 'point_id';
    protected $fillable     = ['point_action_id', 'expired_point_id', 'point_name', 'effective_date', 'point_date_from', 'point_date_to', 'point', 'status', 'created_by', 'created_host'];

    public static function rules($id = null)
    {
    	return [
            'point_action_id'   => 'required',
            'expired_point_id'  => 'required',
            'point_name'        => 'required',
            'effective_date'    => 'required',
            'point'             => 'required',
            'status'            => 'required'
    	];
    }
}
