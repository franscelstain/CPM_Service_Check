<?php

namespace App\Http\Controllers\SA\Campaign\Rewards;

use App\Http\Controllers\AppController;
use App\Models\SA\Campaign\Rewards\Point;
use App\Models\SA\Campaign\Rewards\RewardExtra;
use Illuminate\Http\Request;

class PointController extends AppController
{
    public $table = 'SA\Campaign\Rewards\Point';

    public function index(Request $request)
    {
        try
        {
            $data = Point::select('m_campaign_rewards_points.*', 'b.campaign_ref_name as action')->join('m_campaign_references as b', 'm_campaign_rewards_points.point_action_id', '=', 'b.campaign_ref_id')->where([['m_campaign_rewards_points.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
            return $this->app_response('Point List', ['key' => 'point_id', 'list' => $data]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function detail_reward(Request $request)
    {
        try
        {
            $cg = [];
            if (!empty($request->input('id')))
            {
                $data   = RewardExtra::where([['reward_id', $request->input('id')], ['extra_type', 'point'], ['extra_key', 'customer-group'], ['is_active', 'Yes']])->get();
                foreach ($data as $dt)
                {
                    $cg[] = $dt->extra_value;
                }
            }
            return $this->app_response('Reward Detail', $cg);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        try
        {
            $success    = 1;
            $status     = !empty($request->input('status')) ? $request->input('status') : 'Inactive';
            $request->request->add(['status' => $status]);
            
            $id = $this->db_save($request, $id, ['res' => 'id']);
            RewardExtra::where([['reward_id', $id], ['extra_type', 'point']])->update(['is_active' => 'No']);
            foreach ($request->input('customer_group') as $cg)
            {
                $extra = RewardExtra::where([['reward_id', $id], ['extra_key', 'customer-group'], ['extra_type', 'point'], ['extra_value', $cg]])->first();   
                $data = [
                    'reward_id'     => $id, 
                    'extra_key'     => 'customer-group', 
                    'extra_value'   => $cg, 
                    'extra_type'    => 'point',
                    'is_active'     => 'Yes',
                    'created_by'    => $this->cpm_auth()->id,
                    'created_host'  => $request->input('ip')
                ];
                $save = empty($extra->extra_id) ? RewardExtra::create($data) : RewardExtra::where('extra_id', $extra->extra_id)->update($data);
                $success++;
            }
            return $this->app_partials($success, 0, ['id' => $id]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}