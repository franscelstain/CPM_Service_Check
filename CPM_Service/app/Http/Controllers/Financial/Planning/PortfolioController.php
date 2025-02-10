<?php

namespace App\Http\Controllers\Financial\Planning;

use App\Http\Controllers\AppController;
use App\Models\Financial\Planning\Portfolio\Investment;
use App\Models\Financial\Planning\Portfolio\InvestmentDetail;
use App\Models\Transaction\TransactionHistoryDay;
use Illuminate\Http\Request;
use Auth;
use DB;

class PortfolioController extends AppController
{
    private function balance($inv_id, $prtf_id = '', $prtf_typ = '')
    {
        $balance    = TransactionHistoryDay::selectRaw('history_date, COUNT(trans_history_day_id) as trans, SUM(current_balance) as current_balance, SUM(earnings) as earnings, SUM(investment_amount) as investment_amount')
                    ->join('m_products as b', 't_trans_histories_days.product_id', '=', 'b.product_id')
                    ->where([['investor_id', $inv_id], ['history_date', $this->app_date()], ['t_trans_histories_days.is_active', 'Yes'], ['b.is_active', 'Yes']]);
        
        if (!empty($prtf_id))
            $balance->where('portfolio_id', $prtf_id);
        if (!empty($prtf_typ))
        {
            if ($prtf_typ > 1)
                $balance->whereRaw("LEFT(portfolio_id, 1) = '". $prtf_typ ."'");
            else
                $balance->whereNull('portfolio_id')->orWhereRaw("LEFT(portfolio_id, 1) NOT IN ('2', '3')");
        }
        
        $balance    = $balance->groupBy('history_date')->first();        
        $invest_amt = !empty($balance->investment_amount) ? floatval($balance->investment_amount) : 0;
        $earnings   = !empty($balance->earnings) ? floatval($balance->earnings) : 0;
        
        return (object) [
            'current_balance'   => !empty($balance->current_balance) ? floatval($balance->current_balance) : 0,
            'investment_amount' => $invest_amt,
            'earnings'          => $earnings,
            'returns'           => $invest_amt != 0 ? $earnings / $invest_amt * 100 : 0,
            'row'               => !empty($balance->trans) ? $balance->trans : 0
        ];
    }
    
