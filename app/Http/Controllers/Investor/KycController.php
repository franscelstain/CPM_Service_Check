<?php

namespace App\Http\Controllers\Investor;

use App\Models\Auth\Investor;
use App\Models\SA\Reference\KYC\RiskProfiles\Answer;
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\Users\Investor\Account;
use App\Models\Users\Investor\Address;
use App\Models\Users\Investor\Question;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use Auth;

class KycController extends AppController
{
    public $table = 'Users\Investor\Investor';
    
    private function address()
    {
        $address    = $subdistrict = $city = $province = $postal = '';
        $api        = $this->api_ws(['sn' => 'InvestorAddress', 'val' => [Auth::user()->identity_no]])->original['data'];
        if (!empty($api))
        {
            foreach ($api as $a)
            {   
                $addr = [];

                if (!empty($a->address1)) $addr[] = $a->address1;
                if (!empty($a->address2)) $addr[] = $a->address2;
                if (!empty($a->address3)) $addr[] = $a->address3;
                if (!empty($a->address4)) $addr[] = $a->address4;
                if (!empty($a->address5)) $addr[] = $a->address5;
                
                $province       = $a->province;
                $city           = $a->city;
                $subdistrict    = $a->subDistrict;
                $postal         = $a->postalCode;
                $address        = implode(', ', $addr);
                
                if ($a->addressType == 'KTP')
                    break;
            }
        }
        return ['api' => $api, 'id' => Auth::user()->identity_no, 'address' => $address, 'subdistrict_name' => $subdistrict, 'city_name' => $city, 'province_name' => $province, 'postal_code' => $postal];
    }
    
