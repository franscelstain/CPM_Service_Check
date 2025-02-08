<?php

namespace App\Http\Controllers\SA\Campaign\Rewards;

use App\Http\Controllers\AppController;
use App\Models\SA\Campaign\Rewards\Cart;
use App\Models\SA\Campaign\Rewards\RewardExtra;
use Illuminate\Http\Request;

class CartController extends AppController
{
    public $table = 'SA\Campaign\Rewards\Cart';

    public function index(Request $request)
    {
        try
        {
            $data = Cart::select('m_campaign_rewards_carts.*', 'b.campaign_ref_name as Action')->join('m_campaign_references as b', 'm_campaign_rewards_carts.cart_action_id', '=', 'b.campaign_ref_id')->where([['m_campaign_rewards_carts.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
            return $this->app_response('Cart List', ['key' => 'cart_id', 'list' => $data]);
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
            $extra  = $cg = [];
            if (!empty($request->input('id')))
            {
                $data   = RewardExtra::where([['reward_id', $request->input('id')], ['extra_type', 'cart'], ['is_active', 'Yes']])->get();
                foreach ($data as $dt)
                {
                    if ($dt->extra_key == 'customer-group')
                        $cg[] = $dt->extra_value;
                    else
                        $extra[] = ['extra_key' => $dt->extra_key, 'extra_value' => $dt->extra_value];
                }
            }
            if (empty($extra))
            {
                $extra[] = ['extra_key' => '', 'extra_value' => ''];
            }
            return $this->app_response('Reward Detail', ['customer_group' => $cg, 'extra' => $extra]);
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
            RewardExtra::where([['reward_id', $id], ['extra_type', 'cart']])->update(['is_active' => 'No']);
            foreach ($request->input('customer_group') as $cg)
            {
                $extra = RewardExtra::where([['reward_id', $id], ['extra_key', 'customer-group'], ['extra_type', 'cart'], ['extra_value', $cg]])->first();   
                $data = [
                    'reward_id'     => $id, 
                    'extra_key'     => 'customer-group', 
                    'extra_value'   => $cg, 
                    'extra_type'    => 'cart',
                    'is_active'     => 'Yes',
                    'created_by'    => $this->auth_user()->id,
                    'created_host'  => $request->input('ip')
                ];
                $save = empty($extra->extra_id) ? RewardExtra::create($data) : RewardExtra::where('extra_id', $extra->extra_id)->update($data);
                $success++;
            }

            $extra_key  = $request->input('extra_key');
            $extra_val  = $request->input('extra_value');
            $extra_val2 = $request->input('extra_value2');
            for ($i = 0; $i < count($extra_key); $i++)
            {
                $extra = RewardExtra::where([['reward_id', $id], ['extra_key', $extra_key[$i]], ['extra_type', 'cart']])->first();   
                $data = [
                    'reward_id'     => $id, 
                    'extra_key'     => $extra_key[$i], 
                    'extra_value'   => $extra_val[$i],
                    'extra_value2'  => $extra_val2[$i],
                    'extra_type'    => 'cart',
                    'is_active'     => 'Yes',
                    'created_by'    => $this->auth_user()->id,
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