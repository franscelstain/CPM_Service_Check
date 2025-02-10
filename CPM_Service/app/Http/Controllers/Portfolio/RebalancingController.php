<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\AppController;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\Financial\Planning\Goal\InvestmentDetail;
use App\Models\Transaction\TransactionHistoryDay;
use Illuminate\Http\Request;
use Auth;

class RebalancingController extends AppController
{
    public function detail($id)
    {
        try
        {
            $inv_id     = Auth::id();
            $balance    = $prj_amt = 0;
            $product    = [];
            $data       = Investment::select('t_goal_investment.*', 'b.profile_name')
                        ->leftJoin('m_risk_profiles as b', function($qry) { return $qry->on('t_goal_investment.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes'); })
                        ->where([['investor_id', $inv_id], ['goal_invest_id', $id], ['t_goal_investment.is_active', 'Yes']])
                        ->first();
            if (!empty($data->goal_invest_id))
            {
                $qry_prd    = TransactionHistoryDay::select('t_trans_histories_days.*', 'b.product_name', 'c.asset_class_name', 'c.asset_class_color', 'd.issuer_logo')
                            ->join('m_products as b', 't_trans_histories_days.product_id', '=', 'b.product_id')
                            ->leftJoin('m_asset_class as c', function($qry) { $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                            ->leftJoin('m_issuer as d', function($qry) { $qry->on('b.issuer_id', '=', 'd.issuer_id')->where('d.is_active', 'Yes'); })
                            ->where([['investor_id', $inv_id], ['portfolio_id', $data->portfolio_id], ['history_date', $this->app_date()], ['t_trans_histories_days.is_active', 'Yes'], ['b.is_active', 'Yes']])
                            ->get();

                $d1         = new \DateTime($data->goal_invest_date);
                $d2         = new \DateTime(date('Y-m-d'));
                $diff       = $d2->diff($d1);
                $month      = 0;

                if ($diff->y > 0)
                    $month = $diff->m > 0 ? ($diff->y*12)+$diff->m : $diff->y*12;
                else
                    $month = $diff->m;

                $prj_amt = ($month == 0 ? $data->first_investment : 0);
//                $prj_amt = $this->growth_goal1($month,$data->first_investment);
                $prj_amt2 = $prj_amt ;
                $net_amount = $total_first_investment = 0;

                foreach ($qry_prd as $prd)
                {
                    $qu    = InvestmentDetail::select('net_amount', 'expected_return_month', 'investment_type')
                        ->where([['goal_invest_id', $data->goal_invest_id], ['product_id', $prd->product_id], ['is_active', 'Yes']])
                        ->first();
                    $net_amount = $qu->net_amount;
                    $total_first_investment += $net_amount;

                    if ($month > 0){
                        $prj_amt2 = $this->growth_goals($data->goal_invest_id, $prd->product_id, $month);
                        $amt_req = $this->growth_goals($data->goal_invest_id, $prd->product_id, $month) - $prd->current_balance;
                        $prj_amt += $amt_req;
                    }
                    else{
                       // $amt_req = $prj_amt - $prd->current_balance;
                        $amt_req =  $net_amount - $prd->current_balance ;
                        $prj_amt -= $prd->current_balance ;
//                        if($amt_req > 0){
//                            $prj_amt += $amt_req;
//                        }
                        $prj_amt2 = $prd->current_balance - $net_amount;
                   }



                    $balance += $prd->current_balance;

                    if ($amt_req > 0)
                    {
                        $product[]  = [
                            'account_no'        => $prd->account_no,
                            'amount_required'   => $amt_req,
                            'asset_class_name'  => $prd->asset_class_name,
                            'asset_class_color' => $prd->asset_class_color,
                            'current_balance'   => floatval($prd->current_balance),
                            'earnings'          => floatval($prd->earnings),
                            'issuer_logo'       => $prd->issuer_logo,
                            'product_id'        => $prd->product_id,
                            'product_name'      => $prd->product_name,
                            'returns'           => floatval($prd->returns),
                            'unit'              => floatval($prd->unit),
                            'month'            => $month,
                            'prj_amt'           => $prj_amt2,
                            'net_amount'        => $net_amount
                        ];
                    }
                }
            }
            return $this->app_response('Rebalancing Detail', ['detail' => $data, 'product' => $product, 'total_amount_required' => $prj_amt, 'total_balance' => $balance,'total_first_investment'=>$total_first_investment]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function growth_goal1($month,$first_investment){
        $prj_amt = ($month == 0 ? $first_investment : 0);
        return $prj_amt;
    }



    private function growth_goals($id, $prd_id, $month)
    {
        $prd    = InvestmentDetail::select('net_amount', 'expected_return_month', 'investment_type')
                ->where([['goal_invest_id', $id], ['product_id', $prd_id], ['is_active', 'Yes']])
                ->first();

        if ($prd->investment_type == 'Lumpsum')
            $prj_amt = $prd->net_amount * pow(1 + $prd->expected_return_month, $month);
        else
            $prj_amt = $prd->expected_return_month > 0 ? (($prd->net_amount * (1 + $prd->expected_return_month)) * (pow(1 + $prd->expected_return_month, $month) - 1)) / $prd->expected_return_month : 0;

        return $prj_amt;
    }
}
