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

class InvestorsData extends AppController
{
    protected $table = 'Users\Investor\Investor';


    public function investorsdata($investor_id = '')
    {
        try
        {
            $inv_id = !empty($investor_id) ? $investor_id : Auth::id();
            $data = Investor::where('u_investors.investor_id',$inv_id)
            ->leftJoin('u_investors_addresses', 'u_investors_addresses.investor_id', '=', 'u_investors.investor_id')
                ->leftJoin('u_investors_accounts', 'u_investors_accounts.investor_id', '=', 'u_investors.investor_id')
                ->first();
            return $this->app_response('Investor Data', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function questionaire($investor_id = ''){
        try
        {
            $inv_id = !empty($investor_id) ? $investor_id : Auth::id();
            $data = Question::where(['investor_id'=>$inv_id,'profile_id'=>Auth::user()->profile_id])->orderBy('question_id','ASC')
                ->get();
            return $this->app_response('Questionaire', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function bankaccounts($investor_id = ''){
        try
        {
            $inv_id = !empty($investor_id) ? $investor_id : Auth::id();
            $data = Account::where('u_investors_accounts.investor_id',$inv_id)
                ->leftJoin('m_bank_branches', 'm_bank_branches.bank_branch_id', '=', 'u_investors_accounts.bank_branch_id')
                ->leftJoin('m_bank', 'm_bank.bank_id', '=', 'm_bank_branches.bank_id')
                ->where('u_investors_accounts.is_active','Yes')
                ->get();
            return $this->app_response('Bank Accounts', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }


    public function optionform($tablename=null){
        try {
            $data = \Illuminate\Support\Facades\DB::table($tablename)->where('is_active','Yes')->get();
            return $this->app_response('option', $data);
        }
        catch (\Exception $e){
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
                $request->request->add(['identity_no' => Auth::user()->identity_no]);
                $this->db_save($request, Auth::id(), $this->form_ele());

                $user   = Investor::find(Auth::id());
                $data   = ['img' => $user->photo_profile];
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
        return ['path' => 'investor/img'];
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
