<?php

namespace App\Http\Controllers\Sales\Financial\Construction;

use App\Http\Controllers\AppController;
use App\Models\Investor\Financial\Planning\Goal\Investment;
use App\Models\Investor\Financial\Planning\Goal\InvestmentDetail;
use App\Models\Investor\Transaction\TransactionHistories;
use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Reference\Goal;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\Users\Investor\Investor;
use Illuminate\Http\Request;

class GoalsSetupController extends AppController
{
    public $table = 'Investor\Financial\Planning\Goal\Investment';
    
    public function index(Request $request)
    {
        try
        {
            $data = Investor::select('u_investors.*', 'b.profile_name')
                    ->join('m_risk_profiles as b', 'u_investors.profile_id', '=', 'b.profile_id')
                    ->where([['u_investors.sales_id', $this->auth_user()->id ], ['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->whereIn('u_investors.investor_id', function($qry) { 
                        $qry->select('c.investor_id')->from('t_goal_investment as c')->whereRaw('c.investor_id = u_investors.investor_id');
                    })
                    ->get();
            return $this->app_response('Goals', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }   
    }
    
    public function detail($investor_id, $id)
    {
        try
        {
            $assets     = $detail = $invst = [];
            $prj_amt    = 0;
            $ttl_amount = 0;
            $inv        = Investor::where([['investor_id', $investor_id], ['sales_id', $this->auth_user()->id], ['is_active', 'Yes']])->first();
            $inv_nm     = !empty($inv->investor_id) ? $inv->fullname : '';
            if (!empty($inv->investor_id))
            {
                $invst  = Investment::select('t_goal_investment.*', 'b.profile_name', 'c.goal_name')
                        ->leftJoin('m_risk_profiles as b', function($qry) { return $qry->on('t_goal_investment.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_goals as c', function($qry) {return $qry->on('t_goal_investment.goal_id', '=', 'c.goal_id')->where('c.is_active', 'Yes'); })
                        ->where([['investor_id', $investor_id], ['goal_invest_id', $id], ['t_goal_investment.is_active', 'Yes']])
                        ->first();

                if (!empty($invst->goal_invest_id))
                {
                    $product = InvestmentDetail::selectRaw('target_allocation, c.asset_class_id, c.asset_class_name, c.asset_class_color, d.symbol')
                            ->join('m_products as b', function($qry) {return $qry->on('t_goal_investment_detail.product_id', '=', 'b.product_id')->where('b.is_active', 'Yes'); })
                            ->leftJoin('m_asset_class as c', function($qry) {return $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                            ->leftJoin('m_currency as d', function($qry) {return $qry->on('b.currency_id', '=', 'd.currency_id')->where('d.is_active', 'Yes'); })
                            ->where([['goal_invest_id', $id], ['t_goal_investment_detail.is_active', 'Yes']])
                            ->get();

                    $d1         = new \DateTime($invst->goal_invest_date);
                    $d2         = new \DateTime(date('Y-m-d'));
                    $diff       = $d2->diff($d1);
                    $month      = 0;

                    if ($diff->y > 0)
                        $month = $diff->m > 0 ? ($diff->y*12)+$diff->m : $diff->y*12;
                    else
                        $month = $diff->m;

                    $prj_amt = $month == 0 ? $invst->first_investment : 0;

                    $trans_total = 0;
                    $trans_total = TransactionHistories::select('net_amount')
                                    ->join('m_trans_reference as b', 't_trans_histories.trans_reference_id', '=', 'b.trans_reference_id')
                                    ->join('m_trans_reference as c', 't_trans_histories.type_reference_id', '=', 'c.trans_reference_id')
                                    ->where([['t_trans_histories.investor_id', $investor_id], ['t_trans_histories.is_active', 'Yes'], ['t_trans_histories.portfolio_id', $invst->portfolio_id], ['b.reference_type', 'Transaction Status'], ['b.is_active', 'Yes'], ['b.reference_code', 'Done'], ['c.reference_type', 'Transaction Type'], ['c.is_active', 'Yes'], ['c.reference_code', 'SUB']])
                                    ->whereNotNull('account_no')
                                    ->sum('net_amount');

                    $account = TransactionHistories::select()
                            ->join('m_products as b', function($qry) {return $qry->on('t_trans_histories.product_id', '=', 'b.product_id')->where('b.is_active', 'Yes'); })
                            ->leftJoin('m_asset_class as c', function($qry) {return $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                            ->leftJoin('m_currency as d', function($qry) {return $qry->on('b.currency_id', '=', 'd.currency_id')->where('d.is_active', 'Yes'); })
                            ->leftJoin('m_issuer as e', function($qry) {return $qry->on('e.issuer_id', '=', 'b.issuer_id')->where('e.is_active', 'Yes'); })
                            ->join('m_trans_reference as f', 't_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')
                            ->join('m_trans_reference as g', 't_trans_histories.type_reference_id', '=', 'g.trans_reference_id')
                            ->where([['t_trans_histories.investor_id', $investor_id], ['t_trans_histories.is_active', 'Yes'], ['f.reference_type', 'Transaction Status'], ['g.reference_type', 'Transaction Type']
                                ])
                            ->wherein('g.reference_code', ['SUB','TOPUP', 'SWTIN'])
                            ->whereNotNull('account_no')
                            ->get();

                    $invst_amonut = TransactionHistories::select()
                                ->join('m_products as b', function($qry) {return $qry->on('t_trans_histories.product_id', '=', 'b.product_id')->where('b.is_active', 'Yes'); })
                                ->leftJoin('m_asset_class as c', function($qry) {return $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                                ->leftJoin('m_currency as d', function($qry) {return $qry->on('b.currency_id', '=', 'd.currency_id')->where('d.is_active', 'Yes'); })
                                ->leftJoin('m_issuer as e', function($qry) {return $qry->on('e.issuer_id', '=', 'b.issuer_id')->where('e.is_active', 'Yes'); })
                                ->join('m_trans_reference as f', 't_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')
                                 ->join('m_trans_reference as g', 't_trans_histories.type_reference_id', '=', 'g.trans_reference_id')
                                ->where([['t_trans_histories.investor_id', $investor_id], ['t_trans_histories.is_active', 'Yes'], ['f.reference_type', 'Transaction Status'], ['g.reference_type', 'Transaction Type']
                                    ])
                                ->wherein('g.reference_code', ['SUB','TOPUP', 'SWTIN'])
                                ->whereNotNull('account_no')
                                ->sum('amount');
                    $total_erning = $trans_total - $invst_amonut;

                    $total_return = $total_erning / $invst_amonut;

                    //looping
                    foreach ($account as $acc ) 
                    {
                        $price = Price::select('price_value')
                                ->where('product_id', $acc['product_id'])
                                ->orderBy('price_date', 'Desc')
                                ->first();
                        if($acc['reference_code'] == 'RED' || $acc['reference_code'] == 'SWTOT') 
                           $ttl_amount += 0;
                        else
                            $ttl_amount1 = TransactionHistories::select()
                                                ->sum('approve_unit');

                            $current_balance = $ttl_amount1 * $price->price_value;

                            $invest_amonut = TransactionHistories::select()
                                        ->join('m_products as b', function($qry) {return $qry->on('t_trans_histories.product_id', '=', 'b.product_id')->where('b.is_active', 'Yes'); })
                                        ->leftJoin('m_asset_class as c', function($qry) {return $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                                        ->leftJoin('m_currency as d', function($qry) {return $qry->on('b.currency_id', '=', 'd.currency_id')->where('d.is_active', 'Yes'); })
                                        ->leftJoin('m_issuer as e', function($qry) {return $qry->on('e.issuer_id', '=', 'b.issuer_id')->where('e.is_active', 'Yes'); })
                                        ->join('m_trans_reference as f', 't_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')
                                         ->join('m_trans_reference as g', 't_trans_histories.type_reference_id', '=', 'g.trans_reference_id')
                                        ->where([['t_trans_histories.investor_id', $investor_id], ['t_trans_histories.is_active', 'Yes'], ['f.reference_type', 'Transaction Status'], ['g.reference_type', 'Transaction Type']
                                            ])
                                        ->wherein('g.reference_code', ['SUB','TOPUP', 'SWTIN'])
                                        ->whereNotNull('account_no')
                                        ->sum('amount');
                            $erning = $current_balance - $invest_amonut;
                            $return_persent = $erning/$invest_amonut;
                            $ttl_amount = array_merge([
                                'asset_class_name'  => $acc->asset_class_name,
                                'asset_class_color' => $acc->asset_class_color,
                                'symbol'            => $acc->symbol,
                                'account_no'        => $acc->account_no,
                                'portfolio_id'      => $acc->portfolio_id,
                                'issuer_logo'       => $acc->issuer_logo,
                                'product_name'      => $acc->product_name,
                                'current_balance'   => $current_balance,
                                'invest_amonut'     => $invest_amonut,
                                'erning'            => $erning,
                                'return_persent'    => $return_persent
                            ]);
                    }

                    foreach ($product as $prd)
                    {
                        if ($month > 0)
                        {
                            if ($prd['investment_type'] == 'Lumpsum')
                                $prj_amt += $prd->amount * pow(1 + $prd->expected_return_month, $month);
                            else
                                $prj_amt += (($prd->amount * (1 + $prd->expected_return_month)) * (pow(1 + $prd->expected_return_month, $month) - 1)) / $prd->expected_return_month;
                        }

                        if (in_array($prd->asset_class_id, array_keys($assets)))
                        {
                            $asset[$prd->asset_class_id]['weight'] += $prd->target_allocation;
                        }
                        else
                        {
                            $assets[$prd->asset_class_id] = [
                                'asset_class_name'  => $prd->asset_class_name,
                                'asset_class_color' => $prd->asset_class_color,
                                'symbol'            => $prd->symbol,
                                'weight'            => $prd->target_allocation
                            ];
                        }
                    }
                }
            }
            return $this->app_response('Goals Detail', ['data' => $invst, 'assets' => array_values($assets), 'growth' => $prj_amt, 'total' => $trans_total, 'invst_amonut' => $invst_amonut, 'total_erning' => $total_erning, 'total_return' =>$total_return, 'account' => array($ttl_amount), 'investor_name' => $inv_nm]);        
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function investor($id)
    {
        try
        {
            $data   = [];
            $inv    = Investor::where([['investor_id', $id], ['sales_id', $this->auth_user()->id], ['is_active', 'Yes']])->first();
            $inv_nm = !empty($inv->investor_id) ? $inv->fullname : '';
            if (!empty($inv->investor_id))
            {
                $data   = Investment::select('t_goal_investment.*', 'b.goal_name', 'c.reference_code as status_code', 'c.reference_color as color')
                        ->leftJoin('m_goals as b', function($qry) { return $qry->on('t_goal_investment.goal_id', '=', 'b.goal_id')->where([['b.is_active', 'Yes']]); })
                        ->leftJoin('m_trans_reference as c', function($qry) { return $qry->on('t_goal_investment.status_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Goals Status'], ['c.is_active', 'Yes']]); })
                        ->where([['t_goal_investment.is_active', 'Yes'], ['t_goal_investment.investor_id', $id]])
                        ->get();
            }
            return $this->app_response('Detail Goal', ['investor_name' => $inv_nm, 'goals' => $data]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }
    
    public function goals(Request $request)
    {
        try
        {
            $data = [];
            if (!empty($request->investor_id))
            {
                $inv = Investor::where([['investor_id', $request->investor_id], ['sales_id', $this->auth_user()->id], ['is_active', 'Yes']])->first();
                if (!empty($inv))
                {
                    $pfl    = Profile::where([['profile_id', $inv->profile_id], ['is_active', 'Yes']])->first();
                    $pflnm  = !empty($pfl) ? $pfl->profile_name : '';
                    $goal   = Goal::where('is_active', 'Yes')->orderBy('goal_name')->get();
                    $risk   = Profile::where('is_active', 'Yes')->orderBy('sequence_to')->get();
                    $data   = ['goal' => $goal, 'riskprof' => $risk, 'pfl_name' => $pflnm, 'investor' => $inv];
                }
            }
            return $this->app_response('Investor', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save_checkout(Request $request, $id = null)
    {
        try
        {
            $inv = Investor::where([['investor_id', $request->investor_id], ['is_active', 'Yes']])->first();
            if (!empty($inv))
            {
                $qry_trs    = TransactionHistories::where([['investor_id', $inv->investor_id], ['transaction_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('reference_no', 'desc')->first();
                $rn         = !empty($qry_trs->reference_no) ? substr($qry_trs->reference_no, -3) + 1 : 1;
                $qry_inv    = Investment::where([['investor_id', $inv->investor_id], ['goal_invest_date', $this->app_date()], ['is_active', 'Yes']])->orderBy('portfolio_id', 'desc')->first();
                $inv_prtf   = !empty($qry_inv->portfolio_id) ? substr($qry_inv->portfolio_id, -3) + 1 : 1;
                $sn         = substr($inv->cif, -5) . date('ymd');
                $portf_id   = 2 . $sn . str_pad($inv_prtf, 3, '0', STR_PAD_LEFT);
                $ref_code   = $request->save_type == 'checkout' ? 'IP' : 'OD';
                $status_ref = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Goals Status'], ['reference_code', $ref_code]]], 'SA\Transaction\Reference')->original['data'];

                if ($request->save_type == 'checkout')
                {
                    $trans_ref  = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Status'], ['reference_code', 'Submited']]], 'SA\Transaction\Reference')->original['data'];
                    $type_ref   = $this->db_row('trans_reference_id', ['where' => [['reference_type', 'Transaction Type'], ['reference_code', 'SUB']]], 'SA\Transaction\Reference')->original['data'];
                }

                $request->request->add([
                    'investor_id'       => $inv->investor_id, 
                    'goal_invest_date'  => $this->app_date(), 
                    'portfolio_id'      => $portf_id,
                    'status_id'         => $status_ref
                ]);

                $id                     = $this->db_save($request, $id, ['res' => 'id']);
                $manager                = $this->db_manager($request);
                $product_id             = $request->product_id;
                $fee_product_id         = $request->fee_product_id;
                $investor_account_id    = $request->investor_account_id;
                $transaction_date       = $request->transaction_date;
                $debt_date              = $request->debt_date;
                $amount                 = $request->amount;
                $net_amount             = $request->net_amount;
                $fee_amount             = $request->fee_amount;
                $tax_amount             = $request->tax_amount;
                $exp_rtn_year           = $request->expected_return_year;
                $exp_rtn_month          = $request->expected_return_month;
                $target_allocation      = $request->target_allocation;
                $sharpe_ratio           = $request->sharpe_ratio;
                $treynor_ratio          = $request->volatility; 
                $investment_type        = $request->investment_type;
                $percentage             = $request->percentage;

                for ($i = 0; $i < count($product_id); $i++)
                {
                    $data = [
                        'product_id'            => $product_id[$i],
                        'fee_product_id'        => !empty($fee_product_id[$i]) ? $fee_product_id[$i] : null,
                        'investor_account_id'   => !empty($investor_account_id[$i]) ? $investor_account_id[$i] : null,
                        'debt_date'             => !empty($debt_date[$i]) ? $debt_date[$i] : null,
                        'amount'                => !empty($amount[$i]) ? $amount[$i] : null,
                        'net_amount'            => $net_amount[$i],
                        'fee_amount'            => !empty($fee_amount[$i]) ? $fee_amount[$i] : null,
                        'tax_amount'            => !empty($tax_amount[$i]) ? $tax_amount[$i] : null,
                        'investment_type'       => $investment_type[$i],
                        'created_by'            => $manager->user,
                        'created_host'          => $manager->ip
                    ];

                    $invest_detail = array_merge([
                        'goal_invest_id'        => $id,
                        'expected_return_year'  => !empty($exp_rtn_year[$i]) ? $exp_rtn_year[$i] : null,
                        'expected_return_month' => !empty($exp_rtn_month[$i]) ? $exp_rtn_month[$i] : null,
                        'target_allocation'     => !empty($target_allocation[$i]) ? $target_allocation[$i] : null,
                        'sharpe_ratio'          => !empty($sharpe_ratio[$i]) ? $sharpe_ratio[$i] : null,
                        'volatility'            => !empty($treynor_ratio[$i]) ? $treynor_ratio[$i] : null
                    ], $data);

                    InvestmentDetail::create($invest_detail);
                    if ($request->save_type == 'checkout')
                    {
                        $ref_no     = $sn . str_pad($rn, 3, '0', STR_PAD_LEFT);
                        $trans_hist = array_merge([
                            'investor_id'           => $inv->investor_id,
                            'portfolio_id'          => $portf_id,
                            'reference_no'          => $ref_no,
                            'status_reference_id'   => $status_ref,
                            'trans_reference_id'    => $trans_ref,
                            'type_reference_id'     => $type_ref,
                            'transaction_date'      => !empty($transaction_date[$i]) ? $transaction_date[$i] : null,
                            'percentage'            => !empty($percentage[$i]) ? $percentage[$i] : null
                        ], $data);
                        TransactionHistories::create($trans_hist);
                        $rn++;
                    }
                }
                return $this->app_partials(1, 0, ['id' => $id]);
            }
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}