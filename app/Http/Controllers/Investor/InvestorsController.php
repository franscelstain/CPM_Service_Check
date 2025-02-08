<?php

namespace App\Http\Controllers\Investor;

use App\Models\Administrative\Config\Config;
use App\Models\Investor\Financial\Planning\Goal\Investment;
use App\Models\Investor\Financial\Plannig\Current\Outstanding;
use App\Models\Transaction\TransactionHistory;
use App\Models\SA\Reference\KYC\Region;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\SA\Transaction\Reference;
use App\Models\Users\Investor\Account;
use App\Models\Users\Investor\Address;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\Question;
use App\Models\Users\Investor\CardPriority;
use App\Models\Users\Investor\CardType;
use App\Models\Users\Investor\Category;
use App\Models\Users\User;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use DB;
use Auth;

class InvestorsController extends AppController
{
    protected $table = 'Users\Investor\Investor';
    
    public function address()
    {
        try
        {
            $prv    = Region::where([['region_type', 'Provinsi'], ['is_active', 'Yes']])->get();
            $data   = Address::where([['investor_id', Auth::id()], ['is_active', 'Yes']])->get();
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
    
    public function bank_account($investor_id = '')
    {
        try
        {
            $inv_id = !empty($investor_id) ? $investor_id : Auth::id();
            $data   = Account::select('u_investors_accounts.investor_account_id', 'u_investors_accounts.account_no', 'u_investors_accounts.account_name', 'b.branch_name', 'c.currency_name', 'd.account_type_name')
                    ->leftJoin('m_bank_branches as b', function($qry) { return $qry->on('u_investors_accounts.bank_branch_id', '=', 'b.bank_branch_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_currency as c', function($qry) { return $qry->on('u_investors_accounts.currency_id', '=', 'c.currency_id')->where([['c.is_active', 'Yes'], ['currency_code', 'IDR']]); })
                    ->leftJoin('m_account_types as d', function($qry) { return $qry->on('u_investors_accounts.account_type_id', '=', 'd.account_type_id')->where('d.is_active', 'Yes')->whereIn('d.account_type_name',['GIRO','SAVING']); })
                    ->where([['investor_id', $inv_id], ['u_investors_accounts.is_active', 'Yes']])
                    ->whereNotNull('u_investors_accounts.account_type_id')->get();
            return $this->app_response('Investor Bank Account', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }    
    }

    public function change_address(Request $request, $id=null)
    {
        try
        {
            $error  = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data   = [];
            if (Auth::id())
            {                
                switch ($request->addr_type)
                {
                    case 'domicile' : $addr_type = 'Home'; break;
                    case 'mailing'  : $addr_type = 'Mailing'; break;
                    default         : $addr_type = 'KTP';
                }
                
                $act = !empty($id) ? 'upd' : 'cre';
                
                if ($request->destination != 'new')
                {
                    $dest = $request->destination == 'domicile' ? 'Home' : 'KTP';
                    $addr = Address::where([['investor_id', Auth::id()], ['address_type', $dest], ['is_active', 'Yes']])->first();
                    if (!empty($addr->investor_id))
                    {
                        $prv            = !empty($addr->province_id) ? Region::find($addr->province_id) : [];
                        $city           = !empty($addr->city_id) ? Region::find($addr->city_id) : [];
                        $subdistrict    = !empty($addr->subdistrict_id) ? Region::find($addr->subdistrict_id) : [];
                        $postal_code    = $addr->postal_code;
                        $address        = $addr->address;
                    }
                }
                else
                {                    
                    $prv            = Region::find($request->province_id);
                    $city           = Region::find($request->city_id);
                    $subdistrict    = Region::find($request->subdistrict_id);
                    $postal_code    = $request->postal_code;
                    $address        = $request->address;
                }
                
                $s_name = !empty($subdistrict->region_name) ? ', ' . $subdistrict->region_name : '';
                $c_name = !empty($city->region_name) ? ', ' . $city->region_name : '';
                $p_name = !empty($prv->region_name) ? ', ' . $prv->region_name . ' ' : ' ';
                
                if (!empty($address))
                {
                    $data = [
                        'investor_id'       => Auth::id(),
                        'province_id'       => !empty($prv->region_id) ? $prv->region_id : null,
                        'city_id'           => !empty($city->region_id) ? $city->region_id : null,
                        'subdistrict_id'    => !empty($subdistrict->region_id) ? $subdistrict->region_id : null,
                        'postal_code'       => $postal_code,
                        'address'           => $address,
                        'address_type'      => $addr_type,
                        'is_active'         => 'Yes',
                        $act.'ated_by'      => Auth::user()->usercategory_name.':'.Auth::id().':'.Auth::user()->fullname,
                        $act.'ated_host'    => $request->input('ip'),
                    ];

                    $full_addr  = $address . $s_name . $c_name . $p_name . $postal_code;
                    $qry        = $request->method() == 'POST' ? Address::create($data) : Address::where('investor_address_id', $id)->update($data);
                    $id         = $request->method() == 'POST' ? $qry->investor_address_id : $id;
                    $data       = ['id' => $id, 'address' => $full_addr];
                    $error      = [];
                }
                else
                {
                    $error  = ['error_code' => 422, 'error_msg' => ['Address is required']];
                }
            }
            return $this->app_response('Change Address', $data, $error);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function change_photo(Request $request)
    {
     try
        {
            $error  = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data   = [];
            if (Auth::id())
            {
                if (!empty($this->app_validate($request, ['photo_profile' => 'required|image|mimes:jpeg,png,jpg'])))
                    exit;

                $request->request->add(['identity_no' => Auth::user()->identity_no]);
                $this->db_save($request, Auth::id(), $this->form_ele());
                
                $file = $request->file('photo_profile');
                $user   = Investor::find(Auth::id());
                $data   = ['img' => $user->photo_profile];
                // $data   = ['img' => $file];
                $error  = [];
            }
            return $this->app_response('Change Photo', $data, $error);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }   
    }
    
    protected function form_ele()
    {
        return ['path' => 'investor/img', 'validate' => true];
    }
    
    public function investor_card()
    {
        try
        {
            $card = CardPriority::select('u_investors_card_priorities.*', 'b.card_type_name')
                    ->leftJoin('u_investors_card_types as b', function($qry) { return $qry->on('u_investors_card_priorities.investor_card_type_id', '=', 'b.investor_card_type_id')->where('b.is_active', 'Yes'); })
                    ->where([['u_investors_card_priorities.cif', Auth::user()->cif], ['u_investors_card_priorities.is_active', 'Yes']])
                    ->get();

             return $this->app_response('Investor Card', $card);        
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function risk_profile()
    {
        try
        {
            $n      = $total = 1;
            $uQst   = Question::where([['investor_id', Auth::id()], ['is_active', 'Yes']])->max('repetition');
            $rep    = !empty($uQst) ? $uQst : 1;
            $qst    = Question::select('question_text', 'answer_text', 'answer_icon', 'icon')
                    ->join('m_profile_questions as b', 'u_investors_questions.question_id', '=', 'b.question_id')
                    ->leftJoin('m_profile_answers as c', function($qry) {
                        $qry->on('u_investors_questions.answer_id', '=', 'c.answer_id')->where('c.is_active', 'Yes');
                    })
                    ->where([['investor_id', Auth::id()], ['repetition', $rep], ['u_investors_questions.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->get();
            
            if (!empty(Auth::user()->profile_id))
            {
                $risk   = Profile::where('is_active', 'Yes')->orderBy('sequence_to');
                $total  = $risk->count();
                foreach ($risk->get() as $rk)
                {
                    if ($rk->profile_id == Auth::user()->profile_id)
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
    
    public function investor_by_roles(Request $request)
    {
        try
        {
            $get_data   = isset($request->get_data) ? $request->get_data : '';
            $internal   = isset($request->internal) ? $request->internal : '';
            $limit      = !empty($request->limit) ? $request->limit : 10;
            $page       = !empty($request->page) ? $request->page : 1;
            $data       = Investor::select('investors.*')
                        ->where([['investors.is_active', 'Yes']]);
            $data       = $this->investor_roles_officer($data);
            $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
            if ($get_data != 'first' && $internal != 'yes')
            {
                if (!empty($request->search))
                {
                    
                    $data->where(function($qry) use ($request, $like) {
                        $qry->where('investors.fullname', $like, '%'. $request->search .'%')
                            ->orWhere('investors.cif', $like, '%'. $request->search .'%')
                            ->orWhere('investors.sid', $like, '%'. $request->search .'%')
                            ->orWhere('investors.profile_name', $like, '%'. $request->search .'%');
                    });
                }
            }

            if (!empty($request->start_date))
                $data->where('investors.profile_expired_date', '>=', $request->start_date);
            if (!empty($request->end_date))
                $data->where('investors.profile_expired_date', '<=', $request->end_date);

            if (!empty($request->riskprofiles))
                $data->where('investors.profile_id', $request->riskprofiles);

            if (isset($request->investor_id))
                $data->where('investors.investor_id', $request->investor_id);

            if (isset($request->cif))
                $data->where('investors.cif', $request->cif);

            $balance_goals = $balance_non_goals = false;
            if (isset($request->show_goals) && $request->show_goals == 'Yes')
            {
                $balance_goals = true;
                $data->addSelect('vw_total_balance_goals.balance_goals')
                     ->leftJoin('vw_total_balance_goals', 'vw_total_balance_goals.investor_id', 'investors.investor_id');
            }
           
            if (isset($request->show_non_goals) && $request->show_non_goals == 'Yes')
            {
                $balance_non_goals = true;
                $data->addSelect('balance_non_goals')
                     ->leftJoin('vw_total_balance_non_goals', 'vw_total_balance_non_goals.investor_id', 'investors.investor_id');
            }
           
            if (isset($request->assets_outstanding) && $request->assets_outstanding == 'Yes')
            {
                $data->addSelect('balance_assets')
                     ->leftJoin('vw_total_assets_outstanding', 'vw_total_assets_outstanding.investor_id', 'investors.investor_id');
            }

            if ($balance_goals && $balance_non_goals)
            {
                if (!empty($request->balance_minimum) && !empty($request->balance_maximum))
                {
                    $data->where(function($qry) use ($request) {
                        $qry->where(function($q) use ($request) {
                                $q->whereBetween('vw_total_balance_goals.balance_goals', [$request->balance_minimum, $request->balance_maximum]);
                            })
                            ->orWhere(function($q) use ($request) {
                                $q->whereBetween('vw_total_balance_non_goals.balance_non_goals', [ $request->balance_minimum, $request->balance_maximum]);
                            });
                    });
                }
                elseif (!empty($request->balance_maximum))
                {
                    $data->where(function($qry) use ($request) {
                        $qry->where('vw_total_balance_goals.balance_goals', '<=', $request->balance_maximum)
                            ->orWhere('vw_total_balance_non_goals.balance_non_goals', '<=', $request->balance_maximum);
                    });
                }
                elseif (!empty($request->balance_minimum))
                {
                    $data->where(function($qry) use ($request) {
                        $qry->where('vw_total_balance_goals.balance_goals', '>=', $request->balance_minimum)
                            ->orWhere('vw_total_balance_non_goals.balance_non_goals', '>=', $request->balance_minimum);
                    });
                }
            }
            elseif ($balance_goals || $balance_non_goals)
            {
                if ($balance_goals)
                {
                    if (!empty($request->balance_minimum) && !empty($request->balance_maximum))
                        $data->whereBetween('vw_total_balance_goals.balance_goals', [$request->balance_minimum, $request->balance_maximum]);
                    elseif (!empty($request->balance_maximum))
                        $data->where('vw_total_balance_goals.balance_goals', '<=', $request->balance_maximum);
                    elseif (!empty($request->balance_minimum))
                        $data->where('vw_total_balance_goals.balance_goals', '>=', $request->balance_minimum);
                }
                else
                {
                    if (!empty($request->balance_minimum) && !empty($request->balance_maximum))
                        $data->whereBetween('vw_total_balance_non_goals.balance_non_goals', [$request->balance_minimum, $request->balance_maximum]);
                    elseif (!empty($request->balance_maximum))
                        $data->where('vw_total_balance_non_goals.balance_non_goals', '<=', $request->balance_maximum);
                    elseif (!empty($request->balance_minimum))
                        $data->where('vw_total_balance_non_goals.balance_non_goals', '>=', $request->balance_minimum);
                }
            }

            if (isset($request->cash_flow) && $request->cash_flow == 'Yes')
            {
                $data->addSelect(DB::raw('(CASE WHEN vw_total_income_investors.amount IS NOT NULL THEN vw_total_income_investors.amount ELSE 0 END) - (CASE WHEN vw_total_expense_investors.amount IS NOT NULL THEN vw_total_expense_investors.amount ELSE 0 END) AS cashflow'));
                $data->leftJoin('vw_total_income_investors', 'vw_total_income_investors.investor_id', 'investors.investor_id');
                $data->leftJoin('vw_total_expense_investors', 'vw_total_expense_investors.investor_id', 'investors.investor_id');
                if(!empty($request->balance_minimum) && !empty($request->balance_maximum))
                {
                    $data->havingBetween('cashflow',[ $request->balance_minimum, $request->balance_maximum]);
                }elseif(!empty($request->balance_minimum) || !empty($request->balance_maximum))
                {
                    if (!empty($request->balance_minimum))
                        $data->where('cashflow', '>=', $request->balance_minimum);
                    else
                        $data->where('cashflow', '<=', $request->balance_maximum);
                }
            }

            if (isset($request->networth) && $request->networth == 'Yes')
            {
                $data->addSelect(DB::raw('(CASE WHEN vw_total_assets_investors.amount IS NOT NULL THEN vw_total_assets_investors.amount ELSE 0 END) - (CASE WHEN vw_total_liabilities_investors.amount IS NOT NULL THEN vw_total_liabilities_investors.amount ELSE 0 END) AS networth'));
                $data->leftJoin('vw_total_assets_investors', 'vw_total_assets_investors.investor_id', 'investors.investor_id');
                $data->leftJoin('vw_total_liabilities_investors', 'vw_total_liabilities_investors.investor_id', 'investors.investor_id');
                if(!empty($request->balance_minimum) && !empty($request->balance_maximum))
                {
                    $data->havingBetween('networth',[ $request->balance_minimum, $request->balance_maximum]);
                }elseif(!empty($request->balance_minimum) || !empty($request->balance_maximum))
                {
                    if (!empty($request->balance_minimum))
                        $data->where('networth', '>=', $request->balance_minimum);
                    else
                        $data->where('networth', '<=', $request->balance_maximum);
                }    
            }

            if ($get_data == 'first')
                $data = $data->first();
            elseif ($limit == '~' || (isset($request->no_paging) && $request->no_paging == 'yes'))
            {
                if (!empty($request->limit) && $limit != '~')
                    $data->limit($limit);
                $data = $data->get();
            }
            else
                $data = $data->paginate($limit, ['*'], 'page', $page);
            
            return $internal == 'yes' ? $data : $this->app_response('Investor', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function sales(Request $request)
    {
        $data = User::select('u_users.*', 'b.fullname as leader_name', 'b.user_code as leader_code', 'c.branch_code', 'c.branch_name')
                ->leftJoin('u_users as b', function($qry) { return $qry->on('u_users.leader_id', '=', 'b.user_id')->where('b.is_active', 'Yes'); })
                ->leftJoin('u_sales_branch as c', function($qry) { return $qry->on('u_users.user_id', '=', 'c.sales_id')->where('c.is_active', 'Yes'); })
                ->where([['u_users.user_id', Auth::user()->sales_id], ['u_users.is_active', 'Yes']])
                ->first();

        return $this->app_response('Sales', $data);
    }

    public function transaction(Request $request)
    {
        try
        {
            $data = TransactionHistory::select('t_trans_histories.*', 'b.fullname', 'b.cif', 'c.product_name', 'd.asset_class_name', 'e.reference_name as status_name', 'f.reference_name as trans_reference_name', 'f.reference_color', 'g.reference_name as type_reference_name')
                    ->join('u_investors as b', 't_trans_histories.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_trans_histories.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function($qry) { return $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes'); })
                    ->leftJoin('m_trans_reference as e', function($qry) { return $qry->on('t_trans_histories.status_reference_id', '=', 'e.trans_reference_id')->where([['e.reference_type', 'Goals Status'], ['e.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as f', function($qry) { return $qry->on('t_trans_histories.trans_reference_id', '=', 'f.trans_reference_id')->where([['f.reference_type', 'Transaction Status'], ['f.is_active', 'Yes']]); })
                    ->leftJoin('m_trans_reference as g', function($qry) { return $qry->on('t_trans_histories.type_reference_id', '=', 'g.trans_reference_id')->where([['g.reference_type', 'Transaction Type'], ['g.is_active', 'Yes']]); })
                    ->where([['t_trans_histories.investor_id', Auth::id()], ['t_trans_histories.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']]);
            
            if (!empty($request->limit))
                $data = $data->limit($request->limit);
            
            return $this->app_response('Transaction', $data->get());
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }   
    }
}