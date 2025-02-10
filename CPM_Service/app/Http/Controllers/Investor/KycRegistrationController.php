<?php

namespace App\Http\Controllers\Investor;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\AppController;
use App\Models\Users\Investor\Address;                                
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\InvestorAddress;
use App\Models\Users\Investor\Question;
use App\Models\SA\Reference\KYC\RiskProfiles\Answer;
use App\Models\SA\Reference\KYC\Nationality;
use App\Models\SA\Reference\KYC\DocumentType; 
use App\Models\Users\User; 
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Administrative\Broker\MessagesController;
//use Symfony\Component\HttpKernel\Profiler\Profile;

class KycRegistrationController extends AppController
{
       /**
        * Save registration data
        *
        * @param Request $request
        * @param mixed $id
        * @return JsonResponse
        */

       public function savedata1(Request $request, $id = null): JsonResponse
       {  
           try
           {
               // mencari profile_id
               $investor_id = $id;
               // insert ke Table u_investors
               $this->SaveInvestors($request, $id);
               // insert loop ke  mode questionare
               $this->saveQuestionare2($request, $id);
               // Insert ke table u_investors_addresses
               $this->SaveInvestorContacts($request, $id);
               
               return $this->app_response('Profile_id', $investor_id);
           }
           catch (Exception $e)
           {
               return $this->app_catch($e);
           }
       }

       public function Cdate($var)
       {
              $date = str_replace('/', '-', $var);
              return date('Y-m-d', strtotime($date));
       }

       public function SaveInvestors(Request $request, $investor_id)
       {
              // Table u_investors
              $investor_data = Investor::where('investor_id', $investor_id)->first();
              Investor::where('investor_id', $investor_id)->update(
                     [
                            // 'doctype_id'                => isset($request->doctype_id) ? $request->doctype_id : $investor_data->doctype_id,
                            'identity_no'               => isset($request->identity_no) ? $request->identity_no : $investor_data->identity_no,
                            'identity_expired_date'     => isset($request->identity_expired_date) ? $this->Cdate($request->identity_expired_date) : $investor_data->identity_expired_date,
                            'date_of_birth'             => isset($request->date_of_birth) ? $this->Cdate($request->date_of_birth) : $investor_data->date_of_birth,
                            'tax_no'                    => isset($request->tax_no) ? $request->tax_no : $investor_data->tax_no,
                            'cif'                       => isset($request->cif) ? $request->cif : $investor_data->cif,
                            'fullname'                  => isset($request->fullname) ? $request->fullname : $investor_data->fullname,
                            'place_of_birth'            => isset($request->place_of_birth) ? $request->place_of_birth  : $investor_data->place_of_birth,
                            'date_of_birth'             => isset($request->date_of_birth) ? $request->date_of_birth  : $investor_data->date_of_birth,
                            'gender_id'                 => isset($request->gender_id) ? $request->gender_id : $investor_data->gender_id,
                            'nationality_id'            => isset($request->nationality_id) ? $request->nationality_id : $investor_data->nationality_id,
                            'religion_id'               => isset($request->religion_id) ? $request->religion_id : $investor_data->religion_id,
                            'phone'                     => isset($request->phone) ? $request->phone : $investor_data->phone,
                            'mobile_phone'              => isset($request->mobile_phone) ? $request->mobile_phone : $investor_data->mobile_phone,
                            'fax'                       => isset($request->fax) ? $request->fax : $investor_data->fax,
                            'email'                     => isset($request->email) ? $request->email : $investor_data->email,
                            'education_id'              => isset($request->education_id) ? $request->education_id : $investor_data->education_id,
                            'fund_source_id'            => isset($request->fund_source_id) ? $request->fund_source_id : $investor_data->fund_source_id,
                            'earning_id'                => isset($request->earning_id) ? $request->earning_id : $investor_data->earning_id,
                            'investobj_id'              => isset($request->investobj_id) ? $request->investobj_id : $investor_data->investobj_id,
                     ]
              );
       }
       public function SaveInvestorContacts(Request $request, $investor_id)
       {
              $checkHasDomicileAddress    = InvestorAddress::where('investor_id', $investor_id)->where('address_type', 'Home')->first();
              $checkHasMailingAddress     = InvestorAddress::where('investor_id', $investor_id)->where('address_type', 'Mailing')->first();
              if ($request->domicile_type === 'new') {
                     if ($checkHasDomicileAddress !== null) {
                            $this->saveNewDomicile($request, $investor_id);
                     }
                     if ($checkHasMailingAddress === null) {
                            $this->createNewDomicileAddress($request, $investor_id);
                     }
              }
              if ($request->domicile_type === 'same') {
                     $this->saveAsDomicileIdCard($request, $investor_id);
              }
              if ($request->mailing_type === 'ktp') {
                     $this->saveMailingAsIdCard($request, $investor_id);
              }
              if ($request->mailing_type === 'domicile_new') {
                     if ($checkHasMailingAddress !== null) {
                            $this->saveMailingAsDomicileNew($request, $investor_id);
                     }
                     if ($checkHasMailingAddress === null) {
                            $this->createNewMailingAddress($request, $investor_id);
                     }
              }
              if ($request->mailing_type === 'domicile_ktp') {
                     $this->saveMailingAsDomicileIdCard($request, $investor_id);
              }
              if ($request->mailing_type === 'new') {
                     $this->saveNewFreshMailingAddress($request, $investor_id); // commit -nya gak masuk
              }
       }
       public function createNewDomicileAddress(Request $request, $investor_id)
       {
              DB::table('u_investors_addresses')->insert([
                     'investor_id'        => isset($investor_id) ? $investor_id : Auth::id(),
                     'address'            => $request->complete_address,
                     'address_type'       => 'Home',
                     'province_id'        => static::queryProvinces($request->province_code),
                     'city_id'            => static::queryCities($request->city),
                     'subdistrict_id'     => static::querySubDistricts($request->district),
                     'postal_code'        =>  $request->postal_code,
                     'created_host'       => $request->getClientIp(),
                     'updated_host'       => null,
                     'created_by'         => 'Investor:' . Auth::id() . ':' . Auth::user()->fullname,
                     'updated_by'         => null,
                     'created_at'         => Carbon::now(),
                     'updated_at'         => Carbon::now(),
              ]);
       }