    private function portfolio($request, $prtf_typ)
    {
        try
        {
            $auth   = $this->auth_user();
            $data   = $price = [];
            $limit  = !empty($request->limit) ? $request->limit : 10;
            $page   = !empty($request->page) ? $request->page : 1;
            $offset = ($page-1)*$limit;
            $inv_id = $auth->usercategory_name != 'Investor' ? $request->investor_id : $this->auth_user()->id;
            $paging = !empty($request->paging) ? false : true;

            $goals  = Investment::select('t_portfolio_investment.*', 'b.goal_name', 'c.reference_code', 'c.reference_color')
                    ->leftJoin('m_goals as b', function($qry) {return $qry->on('t_portfolio_investment.goal_id', '=', 'b.goal_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as c', function($qry) { return $qry->on('t_portfolio_investment.status_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Goals Status'], ['c.is_active', 'Yes']]); })
                    ->where([['t_portfolio_investment.investor_id', $inv_id], ['investment_category', $prtf_typ], ['t_portfolio_investment.is_active', 'Yes']]);

            if (!empty($request->search))
            {
                $goals  = $goals->where(function($qry) use ($request) {
                            $qry->where('t_portfolio_investment.investment_date', 'ilike', '%'. $request->search .'%')
                                ->orWhere('t_portfolio_investment.investment_name', 'ilike', '%'. $request->search .'%')
                                ->orWhere('t_portfolio_investment.portfolio_id', 'ilike', '%'. $request->search .'%');
                        });
            }
            
            if (!empty($request->watchlist_select))
            {
                $goals->addSelect(DB::raw("(SELECT COUNT(key_id) FROM t_watchlist WHERE is_active = 'Yes' AND key_id = t_portfolio_investment.investment_id AND watchlist_type = 'goal' AND user_id = ".$inv_id." AND usercategory_id = ".$auth->usercategory_id.") as key_id" ));
            }

            if (!empty($request->watchlist_filter))
            {
                $goals->selectRaw('1 as key_id')->whereExists(function($qry) use ($auth) {
                    $qry->select('key_id')->from('t_watchlist')->where([['is_active', 'Yes'], ['watchlist_type', 'goal'], ['user_id', $auth->id], ['usercategory_id', $auth->usercategory_id]])->whereColumn('key_id', 't_portfolio_investment.investment_id');
                });                    
            }
            if (!empty($request->goal))
                $goals = $goals->where('b.goal_id', $request->goal);
            if (!empty($request->status))
                $goals = $goals->where('c.trans_reference_id', $request->status);
            if (!empty($request->balance_minimum))
                $goals = $goals->where('t_portfolio_investment.total_amount', '>=', $request->balance_minimum);
            if (!empty($request->balance_maximum))
                $goals = $goals->where('t_portfolio_investment.total_amount', '<=', $request->balance_maximum);

            $total = $goals->count();
            $goals = $paging ? $goals->offset($offset)->limit($limit)->get() : $goals->get();
            
            foreach ($goals as $dt)
            {
                $balance    = $this->balance($dt->investor_id, $dt->portfolio_id);
                $prj_amt    = 0;
                
                if ($balance->current_balance > 0)
                {
                    $d1         = new \DateTime($dt->investment_date);
                    $d2         = new \DateTime(date('Y-m-d'));
                    $diff       = $d2->diff($d1);
                    $month      = 0;
                    $product    = InvestmentDetail::select('net_amount', 'expected_return_month', 'investment_type')
                                ->join('m_products as b', 't_portfolio_investment_detail.product_id', '=', 'b.product_id')
                                ->where([['investment_id', $dt->investment_id], ['t_portfolio_investment_detail.is_active', 'Yes'], ['b.is_active', 'Yes']])
                                ->get();

                    if ($diff->y > 0)
                        $month = $diff->m > 0 ? ($diff->y*12)+$diff->m : $diff->y*12;
                    else
                        $month = $diff->m;

                    $prj_amt = $month == 0 ? $dt->first_investment : 0;

                    foreach ($product as $prd)
                    {                    
                        if ($month > 0)
                        {
                            if ($prd->investment_type == 'Lumpsum')
                                $prj_amt += $prd->net_amount * pow(1 + $prd->expected_return_month, $month);
                            else
                                $prj_amt += $prd->expected_return_month > 0 ? (($prd->net_amount * (1 + $prd->expected_return_month)) * (pow(1 + $prd->expected_return_month, $month) - 1)) / $prd->expected_return_month : 0;
                        }
                    }
                }

                $dt->balance    = floatval($balance->current_balance);
                $dt->earnings   = floatval($balance->earnings);
                $dt->returns    = floatval($balance->returns);
                $dt->growth     = floatval($prj_amt);
                $data[]         = $dt;
            }
            
            $total_data = $page*$limit;
           
            $paginate = [
                'current_page'  => $page,
                'data'          => $data,
                'from'          => $page > 1 ?  1 + (($page-1) * $limit) : 1,
                'per_page'      => $limit,
                'to'            => $total_data >= $total ? $total : $total_data,
                'total'         => $total
            ];

            return $this->app_response('Portfolio', $paginate);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function portfolio_goal(Request $request)
    {
        return $this->portfolio($request, 'goal');
    }
    
    public function portfolio_non_goal(Request $request)
    {        
        $inv_id     = $this->auth_user()->usercategory_name != 'Investor' ? $request->investor_id : $this->auth_user()->id;
        $paginate   = [
            'current_page'  => 1,
            'data'          => [$this->balance($inv_id, '', 1)],
            'from'          => 1,
            'per_page'      => 10,
            'to'            => 1,
            'total'         => 1
        ];
        return $this->app_response('Portfolio', $paginate);
    }
    
    public function portfolio_retirement(Request $request)
    {
        return $this->portfolio($request, 'retirement');
    }
    
    public function total(Request $request)
    {
        try
        {
            $prtf   = [];
            $curr   = $invst = $earn = 0;
            foreach (['goal' => 2, 'non_goal' => 1, 'retirement' => 3] as $pk => $pv)
            {
                $blc        = $this->balance($request->investor_id, '', $pv);
                $prtf[$pk]  = $blc;
                $curr      += $blc->current_balance;
                $invst     += $blc->investment_amount;
                $earn      += $blc->earnings;
            }
            
            $prtf['current_balance']    = $curr;
            $prtf['investment_amount']  = $invst;
            $prtf['earnings']           = $earn;
            $prtf['returns']            = $invst > 0 ? $earn / $invst * 100 : 0;
            
            return $this->app_response('Portfolio - Total', $prtf);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}