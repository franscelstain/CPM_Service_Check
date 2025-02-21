<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\AppController;
use App\Models\Auth\Investor;
use App\Models\Users\Investor\Investor as Investors;
use App\Models\Investor\Financial\Condition\IncomeExpense;
use App\Models\SA\Reference\KYC\Region;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Users\Investor\Account;
use App\Models\Users\Investor\Address;
use App\Models\Users\Investor\Edd;
use App\Models\Users\Investor\EddFamily;
use App\Models\Users\Investor\CardPriority;
use App\Models\Users\Investor\Question;
use App\Models\Users\Investor\InvestorPasswordAttemp;
use App\Models\Users\User;
use App\Models\Users\SalesBranch;
use App\Models\SA\Assets\Products\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestorController extends AppController
{
    public $table = 'Users\Investor\Investor';

    public function index(Request $request)
    {
        try
        {
            if ($this->auth_user()->usercategory_name == 'Sales')
                return $this->list_for_sales($request);
            else
                return $this->app_response('investor', ['key' => 'investor_id', 'list' => Investors::where('is_active', 'Yes')->get()]);             
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }  
    }
    
    public function address($id)
    {
        try
        {
            $prv    = Region::where([['region_type', 'Provinsi'], ['is_active', 'Yes']])->get();
            $data   = Address::where([['investor_id', $id], ['is_active', 'Yes']])->get();
            if ($data->count() > 0)
            {
                $address = ['province' => $prv];
                foreach ($data as $dt)
                {
                    switch ($dt->address_type)
                    {
                        case 'Mailing'  : $addr_type = 'mailing'; break;
                        case 'Home'     : $addr_type = 'domicile'; break;
                        default         : $addr_type = 'idcard';
                    }
                    
                    $area   = ' ';
                    $loc    = [];
                    foreach (['subdistrict_id', 'city_id', 'province_id'] as $rg_id)
                    {
                        $region = Region::where([['region_id', $dt->$rg_id], ['is_active', 'Yes']])->first();
                        $area   = !empty($region->region_id) ? trim($area) . ', '. $region->region_name . ' ' : '';
                        if ($dt->address_type != 'idcard')
                        {
                            $loc[substr($rg_id, 0, -3)] = $rg_id != 'province_id' && !empty($region->region_id) ? Region::where([['parent_code', $region->parent_code], ['is_active', 'Yes']])->get() : [];
                        }
                    }

                    $address[$addr_type] = ['address' => $dt->address . $area . $dt->postal_code, 'location' => $loc, 'detail' => $dt];
                }
            }
            else
            {                
                $address = ['idcard' => '', 'domicile' => '', 'mailing' => '', 'province' => $prv];
            }
            return $this->app_response('Investor Address', $address);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function bank_account($id)
    {
        try
        {
            $data   = Account::select('u_investors_accounts.investor_account_id', 'u_investors_accounts.account_no', 'u_investors_accounts.account_name', 'b.branch_name', 'c.currency_name', 'd.account_type_name', 'd.account_type_id')
                    ->leftJoin('m_bank_branches as b', function($qry) { return $qry->on('u_investors_accounts.bank_branch_id', '=', 'b.bank_branch_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_currency as c', function($qry) { return $qry->on('u_investors_accounts.currency_id', '=', 'c.currency_id')->where('c.is_active', 'Yes'); })
                    ->leftJoin('m_account_types as d', function($qry) { return $qry->on('u_investors_accounts.account_type_id', '=', 'd.account_type_id')->where('d.is_active', 'Yes'); })
                    ->where([['investor_id', $id], ['u_investors_accounts.is_active', 'Yes']])
		    ->whereNotNull('u_investors_accounts.account_type_id')->get();
            return $this->app_response('Investor Bank Account', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }    
    }
    
    public function card(Request $request)
    {
        try
        {
            $card = [];
            if ($request->cif)
            {
                $card = CardPriority::select('u_investors_card_priorities.*', 'b.card_type_name')
                        ->leftJoin('u_investors_card_types as b', function($qry) { return $qry->on('u_investors_card_priorities.investor_card_type_id', '=', 'b.investor_card_type_id')->where('b.is_active', 'Yes'); })
                        ->where([['u_investors_card_priorities.cif', $request->cif], ['u_investors_card_priorities.is_active', 'Yes']])
                        ->get();
            }
            return $this->app_response('Investor Card', $card);        
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
		return $this->app_response('investor', Investor::find($id));
    }
    
    public function detail_with_sales($id)
    {
        try
        {
            return $this->app_response('Investor Detail', Investor::where([['investor_id', $id], ['sales_id', $this->auth_user()->id], ['is_active', 'Yes']])->first());
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function edd($id)
    {
        try
        {
            $data = Edd::where([['is_active', 'Yes'], ['investor_id', $id]])->first();
            $family = !empty($data->investor_edd_id) ? EddFamily::where([['is_active', 'Yes'], ['investor_edd_id', $data->investor_edd_id]])->get(): []; 
            return $this->app_response('Investor data EDD', ['edd' => $data, 'family' =>$family]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }

    }

    //  public function list_for_sales(Request $request)
    // {
    //      try
    //     {
    //         $data = Investor::select('investors.*', 'b.profile_name')
    //                 ->leftJoin('m_risk_profiles as b', function($qry) { return $qry->on('investors.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes'); })
    //                 ->where([['investors.sales_id', $this->auth_user()->id], ['investors.is_active', 'Yes'], ['investors.valid_account', 'Yes']])
    // 				->get();
    // 		$inv  = [];
    //         foreach ($data as $dt) 
    //         {
    //             $goals      = $request->show_goals == 'Y' ? TransactionHistoryDay::where([['investor_id', $dt->investor_id], ['history_date', $this->app_date()], ['is_active', 'Yes']])->whereRaw("LEFT(portfolio_id, 1) = '2'")->sum('current_balance') : 0;
    //             $non_goals  = $request->show_non_goals == 'Y' ? TransactionHistoryDay::where([['investor_id', $dt->investor_id], ['history_date', $this->app_date()], ['is_active', 'Yes']])->where(function($qry) { $qry->whereRaw("LEFT(portfolio_id, 1) NOT IN ('2', '3')")->orWhereNull('portfolio_id'); })->sum('current_balance') : 5;
                
    //             $inv[] = [
    //             	'investor_id'           => $dt->investor_id,
    //                 'cif'           		=> $dt->cif,
    //                 'fullname'      		=> $dt->fullname,
    //                 'photo_profile' 		=> $dt->photo_profile,
    //                 'profile_id'            => $dt->profile_id,
    //                 'profile_name'  		=> $dt->profile_name,
    //                 'profile_expired_date'  => $dt->profile_expired_date,
    //                 'sid'					=> $dt->sid,
    //                 'usercategory_name'     => $dt->usercategory_name,
    //                 'balance_amount'		=> !empty($request->show_amount) && $request->show_amount == 'N' ? 0 : 0,
    //                 'balance_goals'         => $goals,
    //                 'balance_non_goals'     => floatval($non_goals)
    //             ];
    //         }
    //         return $this->app_response('investor', $inv);  
    //      }
    //     catch (\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     } 
    // }
    public function list_for_sales(Request $request)
    {
        $inv  = [];
        $limit  = !empty($request->limit) ? $request->limit : 10;
        $page   = !empty($request->page) ? $request->page : 1;
        $offset = ($page-1)*$limit;
        $data = Investor::select('investors.*', 'b.profile_name')
                ->leftJoin('m_risk_profiles as b', function($qry) { return $qry->on('investors.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes'); })
                ->where([['investors.sales_id', $this->auth_user()->id], ['investors.is_active', 'Yes']]);
        
        if (!empty($request->search))
        {
           $data  = $data->where(function($qry) use ($request) {
                        $qry->where('investors.fullname', 'ilike', '%'. $request->search .'%')
                            ->orWhere('investors.cif', 'ilike', '%'. $request->search .'%')
                            ->orWhere('investors.sid', 'ilike', '%'. $request->search .'%');
                    });
        }
        foreach ($data->get() as $dt) 
        {
            $goals      = $request->show_goals == 'Y' ? TransactionHistoryDay::where([['investor_id', $dt->investor_id], ['history_date', $this->app_date()], ['is_active', 'Yes']])->whereRaw("LEFT(portfolio_id, 1) = '2'")->sum('current_balance') : 0;
            $non_goals  = $request->show_non_goals == 'Y' ? TransactionHistoryDay::where([['investor_id', $dt->investor_id], ['history_date', $this->app_date()], ['is_active', 'Yes']])->where(function($qry) { $qry->whereRaw("LEFT(portfolio_id, 1) NOT IN ('2', '3')")->orWhereNull('portfolio_id'); })->sum('current_balance') : 5;
            
            $inv[] = [
                'investor_id'           => $dt->investor_id,
                'cif'                   => $dt->cif,
                'fullname'              => $dt->fullname,
                'photo_profile'         => $dt->photo_profile,
                'profile_id'            => $dt->profile_id,
                'profile_name'          => $dt->profile_name,
                'profile_expired_date'  => $dt->profile_expired_date,
                'sid'                   => $dt->sid,
                'usercategory_name'     => $dt->usercategory_name,
                'balance_amount'        => !empty($request->show_amount) && $request->show_amount == 'N' ? 0 : 0,
                'balance_goals'         => $goals,
                'balance_non_goals'     => floatval($non_goals)
            ];
        }

        $total = $data->count();
        $total_data = $page*$limit;
        $paginate = [
            'current_page'  => $page,
            'data'          => $inv,
            'from'          => $page > 1 ?  1 + (($page-1) * $limit) : 1,
            'per_page'      => $limit,
            'to'            => $total_data >= $total ? $total : $total_data,
            'total'         => $total
        ];
        return $this->app_response('investor', $paginate);   
    }

    public function max_aum()
    {
        try
        {
            $investor   = TransactionHistoryDay::selectRaw('SUM(t_trans_histories_days.current_balance) as current_balances, b.investor_id, b.fullname, b.sid, b.photo_profile')
                        ->join('u_investors as b', 't_trans_histories_days.investor_id', '=', 'b.investor_id')
                        ->where([['t_trans_histories_days.is_active', 'Yes'], ['t_trans_histories_days.history_date', DB::raw('current_date')], ['b.is_active', 'Yes'], ['b.sales_id', $this->auth_user()->id]])
                        ->groupBy(['b.investor_id', 'b.fullname', 'b.sid', 'b.photo_profile'])
                        ->orderBy('current_balances', 'desc')
                        ->limit(5)
                        ->get();       

            $investorId = $investor->pluck('investor_id');
            
            $goals  = DB::table('t_trans_histories_days')
                    ->where([['is_active', 'Yes'], ['history_date', DB::raw('current_date')], [DB::raw('LEFT(portfolio_id, 1)'), '2']])
                    ->whereIn('investor_id', $investorId)
                    ->select('investor_id', DB::raw('SUM(current_balance) as balance'))
                    ->groupBy('investor_id')
                    ->get();

            $non_goals = DB::table('t_trans_histories_days')
                    ->where([['is_active', 'Yes'], ['history_date', DB::raw('current_date')]])
                    ->where(function($qry) { $qry->whereNull('portfolio_id')->orWhere(DB::raw('LEFT(portfolio_id, 1)'), '1'); })
                    ->whereIn('investor_id', $investorId)
                    ->select('investor_id', DB::raw('SUM(current_balance) as balance'))
                    ->groupBy('investor_id')
                    ->get();

            $data = $investor->map(function($item) use ($goals, $non_goals) {
                $goal       = optional($goals->where('investor_id', $item->investor_id)->first())->balance ?? 0;
                $non_goal   = optional($non_goals->where('investor_id', $item->investor_id)->first())->balance ?? 0;
                return [
                    'investor_id'   => $item->investor_id,
                    'fullname'      => $item->fullname,
                    'sid'           => $item->sid,
                    'photo_profile' => $item->photo_profile,
                    'goals'         => floatval($goal),
                    'non_goals'     => floatval($non_goal)
                ];
            });

            return $this->app_response('Investor - Max AUM', $data); 
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function risk_profile($id)
    {
        try
        {
            $n      = $total = 1;
            $inv    = Investor::where([['investor_id', $id], ['is_active', 'Yes']])->first();
            $uQst   = Question::where([['investor_id', $id], ['is_active', 'Yes']])->max('repetition');
            $rep    = !empty($uQst) ? $uQst : 1;
            $qst    = Question::select('question_text', 'answer_text', 'answer_icon', 'icon')
                    ->join('m_profile_questions as b', 'u_investors_questions.question_id', '=', 'b.question_id')
                    ->leftJoin('m_profile_answers as c', function($qry) {
                        $qry->on('u_investors_questions.answer_id', '=', 'c.answer_id')->where('c.is_active', 'Yes');
                    })
                    ->where([['investor_id', $id], ['repetition', $rep], ['u_investors_questions.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->get();
            
            if (!empty($inv->profile_id))
            {
                $risk   = Profile::where('is_active', 'Yes')->orderBy('sequence_to');
                $total  = $risk->count();
                foreach ($risk->get() as $rk)
                {
                    if ($rk->profile_id == $inv->profile_id)
                        break;                    
                    $n++;
                }
            }
            
            return $this->app_response('Investor Risk Profile', ['profile' => ['total' => $total, 'seq' => $n], 'questionnaire' => $qst]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function risk_profile_expired()
    {
        try
        {
            $data = Investors::select('u_investors.*', 'b.profile_name')
                    ->join('m_risk_profiles as b', 'u_investors.profile_id', '=', 'b.profile_id')
                    ->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes'], ['sales_id', $this->auth_user()->id]])
                    ->limit(5)
                    ->orderBy('profile_expired_date', 'desc')
                    ->get();
            return $this->app_response('Risk Profile Expired', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        $saveby     = $this->auth_user()->usercategory_name.':'.$this->auth_user()->id.':'.$this->auth_user()->fullname;
        $is_active  = $request->input('is_active');
        $is_enable  = $request->input('is_enable');
        $st         = $request->method() == 'POST' && empty($id) ? 'cre' : 'upd';
        $data       = ['is_active'      => $is_active,
                       'is_enable'      => $is_enable,
                       $st.'ated_by'    => $saveby,
                       $st.'ated_host'  => $request->input('ip')
                      ];
        $qry        = $request->method() == 'POST' && empty($id) ? Investors::create($data) : Investors::where('investor_id', $id)->update($data);

        if($is_enable == 'Yes') 
        {
            InvestorPasswordAttemp::where('investor_id', empty($id) ? $qry->id : $id)->update(['is_active' => 'No','attempt_count' => 0]); 
        }    
        
        return $this->app_partials(1, 0, ['id' => empty($id) ? $qry->id : $id]);  
    }

    public function totalinvestor(Request $request)
    {
        try
        {
            $data       = [];
            $time       = !empty($request->time) ? $request->time : 'week';
            $date       = date('Y-m-d', strtotime('-1 '. $time)); 
            $dataAll    = Investor::where('is_active', 'Yes');
            $dataNow    = Investor::where([['is_active', 'Yes'], ['created_at', '>=', $date]]);
            
            if (!empty($request->sales_id))
            {
                $dataAll    = $dataAll->where('sales_id', $request->sales_id);
                $dataNow    = $dataNow->where('sales_id', $request->sales_id);
            }
            
            $total_investor = $dataAll->count();
            $inv_time       = $dataNow->count();
            $growth         = is_numeric($total_investor) && $total_investor > 0 ? $inv_time / $total_investor : 0;
            
            if (!empty($request->get_data) && $request->get_data == 'yes')
            {
                if (!empty($request->limit) && is_numeric($request->limit))
                    $dataAll = $dataAll->limit($request->limit);
                $data = $dataAll->orderBy('created_at', 'desc')->get();
            }
            
            return $this->app_response('all-investor', ['data' => $data, 'growth_investor' => $growth, 'total_investor' => $total_investor, 'total_investor_time' => $inv_time]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function totalsales(Request $request)
    {
        try
        {
            $data   = User::join('u_users_categories','u_users_categories.usercategory_id','=','u_users.usercategory_id')
                    ->where([['u_users_categories.usercategory_name', 'Sales'], ['u_users.is_active', 'Yes']])
                    ->count();
            $branch = SalesBranch::where([['is_active', 'Yes']])->count();
            return $this->app_response('total_sales', ['total_sales' => $data, 'total_branch' => $branch]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function investor_asset_liability_by_sales($id='')
    {
        $networth = DB::table('t_assets_liabilities')
                   ->select('investor_id', DB::raw('COALESCE(sum(amount),0) as networth'))
                   ->groupBy('investor_id');
        $data = DB::table('u_investors')
                     ->where("sales_id", $id)
                     ->joinSub($networth, 'networth', function ($join) {
                       $join->on('u_investors.investor_id', '=', 'networth.investor_id');
                     })->get();
      
		$res  = ["key"=>"investor_id", "list"=>$data];
		return $this->app_response('investor', $res);   
    }

    public function detail_edit(Request $request, $id = null)
    {
        
        $data   = Investors::whereIn("is_active", ["Yes", "No"])
                ->where("investor_id", "=", $id)
                ->get();
        $res    = ["key"=>"investor_id", "list"=>$data];

        return $this->app_response('investor', $res);
    }

    public function validation_before_purchase(Request $request)
    {
        try
        {
            $data = Investors::select('u_investors.*')
                    ->where([['u_investors.is_active', 'Yes'], ['investor_id', $this->auth_user()->id]])
                    ->first();

            $list_validation_error = array('validation_error_sid'=>false,'validation_error_risk_profile_product'=>false,'validation_error_risk_profile_expired'=>false);

            if(empty(trim($data->sid)) && empty($data->wms_status)) {
                $list_validation_error['validation_error_sid'] = true;
            }   

            if(empty($data->profile_expired_date) || $this->app_date() > $data->profile_expired_date) {
                $list_validation_error['validation_error_risk_profile_expired'] = true;
            } 

            if(empty(trim($data->sid)) && $data->wms_status == '200') {  
                $list_validation_error['validation_error_risk_profile_on_progress'] = true;
            }

            if(!empty($request->product_id)) {
                $product = Product::select('m_products.*')
                          ->where([['m_products.is_active', 'Yes'],['m_products.product_id', $request->product_id]])
                          ->first(); 
                if(!empty($product->profile_id))  {
                    if($product->profile_id > $data->profile_id) {
                        $list_validation_error['validation_error_risk_profile_product'] = true;
                    }
                }     
            }   
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }

        return $this->app_response('Investor Validation Before Purchase', $list_validation_error);   
    }
}