       public function createNewMailingAddress(Request $request, $investor_id)
       {
              DB::table('u_investors_addresses')->insert([
                     'investor_id'        => isset($investor_id) ? $investor_id : Auth::id(),
                     'address'            => $request->mailing_address_detail_domicile,
                     'address_type'       => 'Mailing',
                     'province_id'        => static::queryProvinces($request->mailing_province_domicile),
                     'city_id'            => static::queryCities($request->mailing_city_domicile),
                     'subdistrict_id'     => static::querySubDistricts($request->mailing_district_domicile),
                     'postal_code'        => $request->mailing_postal_code_domicile,
                     'created_host'       => $request->getClientIp(),
                     'updated_host'       => null,
                     'created_by'         => 'Investor:' . Auth::id() . ':' . Auth::user()->fullname,
                     'updated_by'         => null,
                     'created_at'         => Carbon::now(),
                     'updated_at'         => Carbon::now(),
              ]);
       }

       public function saveNewDomicile(Request $request, $investor_id)
       {
              $address = static::investorAddressData($investor_id, 'Home');
              $address->update([
                     'address'            => $request->complete_address,
                     'address_type'       => 'Home',
                     'province_id'        => static::queryProvinces($request->province_code),
                     'city_id'            => static::queryCities($request->city),
                     'subdistrict_id'     => static::querySubDistricts($request->district),
                     'postal_code'        =>  $request->postal_code
              ]);
       }
       public function saveAsDomicileIdCard(Request $request, $investor_id)
       {
              $address = static::investorAddressData($investor_id, 'Home');
              $address->update([
                     'address'            => $request->domicile_address_detail,
                     'address_type'       => 'Home',
                     'province_id'        => $request->domicile_province,
                     'city_id'            => $request->domicile_city,
                     'subdistrict_id'     => $request->domicile_district,
                     'postal_code'        => $request->domicile_postal_code
              ]);
       }

       public function saveMailingAsIdCard(Request $request, $investor_id)
       {
              $address = static::investorAddressData($investor_id, 'Mailing');
              $address->update([
                     'address'            => $request->mailing_address_detail,
                     'address_type'       => 'Mailing',
                     'province_id'        => $request->mailing_province,
                     'city_id'            => $request->mailing_city,
                     'subdistrict_id'     => $request->mailing_district,
                     'postal_code'        => $request->mailing_postal_code
              ]);
       }
       public function saveMailingAsDomicileIdCard(Request $request, $investor_id)
       {
              $address = static::investorAddressData($investor_id, 'Mailing');
              $address->update([
                     'address'            => $request->mailing_address_detail,
                     'address_type'       => 'Mailing',
                     'province_id'        => $request->mailing_province,
                     'city_id'            => $request->mailing_city,
                     'subdistrict_id'     => $request->mailing_district,
                     'postal_code'        => $request->mailing_postal_code
              ]);
       }
       public function saveMailingAsDomicileNew(Request $request, $investor_id)
       {
              $address = static::investorAddressData($investor_id, 'Mailing');
              $address->update([
                     'address'            => $request->mailing_address_detail_domicile,
                     'address_type'       => 'Mailing',
                     'province_id'        => static::queryProvinces($request->mailing_province_domicile),
                     'city_id'            => static::queryCities($request->mailing_city_domicile),
                     'subdistrict_id'     => static::querySubDistricts($request->mailing_district_domicile),
                     'postal_code'        => $request->mailing_postal_code_domicile,
              ]);
       }
       public function saveNewFreshMailingAddress(Request $request, $investor_id)
       {
              $address = static::investorAddressData($investor_id, 'Mailing');
              $address->update([
                     'address'            => $request->mailing_address_detail_new,
                     'address_type'       => 'Mailing',
                     'province_id'        => static::queryProvinces($request->mailing_province_new),
                     'city_id'            => static::queryCities($request->mailing_city_new),
                     'subdistrict_id'     => static::querySubDistricts($request->mailing_district_new),
                     'postal_code'        => $request->mailing_postal_code_new,
              ]);
       }

       public function saveQuestionareTest(Request $request, $id)
       {
              // return $this->app_response('Hello');
              try {
                     $i = 0;
                     $initial_p_key = 529;
                     $total_question = $request->input('totalquestion');
                     for ($i = 0; $i < $total_question; $i++) {
                            $q = 'question' . $i;
                            $question_id = $request->input($q);
                            $question_id = (int) $question_id;

                            $ans = 'answer' . $i;
                            $answer_id = $request->input($ans);
                            $answer_id = (int) $answer_id;
                            $increment = DB::table('u_investors_questions')->orderBy('investor_question_id', 'desc')->first();
                            $increment = $increment->investor_question_id;
                            $increment++;
                            DB::table('u_investors_questions')->insert([
                                   'investor_question_id' => $increment,
                                   'investor_id' => $id,
                                   'question_id' => $question_id,
                                   'answer_id'   => $answer_id,
                                   'profile_id'  => 2,
                                   'repetition'  => 1,
                                   'created_by'  => 'investor:' . Auth::id() . ':' . Auth::user()->fullname,
                                   'created_host'       => $request->ip(),
                            ]);
                     }
                     return $this->app_response('success isi data baru');
              } catch (Exception $e) {
                     return $this->app_catch($e);
              }
       }