    public function detail_profile(Request $request)
    {
        try
        {
            $account    = Account::where([['investor_id', Auth::id()], ['is_active', 'Yes']])->get();
            $q_addr     = Address::select('u_investors_addresses.*', 'b.region_name as subdisctrict_name', 'c.region_name as city_name', 'd.region_name as province_name')
                        ->leftJoin('m_regions as b', function($qry) { $qry->on('u_investors_addresses.subdistrict_id', '=', 'b.region_id')->where('b.is_active', 'Yes'); })
                        ->leftJoin('m_regions as c', function($qry) { $qry->on('u_investors_addresses.city_id', '=', 'c.region_id')->where('c.is_active', 'Yes'); })
                        ->leftJoin('m_regions as d', function($qry) { $qry->on('u_investors_addresses.province_id', '=', 'd.region_id')->where('d.is_active', 'Yes'); })
                        ->where([['investor_id', Auth::id()], ['address_type', 'KTP'], ['u_investors_addresses.is_active', 'Yes']])->first();
            $address    = !empty($q_addr->investor_address_id) ? $q_addr : $this->address();
            return $this->app_response('KYC', ['address' => $address, 'account' => $account]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function risk_profile(Request $request, $save=false)
    {
        try
        {
            $inv_qst    = [];
            $profile    = $profile_id = $desc = '';
            $score      = 0;
            $n          = 1;
            $arrQst     = [];
            foreach ($request->input('question_id') as $qst)
            {
                $ans    = Answer::select('answer_id', 'answer_score', 'm_profile_answers.sequence_to as answer_no', 'b.sequence_to as question_no')
                        ->join('m_profile_questions as b', 'm_profile_answers.question_id', 'b.question_id')
                        ->where([['answer_id', $request->input('answer'.$qst)], ['m_profile_answers.is_active', 'Yes'], ['b.is_active', 'Yes']])->first();
                $score  = !empty($ans->answer_score) ? $score + $ans->answer_score : 0;
                if ($save && !empty($ans->answer_id))
                {
                    $arrQst[]   = ['no' => strval($ans->question_no), 'answer' => strval($ans->answer_no)];
                    $inv_qst[]  = [
                        'question_id'   => $qst,
                        'question_no'   => $ans->question_no,
                        'answer_id'     => $ans->answer_id,
                        'answer_no'     => $ans->answer_no,
                        'answer_score'  => $ans->answer_score
                    ]; 
                }
            }
            
            $api = $this->api_ws(['sn' => 'RiskProfileWMS', 'val' => [Auth::user()->cif, true, $arrQst]])->original['data'];
            $this->app_response('Risk Profile',$api);
            if (!empty($api))
            {  
                if(!empty($api->profileId) && ($api->profileId != 0)) { 
                    $risk       = Profile::where([['ext_code', $api->profileId], ['is_active', 'Yes']])->first();
                    $profile    = $risk->profile_name;
                    $profile_id = $risk->profile_id;
                    $desc       = $risk->description;
                }    
            }
            
            if (empty($profile_id))
            {   
                if($score < 1) 
                {
                    $risk = Profile::where('is_active', 'Yes')->orderBy('sequence_to','asc')->first();
                    $profile        = $risk->profile_name;
                    $profile_id     = $risk->profile_id;
                    $desc           = $risk->description;
                } 
                else 
                {
                    $risk = Profile::where('is_active', 'Yes')->orderBy('sequence_to');
                    foreach ($risk->get() as $rk)
                    {    
                        if ($score >= $rk->min && $score <= $rk->max)
                        {
                            $profile        = $rk->profile_name;
                            $profile_id     = $rk->profile_id;
                            $desc           = $rk->description;
                            break;
                        }
                        $n++;
                    }
                }    
            }
            return !$save ? $this->app_response('Risk Profile', ['desc' => $desc, 'profile' => $profile, 'seq' => $n, 'total_risk' => $risk->count()]) : (object) ['profile_id' => $profile_id, 'question' => $inv_qst];
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function save(Request $request, $id=null)
    {  
        try
        {
            $add    = [];
            $ip     = $request->input('ip');
            $risk   = $this->risk_profile($request, true);
            $qst    = Question::where([['is_active', 'Yes'], ['investor_id', $id]])->orderBy('repetition', 'desc')->first();
            
            $request->request->add(['profile_id' => $risk->profile_id, 'profile_effective_date' => $this->app_date(), 'profile_expired_date' => date('Y-m-d', strtotime('+2 year '. $this->app_date()))]);
            $save = $this->db_save($request, $id, ['validate' => true]);
            
            if (!empty($id))
            {
                for ($i = 0; $i < count($risk->question); $i++)
                {
                    Question::create([
                        'investor_id'   => $id,
                        'profile_id'    => $risk->profile_id,
                        'question_id'   => $risk->question[$i]['question_id'], 
                        'answer_id'     => $risk->question[$i]['answer_id'], 
                        'answer_score'  => $risk->question[$i]['answer_score'],
                        'repetition'    => !empty($qst->repetition) ? $qst->repetition + 1 : 1,
                        'created_by'    => Auth::user()->usercategory_name.':'.Auth::id().':'.Auth::user()->fullname,
                        'created_host'  => $ip
                    ]);
                }
            }
            
            /*$acc    = $this->api_ws($request, 'ClientAccount', [Auth::user()->identity_no])->original['data'];
            $addr   = $this->api_ws($request, 'ClientAddress', [Auth::user()->identity_no])->original['data'];
            $card   = $this->api_ws($request, 'ClientAccountCard', [$request->input('cif')])->original['data'];
            $auth   = ['email', 'email_verified_at', 'otp', 'otp_created', 'password', 'profile_id', 'usercategory_id', 'valid_account'];
            foreach ($auth as $at)
            {
                switch ($at)
                {
                    case 'profile_id'       : $val = $risk->profile_id; break;
                    case 'valid_account'    : $val = 'Yes'; break;
                    default                 : $val = Auth::user()->$at;
                }
                $add[$at] = $val;
            }*/
            
            /*foreach ($acc as $ac)
            {
                $n          = 0;
                $card_no    = '';
                $card_exp   = '';
                foreach ($card as $c)
                {
                    if ($ac['AccountNo'] == $c['AccountNo'])
                    {
                        $card_no    = $c['CardNo'];
                        $card_exp   = $this->cpm_date($c['ExpiredDate'], '.net');
                        unset($card[$n]);
                        break;
                    }
                    $n++;
                }
                Account::create([
                    'investor_id'   => Auth::id(),
                    'account_name'  => $ac['AccountName'],
                    'account_type'  => $ac['ProductType'],
                    'account_no'    => $ac['AccountNo'],
                    'balance'       => $ac['Balance'],
                    'currency'      => $ac['Currency'],
                    'bank_branch'   => $ac['Office'],
                    'card_no'       => $card_no,
                    'card_expired'  => $card_exp,
                    'ext_code'      => $ac['AccountID'],
                    'is_data'       => 'WS',
                    'created_by'    => Auth::user()->usercategory_name.':'.Auth::id().':'.Auth::user()->fullname,
                    'created_host'  => $ip
                    
                ]);
            }
            
            foreach (['idcard', 'domicile', 'mailing'] as $a)
            {
                if ($a == 'idcard')
                {
                    $prv        = $prv_card         = $this->cpm_row('region_id', ['where' => [['region_code', $addr[0]['ProvinceCode']]]], 'SA\Reference\KYC\Region')->original['data'];
                    $city       = $city_card        = $this->cpm_row('region_id', ['where' => [['region_code', $addr[0]['CityCode']]]], 'SA\Reference\KYC\Region')->original['data'];
                    $district   = $district_card    = $this->cpm_row('region_id', ['where' => [['region_code', $addr[0]['DistrictCode']]]], 'SA\Reference\KYC\Region')->original['data'];
                    $postal     = $postal_card      = $addr[0]['PostalCode'];
                    $address    = $address_card     = $addr[0]['Address'];
                    $is_data    = $data_card        = 'WS';
                }
                else
                {
                    $dest       = $request->input($a.'_destination');
                    $prv        = $prv_dmc      = $dest != 'idcard' ? $a == 'mailing' && $dest == 'domicile' ? $prv_dmc : $request->input($a.'_province_id') : $prv_card;
                    $city       = $city_dmc     = $dest != 'idcard' ? $a == 'mailing' && $dest == 'domicile' ? $city_dmc : $request->input($a.'_city_id') : $city_card;
                    $district   = $district_dmc = $dest != 'idcard' ? $a == 'mailing' && $dest == 'domicile' ? $district_dmc : $request->input($a.'_subdistrict_id') : $district_card;
                    $postal     = $postal_dmc   = $dest != 'idcard' ? $a == 'mailing' && $dest == 'domicile' ? $postal_dmc : $request->input($a.'_postal_code') : $postal_card;
                    $address    = $address_dmc  = $dest != 'idcard' ? $a == 'mailing' && $dest == 'domicile' ? $address_dmc : $request->input($a.'_address') : $address_card;
                    $is_data    = $data_dmc     = $dest != 'idcard' ? $a == 'mailing' && $dest == 'domicile' ? $data_dmc : 'APPS' : $data_card;
                }
                
                Address::create([
                    'investor_id'       => Auth::id(),
                    'province_id'       => $prv,
                    'city_id'           => $city,
                    'subdistrict_id'    => $district,
                    'postal_code'       => $postal,
                    'address'           => $address,
                    'address_type'      => $a,
                    'is_data'           => $is_data,
                    'created_by'        => Auth::user()->usercategory_name.':'.Auth::id().':'.Auth::user()->fullname,
                    'created_host'      => $ip
                ]);
            }*/
            
            return $this->app_partials(1, 0, ['session' => ['user_auth' => Investor::find(Auth::id())]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}