    public function saveQuestionare2(Request $request, $id)
    {
        try
        {  
            // coba method ini di lokal mas
            // nanti kalo ini bisa ke save coba uncomment yang question _id sama answer id buat nyimpen data answer sama question nya
            // wes tak coba pakai cara looping / insert data nya satu satu ga bisa
            $investor       = Investor::where([['investor_id', $id], ['is_active', 'Yes'], ['valid_account', 'Yes']])->first();
            // question_id 4 diubah answer dari 11 ke 98
            $i              = 0;
            $total_question = $request->input('totalquestion');
            $risk           = $this->risk_profile($request, true);

            Investor::where([['investor_id', $id], ['is_active', 'Yes'], ['valid_account', 'Yes']])->update(['sid' => null, 'profile_id' => $risk->profile_id, 'profile_effective_date' => $this->app_date(), 'profile_expired_date' => date('Y-m-d', strtotime('+2 year '. $this->app_date()))]);
            
            //        
            //code...
            for ($i = 0; $i < $total_question; $i++)
            {
                $q              = 'question' . $i;
                $question_id    = $request->input($q);
                $ans            = 'answer' . $i;
                $answer_id      = $request->input($ans);
                $check          = Question::where(['investor_id' => $id, 'question_id' => $question_id, 'profile_id' => Auth::user()->profile_id])->first();
                     //                $check = DB::table('u_investors_questions')->where(['investor_id' => $id, 'question_id' => $question_id, 'profile_id' => $investor->profile_id])->first();
                if ($check)
                {
                    DB::table('u_investors_questions')
                        ->where(['investor_id' => $id, 'profile_id' => Auth::user()->profile_id, 'question_id' => $question_id])
                        ->update([
                            'answer_id'     => $answer_id,
                            'updated_at'    => Carbon::now('Asia/Jakarta'),
                            'updated_host'  => $request->getClientIp(),
                            'updated_by'    => 'investor:' . Auth::id() . ':' . Auth::user()->fullname
                        ]);
                }
                else
                {
                    // $increment = DB::table('u_investors_questions')->orderBy('investor_question_id', 'desc')->first();
                    // // $increment = $increment->investor_question_id;
                    // $increment++;
                    DB::table('u_investors_questions')->insert([
                        // 'investor_question_id' => $increment, di matiin kalo kondisi database fresh alias kosong
                        'investor_id'   => $id,
                        'question_id'   => $risk->question[$i]['question_id'],
                        'answer_id'     => $risk->question[$i]['answer_id'],
                        'answer_score'  => $risk->question[$i]['answer_score'],
                        'profile_id'    => $risk->profile_id,
                        'repetition'    => 1,
                        'created_by'    => 'investor:' . Auth::id() . ':' . Auth::user()->fullname,
                        'created_host'  => $request->getClientIp(),
                        'created_at'    => Carbon::now('Asia/Jakarta'),
                        'updated_at'    => Carbon::now('Asia/Jakarta')
                    ]);
                }

                     // bikin station cek data apakah sudah ada data jawaban dari investor
            }
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }



       // public function saveQuestionare(Request $request, $id)
       // {
       //        // coba method ini di lokal mas
       //        // nanti kalo ini bisa ke save coba uncomment yang question _id sama answer id buat nyimpen data answer sama question nya
       //        // wes tak coba pakai cara looping / insert data nya satu satu ga bisa
       //        $investor            = Investor::where('investor_id', $id)->first();
       //        // question_id 4 diubah answer dari 11 ke 98
       //        $i = 0;
       //        $total_question = $request->input('totalquestion');

       //        for ($i; $i < $total_question; $i++) {
       //               $q = 'question' . $i;
       //               $question_id = $request->input($q);
       //               $question_id = (int)$question_id;

       //               $ans = 'answer' . $i;
       //               $answer = $request->input($ans);
       //               $answer = (int)$answer;



       //               $check = DB::table('u_investors_questions')->where(['investor_id' => $id, 'question_id' => $question_id, 'profile_id' => 2])->first();
       //               if ($check) {
       //                      DB::table('u_investors_questions')
       //                             ->where(['investor_id' => $id, 'profile_id' => 2, 'question_id' => $question_id])
       //                             ->update(['answer_id' => $answer]);
       //               } else {
       //                      DB::table('u_investors_questions')->insert([
       //                             'investor_question_id' => $initial_p_key++,
       //                             'investor_id' => $id,
       //                             'question_id' => $question_id,
       //                             'answer_id'   => $answer,
       //                             'profile_id'  => 2,
       //                             'repetition'  => 1,
       //                             'created_by'  => 'investor:' . Auth::id() . ':' . Auth::user()->fullname,
       //                             'created_host'       => $request->ip(),
       //                      ]);
       //               }
       //        }


       //        $i = 0;
       //        //              for ($i; $i < $total_question; $i++) {
       //        //                     $q = 'question' . $i;
       //        //                     $question_id = $request->input($q); $request->input('question0');
       //        //                     // Pertama tentukan dulu apakah di-Create atau di Update
       //        //                     $check = Question::where(['investor_id' => $id, 'question_id' => $question_id,'profile' =>$investor['profile_id']])->first();
       //        //                     if ($check !== null) {
       //        //                            $ans = 'answer' . $i;
       //        //                            $answer = $request->input($ans);
       //        //                            DB::table('u_investors_questions')
       //        //                                ->where(['investor_id' => $id, 'question_id' => $question_id,'profile' =>$investor['profile_id']])
       //        //                                ->update(['answer_id' => $answer]);
       //        ////                            Question::where(['investor_id' => $id, 'question_id' => $question_id,'profile' =>$investor['profile_id']])
       //        //
       //        //                     } else {
       //        //                            $ans = 'answer' . $i;
       //        //                            $answer = $request->input($ans);
       //        //                            DB::table('u_investors_questions')->insert(
       //        //                                ['investor_id' => $id, 'profile_id' => $investor->profile_id, 'question_id' => $question_id, 'answer_id' => $answer]
       //        //                            );
       //        ////                            Question::create(['investor_id' => $id, 'profile_id' => $investor->profile_id, 'question_id' => $question_id, 'answer_id' => $answer]);
       //        //                     }
       //        //              }
       // }

       public function check_risk_profile1(Request $request)
       {
              try {
                     return $this->app_response('Risk Profile', ['desc' => 'dummy']);
              } catch (\Exception $e) {
                     return $this->app_catch($e);
              }
       }

       public function check_risk_profile(Request $request)
       {
              $total_question =  $request->input('length');
              $datapost = $request->input('datapost');
              try {
                     $inv_qst    = [];
                     $profile    = $profile_id = $desc = '';
                     $score      = 0;
                     $n          = 1;
                     $i = 0;

                     for ($i; $i < $total_question; $i++) {
                            $question_id = $datapost[$i]['question_id'];
                            $answer_id = $datapost[$i]['answer_id'];
                            $ans    = Answer::find($answer_id);
                            $score  = !empty($ans->answer_score) ? $score + $ans->answer_score : 0;
                            if (!empty($ans->answer_id)) {
                                   $inv_qst[]  = ['question_id' => $question_id, 'answer_id' => $ans->answer_id, 'answer_score' => $ans->answer_score];
                            }


                            //                            $risk = DB::table('m_risk_profiles')->where('is_active', 'Yes')->orderBy('sequence_to')->get() ;
                            $risk = Profile::where('is_active', 'Yes')->orderBy('sequence_to')->get();
                            foreach ($risk as $rk) {
                                   if ($score >= $rk->min && $score <= $rk->max) {
                                          $profile        = $rk->profile_name;
                                          $profile_id     = $rk->profile_id;
                                          $desc           = $rk->description;
                                          $profile_image  = $rk->profile_image;
                                          break;
                                   }
                                   $n++;
                            }
                     }
                     //                  return $this->app_response('Risk Profile', ['score' =>  $score,'risk'=>$risk]);
                     return $this->app_response('Risk Profile', ['desc' => $desc, 'profile' => $profile, 'profile_image' => $profile_image, 'seq' => $n, 'total_risk' => $risk->count()]);
              } catch (\Exception $e) {
                     return $this->app_catch($e);
              }
       }


       /**
        * Get the SID Code form authenticated user.
        *
        * @return JsonResponse
        */
       public function getSid(): JsonResponse
       {
              try {
                     $investor     = Investor::where([['investor_id', Auth::id()],['is_active', 'Yes'], ['valid_account', 'Yes']])->select('sid', 'email', 'investor_id', 'wms_status', 'wms_message')->first();
                     return $this->app_response("Success retrieve Sid for authenticated user", $investor);
              } catch (Exception $e) {
                     return $this->app_catch($e);
              }
       }



       /**
        * get endpoint for wms authentication.
        *
        * @return string The uri for authenticating into wms api.
        */
       private static function getWmsAuthEndpoint()
       {
  	      //return 'http://localhost:6001/v1/Auth';		
          //return 'http://192.168.26.30/DEV/CPM_INT_RDO/v1/Auth';
          $data =  DB::table('c_api')->where([['api_name','API_WMS'],['is_active','Yes']])->first();

          if(!empty($data)) {
            $slug =  $data->slug.'Auth';
          } else {
            $slug = null;
          }

          return $slug;
       }  

       /**
        * Request body to authenticate into wms.
        *
        * @return array
        */
       private static function wmsAuthData()
       {

              $data =  DB::table('c_api')->where([['api_name','API_WMS'],['is_active','Yes']])->first();

              return [
                     'username'    => !empty($data->username) ? $data->username : null,
                     'password'    => !empty($data->password) ? $data->password : null
              ];

              /*
              return [
                     'username'    => "CPM",
                     'password'    => "cpm.praisindo2020!#"
              ];
              */
       }

       /**
        * Get authentication response from WMS auth to grab token_data that used in make SID Request.
        *
        * @return array
        */
       public function authenticateToWms()
       {
              $response = Http::acceptJson()->post(
                     static::getWmsAuthEndpoint(),
                     static::wmsAuthData()
              );
              return $response->json();
       }
       /**
        * Generate request body to send it into wms.
        *
        * @param Request $request
        * @return array
        */
       public function generateInvestorData($id): array
       {
              try {
                    /*
                     $investor = DB::table('u_investors')->select([
                            'u_investors.cif',
                            'u_investors.identity_no',
                            'u_investors.phone',
                            'u_investors.mobile_phone',
                            'u_investors.email'
                     ])
                    ->leftjoin('m_fund_sources', 'u_investors.fund_source_id', '=', 'm_fund_sources.fund_source_id')
                    ->leftjoin('m_investment_objectives', 'u_investors.investobj_id', '=', 'm_investment_objectives.investobj_id')
                    ->leftjoin('m_earnings', 'u_investors.earning_id', '=', 'm_earnings.earning_id')
                    ->addSelect('m_investment_objectives.ext_code AS investmentObjectiveCode')
                    ->addSelect('m_fund_sources.ext_code AS sourceOfIncomeCode')
                    ->addSelect('m_earnings.ext_code AS yearlyIncomeCode')
                    ->where([['u_investors.investor_id', '=', $id], ['u_investors.is_active', '=', 'Yes'], ['u_investors.valid_account', '=', 'Yes'],['m_fund_sources.is_active', '=', 'Yes'],['m_earnings.is_active', '=', 'Yes']])
                    ->first();
                    */

                     $investor = DB::table('u_investors')->select([
                            'u_investors.cif',
                            'u_investors.identity_no',
                            'u_investors.phone',
                            'u_investors.mobile_phone',
                            'u_investors.email'
                     ])
                    ->leftjoin('m_fund_sources', 'u_investors.fund_source_id', '=', 'm_fund_sources.fund_source_id')
                    ->leftjoin('m_investment_objectives', 'u_investors.investobj_id', '=', 'm_investment_objectives.investobj_id')
                    ->leftjoin('m_earnings','u_investors.earning_id', '=', 'm_earnings.earning_id')
                    ->addSelect('m_investment_objectives.ext_code AS investmentObjectiveCode')
                    ->addSelect('m_fund_sources.ext_code AS sourceOfIncomeCode')
                    ->addSelect('m_earnings.ext_code AS yearlyIncomeCode')
                    ->where([['u_investors.investor_id', '=', $id], ['u_investors.is_active', '=', 'Yes'], ['u_investors.valid_account', '=', 'Yes']])
                    ->first();

                    //$investor = Investor::where('investor_id', $id)->first();
                    return [
                            "cif"                       => (string) !empty($investor->cif) ? $investor->cif : null,
                            "identityNumber"            => (string) !empty($investor->identity_no) ? $investor->identity_no : null,
                            "sourceOfIncomeCode"        => (string) !empty($investor->sourceOfIncomeCode) ? $investor->sourceOfIncomeCode: '1' , # nilai dari tabel relasi ini dari ketentuan mas riza di grup, nama kolonya sama jadi gimana solusinya yak?
                            "yearlyIncomeCode"          => (string) !empty($investor->yearlyIncomeCode) ? $investor->yearlyIncomeCode : '1', # nilai dari tabel relasi ini dari ketentuan mas riza di grup, nama kolonya sama jadi gimana solusinya yak?
                            "investmentObjectiveCode"   => (string) !empty($investor->investmentObjectiveCode) ? $investor->investmentObjectiveCode : '1', # nilai dari tabel relasi ini dari ketentuan mas riza di grup, nama kolonya sama jadi gimana solusinya yak?
                            "phone"                     => (string) !empty($investor->phone) ? $investor->phone : null,
                            "mobile"                    => (string) !empty($investor->mobile_phone) ? $investor->mobile_phone : null,
                            "email"                     => (string) !empty($investor->email) ? $investor->email : null
                     ];

              } catch (Exception $e) {
                     return $e;
              }
       }

       /**
        * generate URL for send request to Make SID in wms.
        *
        * @return string
        */
       private static function RequestSidUrl(): string
       {
         //return 'http://localhost:6001/v1/Investor';
         //return 'http://192.168.26.30/DEV/CPM_INT_RDO/v1/Investor';

        $data =  DB::table('c_api')->where([['api_name','API_WMS'],['is_active','Yes']])->first();

        if(!empty($data)) {
          $slug =  $data->slug.'Investor';
        } else {
          $slug = null;
        }

        return $slug;              
       }
       /**
        * Make request SID to WMS.
        *
        * @return array
        */
       //public function makeRequestSid(Request $request): array
       public function makeRequestSid(Request $request)
       { 
             try {
                    /*
                    $data = [
                            "cif"                       => '81884583',
                            "identityNumber"            => '1671042308560006',
                            "sourceOfIncomeCode"        => '1', # nilai dari tabel relasi ini dari ketentuan mas riza di grup, nama kolonya sama jadi gimana solusinya yak?
                            "investmentObjectiveCode"   => '1', # nilai dari tabel relasi ini dari ketentuan mas riza di grup, nama kolonya sama jadi gimana solusinya yak?
                            "yearlyIncomeCode"			=> '1',
                            "phone"                     => '',
                            "mobile"                    => '62811784842',
                            "email"                     => 'faradhillah29@gmail'
                     ];
                     */
                     $data = $this->generateInvestorData($request->investor_id);
              	     $response = Http::acceptJson()->withToken(
                            $this->authenticateToWms()['data'] # array access to grab token / bearer_token from given respo
		                 )->post(
                            static::RequestSidUrl(),
                            $data, # mendapatkan data1 
                            //investor yang di gunakan untuk request SID di wms.
                     );

                     $this->saveFlag($request->investor_id, $response->json()); # save response to given flag in u investors from wms status and message

                     return $response->json(); # kembalikan response ke CPM buat mas rian nentuin banner nya tanpil atau tidak .
              } catch (Exception $e) {
                     return array($this->app_catch($e));
              }
       }

       //private function saveFlag($id, array $wmsResponse)
       private function saveFlag($id, $wmsResponse)
       {
              /*
              Investor::where('investor_id', $id)->update([
                     'wms_status' => (int) $wmsResponse['code'],
                     'wms_message' => $wmsResponse['message']
              ]);
              */  
	  if($wmsResponse['code'] == '200')
          {
            //$sendEmailNotification = new Administrative\Broker\MessagesController;
            //$api_email = $sendEmailNotification->request_sid($id);
            $sendEmailNotification = new MessagesController;
            $api_email = $sendEmailNotification->request_sid($id);            
          }

              Investor::where('investor_id', $id)->update([
                     'wms_status' => (int) $wmsResponse['code'],
                     'wms_message' => $wmsResponse['message']
              ]);
       }


       public function termscondition()
       {
              try {
                     $Mterms = DB::table('c_terms_conditions')->where('terms_code', 'RegSIDMF')->first();
                     return $this->app_response('Bank Accounts', $Mterms);
              } catch (\Exception $e) {
                     return $this->app_catch($e);
              }
       }
       /**
        * Get the address list for complete new registration user address field
        *
        * @param Request $request
        * @return JsonResponse
        */
       public function getAddressList(Request $request)
       {
              $provinceCode = $request->query('province_code');
              $cityCode     = $request->query('city_code');
              try {
                     if ($provinceCode === null && $cityCode === null) {
                            return $this->getProvinceList();
                     }
                     if (isset($provinceCode) && $cityCode === null) {
                            return $this->getCityList($provinceCode, $cityCode);
                     }
                     if (isset($provinceCode) && isset($cityCode)) {
                            return $this->getDistrictList($provinceCode, $cityCode);
                     }
              } catch (Exception $e) {
                     return $this->app_catch($e);
              }
       }
       /**
        * Get the list of all available province
        *
        * @return JsonResponse
        */
       public function getProvinceList()
       {
              $data = DB::table('m_regions')->where('region_type', 'Provinsi')->get();
              return $this->app_response('Province List, Found [' . count($data) . '] province data', $data);
       }
       /**
        * Get the available cities of given province
        *
        * @param mixed $provinceCode
        * @param mixed $cityCode
        * @return JsonResponse
        */
       public function getCityList($provinceCode, $cityCode)
       {
              if (!isset($provinceCode) || $provinceCode === '') {
                     throw new Exception("Error Processing Request, province code is required", 1);
              } elseif (isset($provinceCode) && $cityCode === null) {
                     $provinceName = DB::table('m_regions')->where([['region_code', $provinceCode], ['is_active', 'Yes']])->first();
                     $data         = DB::table('m_regions')->where([['parent_code', $provinceCode], ['is_active', 'Yes']])->get();
                     if (count($data) <= 0) {
                            throw new Exception("Error Processing Request, The province code : ['$provinceCode']['Undefined province'] wasnt associated with any cities", 1);
                     }
                     return $this->app_response('City List for province code [' . $provinceCode . '] [Provinsi : ' . $provinceName->region_name . ']', $data);
              }
       }
       /**
        * Get available districts for given city code
        *
        * @param mixed $provinceCode
        * @param mixed $cityCode
        * @return jsonResponse
        */
       public function getDistrictList($provinceCode, $cityCode)
       {
              if (!isset($provinceCode)) {
                     throw new Exception("Error Processing Request, The province code is required", 1);
              } elseif (isset($provinceCode) && isset($cityCode)) {
                     if (strlen($cityCode <= 0)) {
                            throw new Exception("Error Processing Request, city code must be provided", 1);
                     }
                     $cityName     = DB::table('m_regions')->where([['region_code', $cityCode], ['is_active', 'Yes']])->first();
                     $data         = DB::table('m_regions')->where([['parent_code', $cityCode], ['is_active', 'Yes']])->get();
                     if (count($data) <= 0) {
                            throw new Exception("Error Processing Request, The city code : ['$cityCode']['Undefined city'] wasnt associated with any districts", 1);
                     }
                     return $this->app_response('District List for city code [' . $cityCode . '][Kota/Kabupaten : ' . $cityName->region_name . ']', $data);
              }
       }
       /**
        * Grab investor address data to update
        *
        * @return Model
        */
       public static function investorAddressData($investorId, $addresType): Model
       {
              return InvestorAddress::where('investor_id', $investorId)->where('address_type', $addresType)->first();
       }
       /**
        * Query to get region id with based on province code
        *
        * @param mixed $provinceCode
        * @return mixed
        */
       public static function queryProvinces($provinceCode)
       {
              $province = DB::table('m_regions')->where('region_type', 'Provinsi')->where('region_code', $provinceCode)->first();
              return $province->region_id;
       }
       /**
        * Query to get region id with based on city code
        *
        * @param mixed $provinceCode
        * @return mixed
        */
       public static function queryCities($cityCode)
       {
              $city = DB::table('m_regions')->where('region_type', 'Kota / Kab.')->where('region_code', $cityCode)->first();
              return $city->region_id;
       }
       /**
        * Query to get region id with based on sub district code
        *
        * @param mixed $provinceCode
        * @return mixed
        */
       public static function querySubDistricts($subDistrictCode)
       {
              $subDistrict = DB::table('m_regions')->where('region_type', 'Kecamatan')->where('region_code', $subDistrictCode)->first();
              return $subDistrict->region_id;
       }
       public function getInvestorAddressData($id = null)
       {
              try {
                     $data['KTP']       = $this->getIDCardAddress($id);
                     $data['Domicile']  = static::getDomicileAddress($id);
                     $data['Mailing']   = static::getMailingAddress($id);
                     return $this->app_response('investor address data list', $data);
              } catch (Exception $e) {
                     return $this->app_catch($e);
              }
       }
       public static function queryAddress($id, $addressType)
       {
              return Db::table('u_investors_addresses')
                     ->where('investor_id', $id)
                     ->where('address_type', $addressType)
		    ->where('is_active', 'Yes')
                     ->select([
                            'investor_address_id',
                            'investor_id',
                            'province_id',
                            'city_id',
                            'subdistrict_id',
                            'postal_code',
                            'address',
                            'address_type'
                     ])->first();
       }
       public static function getRegionAttribute($adressId)
       {
              return Db::table('m_regions')->where('region_id', $adressId)->first();
       }
       public function getIDCardAddress($id)
       {
           $idCardAddressData         = static::queryAddress($id, 'KTP');
            if ($idCardAddressData === null) {
                   return null;
            }
            return [
                   'address_detail'     => $idCardAddressData,
                   'province_name'      => isset($idCardAddressData->province_id) ? static::getRegionAttribute($idCardAddressData->province_id)->region_name : null,
                   'city_name'          => isset($idCardAddressData->city_id) ? static::getRegionAttribute($idCardAddressData->city_id)->region_name : null,
                   'subdistrict_name'   => isset($idCardAddressData->subdistrict_id) ? static::getRegionAttribute($idCardAddressData->subdistrict_id)->region_name : null,
            ];

          $address    = $this->api_ws(['sn' => 'InvestorAddress', 'val' => [Auth::user()->cif]])->original['data'];
          return $address;
          $idCardAddressData = array();
          if (!empty($api))
          {
              foreach ($api as $a)
              {  
                  
                  $province       = !empty($a->province) ? $a->province : null;
                  $city           = !empty($a->city) ? $a->city : null;
                  $subdistrict    = !empty($a->subDistrict) ? $a->subDistrict : null;
                  $postal         = !empty($a->postalCode) ? $a->postalCode : null;
                  $address        = implode(', ', $addr);

                  $idCardAddressData['investor_address_id'] =  !empty($a->investorId) ? $a->investorId : '';
                  $idCardAddressData['investor_id'] = !empty($a->investorId) ? $a->investorId : ''; 
                  $idCardAddressData['city_id'] = null;
                  $idCardAddressData['subdistrict_id'] = null;
                  $idCardAddressData['postal_code'] = !empty($a->postalCode) ? $a->postalCode : '';
                  $idCardAddressData['address'] = !empty($a->address1) ? $a->address1 : '';
                  $idCardAddressData['address_type'] = 'KTP';

                  if ($a->addressType == 'KTP')
                      break;
              }
          }
          /*
          return $idCardAddressData;
          return [
                 'address_detail'     => $idCardAddressData,
                 'province_name'      => isset($province) ? $province : null,
                 'city_name'          => isset($city) ? $city : null,
                 'subdistrict_name'   => isset($subdistrict) ? $subdistrict : null,
          ];
          */
  
       }
       public static function getDomicileAddress($id)
       {
              $domicileAddressData          = static::queryAddress($id, 'Home');
              if ($domicileAddressData === null) {
                     return null;
              }
              return [
                     'address_detail'     => $domicileAddressData,
                     'province_name'      => isset($domicileAddressData->province_id) ? static::getRegionAttribute($domicileAddressData->province_id)->region_name : null,
                     'city_name'          => isset($domicileAddressData->city_id) ? static::getRegionAttribute($domicileAddressData->city_id)->region_name : null,
                     'subdistrict_name'   => isset($domicileAddressData->subdistrict_id) ? static::getRegionAttribute($domicileAddressData->subdistrict_id)->region_name : null,
              ];
       }
       public static function getMailingAddress($id)
       {
              $mailingAddressData          = static::queryAddress($id, 'Mailing');
              if ($mailingAddressData === null) {
                     return null;
              }
              return [
                     'address_detail'     => $mailingAddressData,
                     'province_name'      => isset($mailingAddressData->province_id) ? static::getRegionAttribute($mailingAddressData->province_id)->region_name : null,
                     'city_name'          => isset($mailingAddressData->province_id) ? static::getRegionAttribute($mailingAddressData->city_id)->region_name : null,
                     'subdistrict_name'   => isset($mailingAddressData->province_id) ? static::getRegionAttribute($mailingAddressData->subdistrict_id)->region_name : null,
              ];
       }

    public function risk_profile(Request $request)
    {
        try
        {
            $inv_qst    = [];
            $profile    = $profile_id = $desc = '';
            $score      = 0;
            $n          = 1;
            $arrQst     = [];
            $totQst     = $request->totalquestion;
            for ($i=0; $i < $totQst; $i++)
            {
                $ans            = 'answer' . $i;
                $answer_id      = $request->input($ans);
                $ans            = Answer::select('answer_id', 'answer_score', 'm_profile_answers.sequence_to as answer_no', 'b.question_id', 'b.sequence_to as question_no')
                                ->join('m_profile_questions as b', 'm_profile_answers.question_id', 'b.question_id')
                                ->where([['answer_id', $answer_id], ['m_profile_answers.is_active', 'Yes'], ['b.is_active', 'Yes']])->first();
                $score          = !empty($ans->answer_score) ? $score + $ans->answer_score : 0;
                if (!empty($ans->answer_id))
                {
                    $arrQst[]   = ['no' => strval($ans->question_no), 'answer' => strval($ans->answer_no)];
                    $inv_qst[]  = [
                        'question_id'   => $ans->question_id,
                        'question_no'   => $ans->question_no,
                        'answer_id'     => $ans->answer_id,
                        'answer_no'     => $ans->answer_no,
                        'answer_score'  => $ans->answer_score
                    ]; 
                }
            }
            
            $api = $this->api_ws(['sn' => 'RiskProfileWMS', 'val' => [Auth::user()->cif, true, $arrQst]])->original['data'];
            if (!empty($api))
            {
                $risk       = Profile::where([['ext_code', $api->profileId], ['is_active', 'Yes']])->first();
                $profile    = !empty($risk->profile_name) ? $risk->profile_name : '';
                $profile_id = !empty($risk->profile_id) ? $risk->profile_id : '1';
                //$profile_id = 2;
                $desc       = !empty($risk->description) ? $risk->description : '';
            }
            
            if (empty($profile_id))
            {
                $risk = Profile::where('is_active', 'Yes')->orderBy('sequence_to');
                foreach ($risk->get() as $rk)
                {
                    if ($score >= $rk->min && $score <= $rk->max)
                    {
                        $profile        = $rk->profile_name;
                        $profile_id     = $rk->profile_id;
                        //$profile_id     = 4;
                        $desc           = $rk->description;
                        break;
                    }
                    $n++;
                }
            }

            return (object) ['profile_id' => $profile_id, 'question' => $inv_qst];
            //return !$save ? $this->app_response('Risk Profile', ['desc' => $desc, 'profile' => $profile, 'seq' => $n, 'total_risk' => $risk->count()]) : (object) ['profile_id' => $profile_id, 'question' => $inv_qst];
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function sync_investor() 
    {
        try
        {
            $res        = [];
            $address    = $this->api_ws(['sn' => 'InvestorAddress', 'val' => [Auth::user()->cif]])->original['data'];

            $investor = Investor::select('identity_no')
            ->distinct('identity_no')
            ->where([['is_active', 'Yes'], ['valid_account', 'Yes'],['cif',Auth::user()->cif]])
            ->first();
            $crm    = $this->api_ws(['sn' => 'InvestorCRM', 'val' => [$investor->identity_no]])->original['data'];

            if ($crm!=null)  {
                $dt = Nationality::whereRaw("UPPER(ext_code)='".strtoupper($crm->nationalityCode)."'")->first();
                $nationality_id = ($dt!=null)? $dt->nationality_id : null;

                $dt = DocumentType::whereRaw("UPPER(doctype_code)='".strtoupper($crm->identityType)."'")->first();
                $doctype_id = ($dt!=null)? $dt->doctype_id : null;

                $dt = User::whereRaw("UPPER(user_code)='".strtoupper($crm->salesCode)."'")->first();
                $sales_id =  !empty($crm->salesCode) ? $this->db_row('user_id', ['where' => [['ext_code', $crm->salesCode]]], 'Users\User')->original['data'] : null;

                $data = [   'gender_id'             => !empty($crm->genderCode) ? $this->db_row('gender_id', ['where' => [['ext_code', $crm->genderCode]]], 'SA\Reference\KYC\Gender')->original['data'] : null,
                            'nationality_id'        => $nationality_id, 
                            'marital_id'            => !empty($crm->maritalStatusCode) ? $this->db_row('marital_id', ['where' => [['ext_code', $crm->maritalStatusCode]]], 'SA\Reference\KYC\MaritalStatus')->original['data'] : null,
                            'education_id'          => !empty($crm->academicDegreeCode) ? $this->db_row('education_id', ['where' => [['ext_code', $crm->academicDegreeCode]]], 'SA\Reference\KYC\Education')->original['data'] : null,
                            'occupation_id'         => !empty($crm->occupationCode) ? $this->db_row('occupation_id', ['where' => [['ext_code', $crm->occupationCode]]], 'SA\Reference\KYC\Occupation')->original['data'] : null,
                            'religion_id'           => !empty($crm->religionCode) ? $this->db_row('religion_id', ['where' => [['ext_code', $crm->religionCode]]], 'SA\Reference\KYC\Religion')->original['data'] : null,
                            'fund_source_id'        => !empty($crm->sourceOfIncomeCode) ? $this->db_row('fund_source_id', ['where' => [['ext_code', $crm->sourceOfIncomeCode]]], 'SA\Reference\KYC\FundSource')->original['data'] : null,
                            'earning_id'            => !empty($crm->incomeCode) ? $this->db_row('earning_id', ['where' => [['ext_code', $crm->incomeCode]]], 'SA\Reference\KYC\Earning')->original['data'] : null,
                            'investobj_id'          => !empty($crm->investmentObjectiveCode) ? $this->db_row('investobj_id', ['where' => [['ext_code', $crm->investmentObjectiveCode]]], 'SA\Reference\KYC\InvestmentObjective')->original['data'] : null,
                            'doctype_id'            => $doctype_id, 
                            'sales_id'              => $sales_id,
                            'cif'                   => !empty($crm->cif) ? $crm->cif : null,
                            'fullname'              => !empty($crm->fullname) ? $crm->fullname : null,
                            'place_of_birth'        => !empty($crm->birthPlace) ? $crm->birthPlace: null,
                            'date_of_birth'         => !empty($crm->birthDate) ? $crm->birthDate: null,
                            'identity_expired_date' => !empty($crm->identityExpiredDate) ? $crm->identityExpiredDate : null,
                            'tax_no'                => !empty($crm->taxNumber) ? $crm->taxNumber  : null,
                            'phone'                 => !empty($crm->phone) ? $crm->phone : null,
                            'mobile_phone'          => !empty($crm->mobile) ? $crm->mobile  : null,
                            'company_phone'         => !empty($crm->officePhone) ? $crm->officePhone : null,
                            'fax'                   => !empty($crm->fax) ? $crm->fax : null
                ];
                if ($sales_id != 0) $data['sales_id'] = $sales_id;
                Investor::where('cif', Auth::user()->cif)->update($data);
                $res[] = $data;
            } 

            if (!empty($address))
            {
                foreach ($address as $addr)
                {
                    $prv        = !empty($addr->provinceCode) ? $this->db_row('region_id', ['where' => [['region_code', $addr->provinceCode]]], 'SA\Reference\KYC\Region')->original['data'] : null;
                    $city       = !empty($addr->cityCode) ? $this->db_row('region_id', ['where' => [['region_code', $addr->cityCode]]], 'SA\Reference\KYC\Region')->original['data'] : null;
                    $district   = !empty($addr->subDistrictCode) ? $this->db_row('region_id', ['where' => [['region_code', $addr->subDistrictCode]]], 'SA\Reference\KYC\Region')->original['data'] : null;
                    $addr1      = !empty($addr->address1) ? $addr->address1 . '' : '';
                    $addr2      = !empty($addr->address2) ? $addr->address2 . '' : '';
                    $addr3      = !empty($addr->address3) ? $addr->address3 . '' : '';
                    $addr4      = !empty($addr->address4) ? $addr->address4 . '' : '';
                    $addr5      = !empty($addr->address5) ? $addr->address5 : '';
                    $type_addr  = !empty($addr->addressType) ? $addr->addressType : null;
                    $data       = ['investor_id'       => Auth::user()->investor_id,
                                   'province_id'       => $prv,
                                   'city_id'           => $city,
                                   'subdistrict_id'    => $district,
                                   'postal_code'       => !empty($addr->postalCode) ? $addr->postalCode : null,
                                   'address'           => $addr1 . $addr2 . $addr3 . $addr4 . $addr5,
                                   'address_type'      => $type_addr,
                                   'is_data'           => 'WS',
                                   'created_by'        => '::1',
                                   'created_host'      => '127.0.0.1'
                                  ];
                    $row        = Address::where([['investor_id', Auth::user()->investor_id], ['is_active', 'Yes'], ['address_type', $type_addr]])->first();
                    $save       = empty($row) ? Address::create($data) : Address::where('investor_address_id', $row->investor_address_id)->update($data);
                    $res[]      = $data;
                }
            }
            return ['success' => true, 'data' => $res];
        }
        catch (\Exception $e)
        {
            return ['success' => false, 'message' => $e->getMessage()];
        }      
    }
}
