<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\Account;	
use App\Models\Users\Investor\Address;								  
use App\Models\Users\Investor\CardPriority;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\Financial\AssetOutstanding;
use App\Models\SA\Reference\KYC\Nationality;
use App\Models\SA\Reference\KYC\DocumentType; 
use App\Models\Transaction\TransactionFeeOutstanding; 
use App\Models\SA\Reference\KYC\RiskProfiles\Profile;
use App\Models\Users\User; 
use App\Models\Users\UserSalesDetail; 									  
use App\Models\Administrative\Api\Host; 
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client;

class SyncController extends AppController  
{

   private $cpm_api = ''; 
   private $crm_api = ''; 
   private $wms_api = ''; 						  
    
      public function investor_priority()
      {
      // - Is priority **True** and Pre Approve **True** maka Pre Approved -> **ID 3**
      // - Is priority **False** and Pre Approve **True** maka Pre Approved -> **ID 3**
      // - Is priority **True** and Pre Approve **False** maka Priority ->** ID 2**
      // - Is priority **False** and Pre Approve **False** maka Non Priority -> **ID 1**
      
      try
      {
         $this->initAPI();
         $investor = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])
                    ->orderBy('u_investors.cif')
                    ->get();
         $xres = [];
         foreach($investor as $dt)
         {
               $inv_type=null;
               $card = CardPriority::where([['cif', $dt->cif ], ['is_active','Yes' ]])
                           ->first();
               if (!empty($card->investor_card_id)) 
               {
                  //$inv_type = !$card->pre_approve ?  $card->is_priority ? 2 : 1 : 3 ;
                  $inv_type = !$card->pre_approve ?  $card->is_priority ? 'Priority' : 'Non Priority' : 'Pre Approved' ;
                  $data=['CIF' => $dt->cif, 'InvestorType' => $inv_type, 'channelID' => 1]; 

                  //post to CRM
                  $url = $this->crm_api->slug.'AccountPriorityUpdate';
                  $res = $this->ext_api2($url, 'POST', ['Content-Type: application/json'], json_encode($data));
                  $xres[] = json_decode($res, true);
               } 
         }
         return  ($xres);
      }
      catch (\Exception $e)
      {
         return $this->api_catch($e);
      }
   }

   public function assets()
   {
      $this->initAPI();
      try 
      {
         $investor = Investor::select('cif','investor_id')
                     ->distinct('cif')
                     ->where([['is_active', 'Yes'], ['valid_account', 'Yes']])
                     ->orderBy('u_investors.cif')
                     ->get();
         $xres = [];
         foreach($investor as $inv)
         {
            $url = $this->crm_api->slug.'Customer';
            
            $data = $this->data_assets($inv->cif, $inv->investor_id);
            $res = $this->ext_api2($url, 'POST', ['Content-Type: application/json'], json_encode($data));

            $xres[] = json_decode($res, true);
         }
         return  ($xres);
      }
      catch (\Exception $e)
      {
         return $this->api_catch($e);
      }
   }

   private function data_assets($cif, $inv_id)
   {
      try 
      {
         $qry = DB::table('u_investors')->selectRaw("cif, asset_category_name, product_name, asset_class_name, tao.balance_amount as amount")
                     ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
                     ->leftJoin('m_products as mp', 'mp.product_id', 'tao.product_id')
                     ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                     ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                     ->where([['u_investors.valid_account', 'Yes'],['u_investors.is_active', 'Yes'],
                           ['cif', $cif],
                           ['tao.outstanding_date', $this->cpm_date()]])
                     ->get(); 

         $row = [
               'AUMDate'           => date("Y-m-d"),
               'AmountBancas'      => 0,
               'AmountDeposito'    => 0,
               'AmountGiro'        => 0,
               'AmountReksadana'   => 0,
               'AmountSukuk'       => 0,    
               'AmountTabungan'    => 0,
               'AmountLiabilities' => 0,
               'CIF'               => $cif,
               'FeeBaseBancas'     => null,
               'FeeBaseMFee'       => null,
               'FeeBaseSukuk'      => null,
               'FeeBaseTrans'      => null,
               'NTI'               => 0,
               'TotalProd'         => 0,
               'channelID'         => 1
         ];
      
         foreach ($qry as $dt) {  
            switch (strtolower($dt->asset_class_name))
            {
               case 'insurance':
                  $row['AmountBancas']      += (float)$dt->amount;
                  break;
               case 'deposit':
               case 'deposito':
                  $row['AmountDeposito']    += (float)$dt->amount;
                  break;
               case 'giro':
                  $row['AmountGiro']        += (float)$dt->amount;
                  break;
               // case 'reksa dana':
               // case 'mutual fund':
               // case 'balance fund':
               // case 'equity fund':
               // case 'fixed income fund':
               // case 'money market fund':
               //    $row['AmountReksadana']   += (float)$dt->amount;
               //    break;
               // case 'bonds':
               // case 'government bond':
               //    $row['AmountSukuk']       += (float)$dt->amount;
               //    break;
               case 'saving':
                  $row['AmountTabungan']    += (float)$dt->amount;
                  break;
            } 

            switch (strtolower($dt->asset_category_name))
            {
               // case 'insurance':
               //    $row['AmountBancas']      += (float)$dt->amount;
               //    break;
               // case 'deposit':
               //    $row['AmountDeposito']    += (float)$dt->amount;
               //    break;
               // case 'giro':
               //    $row['AmountGiro']        += (float)$dt->amount;
               //    break;
               case 'reksa dana':
               case 'mutual fund':
               case 'balance fund':
               case 'equity fund':
               case 'fixed income fund':
               case 'money market fund':
                  $row['AmountReksadana']   += (float)$dt->amount;
                  break;
               case 'bonds':
               case 'government bond':
                  $row['AmountSukuk']       += (float)$dt->amount;
                  break;
               // case 'saving':
               //    $row['AmountTabungan']    += (float)$dt->amount;
               //    break;
            } 

            $row['TotalProd']++;
         }

         $outstanding = LiabilityOutstanding::selectRaw('investor_id,(outstanding_balance)as amount,max(outstanding_date)' )
                           ->where([['investor_id', $inv_id],['is_active', 'Yes']])
                           ->groupBy('investor_id', 'outstanding_balance')
                           ->get();
         foreach($outstanding as $dt) {
            $row['AmountLiabilities'] += (float)$dt->amount;
         }

         $fee_date = TransactionFeeOutstanding::selectRaw('max(fee_date)')->first();
         $fee = TransactionFeeOutstanding::selectRaw('fee_date, fullname, asset_category_name, fee_category, fee_amount as amount'  )
                           ->leftJoin('m_products as mp', 'mp.product_id', 't_fee_outstanding.product_id') 
                           ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id') 
                           ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id') 
                           ->leftJoin('u_investors as ui', 'ui.investor_id', 't_fee_outstanding.investor_id')
                           ->whereRaw("t_fee_outstanding.investor_id='$inv_id' and mp.is_active='Yes' and ui.is_active='Yes' and mac.is_active='Yes' and mact.is_active='Yes' and fee_date='".$fee_date->max."' ")
                           ->get();
         foreach($fee as $dt) {
            $dtx = [strtolower($dt->asset_category_name), strtolower($dt->fee_category) ];
            if (strtolower($dt->fee_category) == 'sharing fee') {
               switch(strtolower($dt->asset_category_name)) {
                  case 'insurance':
                     $row['FeeBaseBancas'] += (float)$dt->amount;
                     break;
                  case 'bonds':
                  case 'bond':
                     $row['FeeBaseSukuk'] += (float)$dt->amount;
                     break;
                  case 'mutual fund':
                     $row['FeeBaseMFee'] += (float)$dt->amount;
                     break;
               }
            }
            if (strtolower($dt->fee_category) == 'transaction fee') {
               if (strtolower($dt->asset_category_name)=='mutual fund') {
                  $row['FeeBaseTrans'] += (float)$dt->amount;
               }
            }
         }

         $row['NTI'] = $this->get_nti_status($cif);
         $row['channelID'] = 1;

         return $row;
      }
      catch (\Exception $e)
      {
         return $this->api_catch($e);
      }
   }

   public function crm_investor() 
   {
      try {
         ini_set('max_execution_time', '14400');

         $investor = Investor::select('identity_no')
         ->distinct('identity_no')
         ->where([['is_active', 'Yes'], ['valid_account', 'Yes']])
         ->get();
         $tot=[]; $update=0;
         foreach($investor as $inv)
         {
            if(!empty($inv->identity_no))
            {
               $id_no = $inv->identity_no;
               $crm    = $this->api_ws(['sn' => 'InvestorCRM', 'val' => [$id_no]])->original['data'];
               if (!empty($crm->cif))  {
                  $dt = Nationality::whereRaw("UPPER(ext_code)='".strtoupper($crm->nationalityCode)."'")->first();
                  $nationality_id = ($dt!=null)? $dt->nationality_id : null;

                  $dt = DocumentType::whereRaw("UPPER(doctype_code)='".strtoupper($crm->identityType)."'")->first();
                  $doctype_id = ($dt!=null)? $dt->doctype_id : null;

                  $sales_id = !empty($crm->salesCode) ? $this->db_row('user_id', ['where' => [['ext_code', $crm->salesCode], ['is_active', 'Yes']]], 'Users\User')->original['data'] : null;

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
                              'cif'                   => !empty($crm->cif) ? $crm->cif : null,
                              'fullname'              => !empty($crm->fullname) ? $crm->fullname : null,
                              'place_of_birth'        => !empty($crm->birthPlace) ? $crm->birthPlace : null,
                              'date_of_birth'         => !empty($crm->birthDate) ? $crm->birthDate : null,
                              'identity_expired_date' => !empty($crm->identityExpiredDate) ? $crm->identityExpiredDate : null,
                              'tax_no'                => !empty($crm->taxNumber) ? $crm->taxNumber : null,
                              'phone'                 => !empty($crm->phone) ? $crm->phone : null,
                              'mobile_phone'          => !empty($crm->mobile) ? $crm->mobile : null,
                              'company_phone'         => !empty($crm->officePhone) ? $crm->officePhone : null,
                              'fax'                   => !empty($crm->fax) ? $crm->fax : null
                  ];
                  if ($sales_id != 0) $data['sales_id'] = $sales_id;
                  Investor::where('identity_no', $id_no)->update($data);
                  $tot[]=$data;
                  $update=$update+1;
               } else {
                  //return $id_no.' no data';
               }
            }
         }
         return $this->app_response('Synchronize data from CRM success', $tot);
      }
      catch (\Exception $e)
      {
         return $this->api_catch($e);
      }
   }

   public function get_nti_status($cif) 
   {
      $yr = date("Y-m-d",strtotime("-1 year"));
      $qry = DB::table('u_investors')->selectRaw("cif")
            ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
            ->leftJoin('m_products as mp', 'mp.product_id', 'tao.product_id')
            ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
            ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
            ->leftJoin('m_financials_assets as mfa', 'mfa.asset_class_id', 'mac.asset_class_id')
            ->leftJoin('m_financials as mf', 'mf.financial_id', 'mfa.financial_id')
            ->whereRaw("u_investors.valid_account='Yes' and u_investors.is_active='Yes' and mfa.is_active='Yes' and 
                     cif='$cif' and (LOWER(financial_name) in ('bonds', 'government bond', 'reksa dana', 'mutual fund', 'balance fund', 'equity fund', 'fixed income fund','money market fund','insurance')) and
                     (tao.outstanding_date>'$yr') and 
                     tao.balance_amount>0 ")
            ->count(); 
      $nti   = ($qry>0) ? 0 : 1;
      return $nti;
   
   }

	// public function bank_account(Request $request) {
 //      try
 //      { 
 //         ini_set('max_execution_time', '3600');
         
 //         $this->initAPI();
 //         $token = $this->get_token_wms();


 //         if(!empty($request->cif)) {
 //            $where = [['is_active', 'Yes'],['cif', $request->cif]];
 //         } else {
 //            $where = [['is_active', 'Yes']];            
 //         }

 //         $investor = Investor::where($where)
 //                    ->orderBy('u_investors.cif')
 //                    ->get();

 //         $res = [];
 //         $detail_update = [];
 //         $detail_insert = [];

 //         foreach($investor as $dt)
 //         {
 //            $wms = array();   
 //            $url = $this->wms_api->slug.'Investor/account/sync?key=cif&value='.$dt->cif.'&destination=wms';
 //            $wms = json_decode($this->ext_api3($url, 'GET', ['Authorization: Bearer '.$token]),true); 

 //            if(!empty($wms['data'])) {

 //               $data_bank_account_exist = DB::table('u_investors_accounts')
 //               ->where([['investor_id', $dt->investor_id]])
 //               ->get();

 //               $wms = array();   
 //               $data_core_banking_bsi = array();
 //               $data_bank_account_exist_arr = array();
 //               foreach($data_bank_account_exist as $dt_2) {
 //                 $data_bank_account_exist_arr[] = $dt_2->account_no;
 //               }

 //               foreach($wms['data'] as $dt_wms) {
 //                  //$dt_2->investor_id = '57';
 //                  //$account_no = '86964932423';
 //                  //$ext_code = 0000
 //                  $investor_id = $dt->investor_id;
 //                  $account_name = $dt_wms['accountName'];
 //                  $account_no = $dt_wms['accountNo'];  
 //                  $currencyId = $dt_wms['currencyId'];
 //                  $bank_branch_code = $dt_wms['bankBranchCode'];
 //                  $account_type_id = $dt_wms['accountTypeId'];
 //                  $ext_code = $dt_wms['id'];

 //                  $bank_branch = DB::table('m_bank_branches')
 //                                 ->where([['branch_code', $bank_branch_code]])
 //                                 ->first();

 //                  //sync yang branch codenya ada dan type account 1 = saving atau 2 = giro
 //                  if(!empty($bank_branch->bank_branch_id) && in_array($account_type_id,array('1','2'))) {               
 //                     $bank_branch_id = $bank_branch->bank_branch_id;
 //                     if(in_array($account_no,$data_bank_account_exist_arr)) {
 //                        $data_update_insert = ['account_name'   => !empty($account_name) ? $account_name : '',
 //                                              'currency_id'     => !empty($currencyId) ? $currencyId : 1, 
 //                                              'ext_code'        => $ext_code,  
 //                                              'is_data'         => 'WS',
 //                                              'is_active'       => 'Yes',
 //                                              'updated_host'    => '::1',
 //                                              'updated_by'      => 'System',
 //                                              'updated_at'      => $this->cpm_date(),
 //                                              'bank_branch_id'  => !empty($bank_branch_id) ? (int) $bank_branch_id : '',
 //                                              'account_type_id' => !empty($account_type_id) ? (int) $account_type_id : 2,
 //                                             ];                  

 //                        if(Account::where([['account_no', $account_no],['investor_id', $investor_id]])->update($data_update_insert)) {
 //                           $detail_update[] = [['investor_id' => $investor_id,'account_no'=>$account_no]];
 //                        }
 //                     } 
 //                     else {
 //                        $data_update_insert = ['account_name'   => !empty($account_name) ? $account_name : '',
 //                                              'currency_id'     => !empty($currencyId) ? $currencyId : 1, 
 //                                              'ext_code'        => $ext_code,  
 //                                              'is_data'         => 'WS',
 //                                              'is_active'       => 'Yes',
 //                                              'created_host'    => '::1',
 //                                              'created_by'      => 'System',
 //                                              'created_at'      => $this->cpm_date(),
 //                                              'bank_branch_id'  => !empty($bank_branch_id) ? (int) $bank_branch_id : '5663',
 //                                              'account_type_id' => !empty($account_type_id) ? (int) $account_type_id : 2,
 //                                              'investor_id'     => $dt_2->investor_id,
 //                                              'account_no'      => !empty($account_no) ? $account_no :  ''
 //                                             ];                  

 //                        if(Account::create($data_update_insert)) {
 //                           $detail_insert[] = [['investor_id' => $investor_id,'account_no'=>$account_no]];
 //                        }      
 //                     }
 //                  }   
 //               }  
 //            }
 //         }

 //        return response()->json([
 //         'detail_insert' => $detail_insert,
 //         'detail_update' => $detail_update,
 //        ]);

 //      }
 //      catch (\Exception $e)
 //      {
 //         return $this->api_catch($e);
 //      }

 //   }

   public function bank_account(Request $request) {
      try
      { 
         ini_set('max_execution_time', '3600');
         
         $this->initAPI();
         $token = $this->get_token_wms();


         if(!empty($request->cif)) {
            $where = [['is_active', 'Yes'],['cif', $request->cif]];
         } else {
            $where = [['is_active', 'Yes']];            
         }

         $investor = Investor::where($where)
                    ->orderBy('u_investors.cif')
                    ->get();

         $res = [];
         $detail_update = [];
         $detail_insert = [];

         foreach($investor as $dt)
         {
            // $wms = array();   
            // $url = $this->wms_api->slug.'Investor/account/sync?key=cif&value='.$dt->cif.'&destination=wms';
            // $wms = json_decode($this->ext_api3($url, 'GET', ['Authorization: Bearer '.$token]),true); 
            
            $res        = [];
            $account    = $this->api_ws(['sn' => 'InvestorAccount', 'val' => [$dt->cif]])->original['data'];
            if (!empty($account))
            {
               // $accout_no = Account::where([['account_no',$acc->accountNo], ['investor_id', $dt->investor_id],['is_active', 'Yes']])->first();
               $update = Account::where([['investor_id', $dt->investor_id]])->update(['is_active' => 'No']);
               // return $this->app_response('xxx', $update);
               foreach ($account as $acc)
               {
                  $accout_no = Account::where([['account_no',$acc->accountNo], ['investor_id', $dt->investor_id]])->first();
                  if(empty($accout_no))
                  {
                    $accNo  = !empty($acc->accountNo) ? $acc->accountNo : null;
                    $data   = ['investor_id'    => $dt->investor_id,
                               'account_name'   => !empty($acc->accountName) ? $acc->accountName : null,
                               'account_no'     => $accNo,
                               'currency_id'    => !empty($acc->currencyId) ? $this->db_row('currency_id', ['where' => [['currency_code', $acc->currencyCode]]], 'SA\Assets\Products\Currency')->original['data'] : null,
                               //'bank_branch_id' => !empty($acc->bankBranchId) ? $this->db_row('bank_branch_id', ['where' => [['branch_name', $acc->bankBranch]]], 'SA\Reference\Bank\Branch')->original['data'] : null,
                               //'account_type_id'=> !empty($acc->accountTypeId) ? $this->db_row('account_type_id', ['where' => [['ext_code', $acc->accountTypeId]]], 'SA\Reference\Bank\AccountType')->original['data'] : null,
                               'bank_branch_id' => !empty($acc->bankBranchCode) ? $this->db_row('bank_branch_id', ['where' => [['branch_code', $acc->bankBranchCode]]], 'SA\Reference\Bank\Branch')->original['data'] : null,
                                'account_type_id'=> !empty($acc->accountTypeId) ? $this->db_row('account_type_id', ['where' => [['ext_code', $acc->accountTypeId]]], 'SA\Reference\Bank\AccountType')->original['data'] : null,
                              'ext_code'       => !empty($acc->accountTypeCode) ? $acc->accountTypeCode : null,
                               'is_data'        => 'WS',
                               'created_by'     => '::1',
                               'created_host'   => '127.0.0.1'
                              ];
                    // $row    = Account::where([['investor_id', $dt->investor_id], ['account_no', $accNo], ['is_active', 'Yes']])->first();
                    // $save   = empty($row) ? Account::create($data) : Account::where('investor_account_id', $row->investor_account_id)->update($data);
                     $save   =  Account::create($data);
                    $res[]  = $data; 
                  }else{
                     $data   = ['investor_id'    => $dt->investor_id,
                               'account_name'   => !empty($acc->accountName) ? $acc->accountName : null,
                               'account_no'     => $acc->accountNo,
                               'currency_id'    => !empty($acc->currencyId) ? $this->db_row('currency_id', ['where' => [['currency_code', $acc->currencyCode]]], 'SA\Assets\Products\Currency')->original['data'] : null,
                               //'bank_branch_id' => !empty($acc->bankBranchId) ? $this->db_row('bank_branch_id', ['where' => [['branch_name', $acc->bankBranch]]], 'SA\Reference\Bank\Branch')->original['data'] : null,
                               //'account_type_id'=> !empty($acc->accountTypeId) ? $this->db_row('account_type_id', ['where' => [['ext_code', $acc->accountTypeId]]], 'SA\Reference\Bank\AccountType')->original['data'] : null,
                               'bank_branch_id' => !empty($acc->bankBranchCode) ? $this->db_row('bank_branch_id', ['where' => [['branch_code', $acc->bankBranchCode]]], 'SA\Reference\Bank\Branch')->original['data'] : null,
                                'account_type_id'=> !empty($acc->accountTypeId) ? $this->db_row('account_type_id', ['where' => [['ext_code', $acc->accountTypeId]]], 'SA\Reference\Bank\AccountType')->original['data'] : null,
                              'ext_code'       => !empty($acc->accountTypeCode) ? $acc->accountTypeCode : null,
                              'is_active'       => 'Yes',
                               'is_data'        => 'WS',
                               'created_by'     => '::1',
                               'created_host'   => '127.0.0.1'
                              ];
                     // $row    = Account::where([['investor_id', $dt->investor_id], ['account_no', $acc->accountNo]])->first();
                     $save   = Account::where([['account_no', $acc->accountNo],['investor_id', $dt->investor_id]])->update($data);
                     // Account::where('investor_id', $dt->investor_id)->update('is_active', 'No');
                  }
               }
            }
         }

         return $this->app_response('Update investor account and address', ['investor' => $account]);
      }
      catch (\Exception $e)
      {
         return $this->api_catch($e);
      }

   }

     public function sales_detail() {
      try
      {
         ini_set('max_execution_time', '3600');

         $this->initAPI();
         $token = $this->get_token_wms();
         $sales = User::join('u_users_categories as b', 'u_users.usercategory_id', '=', 'b.usercategory_id')
                  ->where([['u_users.is_active', 'Yes'],['b.usercategory_name', 'Sales']])
                  ->whereNotNull('u_users.user_code')
                  ->orderBy('u_users.user_code')         
                  ->get();
         $res = [];
         $detail_update = [];
         $detail_insert = [];

         foreach($sales as $dt)
         {
            //$user_code = trim(strip_tags($dt->user_code));
            // $wms    = $this->api_ws(['sn' => 'SalesWaperd', 'val' => [$dt->user_code]])->original['data'];
            $wms    = $this->api_ws(['sn' => 'SalesWaperd', 'val' => [$dt->user_code]]);

            if(!empty($wms)) {
               $salesDetail = UserSalesDetail::where([['user_id', $dt->user_id],['is_active', 'Yes']])   
                              ->first();

               /*               
               $data_update_insert = [ 'user_id'                => $dt->user_id,
                                       'agent_branch_code'      => !empty($wms['data']['agentBranchCode']) ? $wms['data']['agentBranchCode'] : $wms['data']['dummyBranchCode'], 
                                       'agent_branch_name'      => !empty($wms['data']['agentBranchName']) ? $wms['data']['agentBranchName'] : $wms['data']['dummyBranchName'],  
                                       'agent_id'               => !empty($wms['data']['agentID']) ? $wms['data']['agentID'] : $wms['data']['dummyAgentID'],
                                       'agent_waperd_expdate'   => !empty($wms['data']['agentWaperdExpDate']) ? $wms['data']['agentWaperdExpDate'] : $wms['data']['dummyWaperdExpDate'],
                                       'agent_waperd_no'        => !empty($wms['data']['agentWaperdNo']) ? $wms['data']['agentWaperdNo'] : $wms['data']['dummyWaperdNo'],
                                       'is_data'                => 'WS',
                                       'is_active'              => 'Yes',
                                     ];  
               */                                                     

                $data_update_insert = [ 'user_id'                => $dt->user_id,
                                       'agent_branch_code'      => !empty($wms->agentBranchCode) ? $wms->agentBranchCode : null, 
                                       'agent_branch_name'      => !empty($wms->agentBranchName) ? $wms->agentBranchName : null,  
                                       'agent_id'               => !empty($wms->agentID) ? $wms->agentID : null,
                                       'agent_waperd_expdate'   => !empty($wms->agentWaperdExpDate) ? $wms->agentWaperdExpDate : null,
                                       'agent_waperd_no'        => !empty($wms->agentWaperdNo) ? $wms->agentWaperdNo : null,
                                       'is_data'                => 'WS',
                                       'is_active'              => 'Yes',
                                       'created_by'             => '::1',
                                       'created_host'           => '127.0.0.1'
                                     ];  

               if(!empty($salesDetail->user_sales_detail_id)) {
                  if(UserSalesDetail::where([['user_id', $dt->user_id], ['is_active', 'Yes']])->update($data_update_insert)) {
                        $detail_update[] = [['user_detail_id' => $salesDetail->user_sales_detail_id,'data'=> $data_update_insert]];
                  }
               } 
               else {
                  if(UserSalesDetail::create($data_update_insert)) {
                     $getLastSalesDetailId = UserSalesDetail::where([['user_id', $dt->user_id],['is_active', 'Yes']])
                                             ->orderBy('user_sales_detail_id', 'desc')
                                             ->first();
                     $detail_insert[] = [['user_detail_id' => $getLastSalesDetailId->user_sales_detail_id,'data'=> $data_update_insert]];
                  }      
               }
            }   
         }         
        return response()->json([ 'detail_insert' => $detail_insert, 'detail_update' => $detail_update]);

      }
      catch (\Exception $e)
      {
         return $this->api_catch($e);
      }
   }

   public function ext_api3($url, $type, $header, $post_field='') 
   { 
      $curl = curl_init();
      curl_setopt_array($curl, array(
         CURLOPT_URL => $url,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_ENCODING => '',
         CURLOPT_MAXREDIRS => 10,
         CURLOPT_TIMEOUT => 0,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
         CURLOPT_CUSTOMREQUEST => $type,
         CURLOPT_HTTPHEADER => $header
      ));
      $res = curl_exec($curl);
      curl_close($curl);

      return $res;   
   }
							   
	public function ext_api2($url, $type, $header='', $post_field='') 
   { 
      $curl = curl_init();
      $dat = array(
         CURLOPT_URL => $url,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_ENCODING => '',
         CURLOPT_MAXREDIRS => 10,
         CURLOPT_TIMEOUT => 0,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
         CURLOPT_CUSTOMREQUEST => $type,
      );
      if ($header != '') $dat[CURLOPT_HTTPHEADER] = $header;
      if ($post_field != '') $dat[CURLOPT_POSTFIELDS ] = $post_field;
      curl_setopt_array($curl, $dat);
      $res = curl_exec($curl);
      curl_close($curl);
      $res = json_decode($res);
      return json_encode($res);
      if ($res->code == '200') //sukses
      {
         return $res->data;
      } else { //error
         return $res;
      }
   }

   public function ext_api($url, $type, $header, $post_field='') 
   { 
      $curl = curl_init();
      curl_setopt_array($curl, array(
         CURLOPT_URL => $url,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_ENCODING => '',
         CURLOPT_MAXREDIRS => 10,
         CURLOPT_TIMEOUT => 0,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
         CURLOPT_CUSTOMREQUEST => $type,
         CURLOPT_HTTPHEADER => $header
      ));
      $res = curl_exec($curl);
      curl_close($curl);
      $res = json_decode($res);

      if (!empty($res) and $res->code == '200') //sukses
      {
         return $res->data;
      } else { //error
         return null;
      }
   }

   public function get_token() 
   {
      $this->initAPI(); 
      $url = $this->cpm_api->slug.'auth';
      $curl = curl_init();
      curl_setopt_array($curl, array(
         CURLOPT_URL => $url,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_ENCODING => '',
         CURLOPT_MAXREDIRS => 10,
         CURLOPT_TIMEOUT => 0,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
         CURLOPT_CUSTOMREQUEST => 'POST',
         CURLOPT_POSTFIELDS =>'{"username":"'.$this->cpm_api->username.'","password":"'.$this->cpm_api->password.'"}',
         CURLOPT_HTTPHEADER => array(
               'Content-Type: application/json'
         ),
      ));
      $res = curl_exec($curl);
      curl_close($curl);
      $res = json_decode($res);
      if ($res->code == '200') //sukses
      {
         return $res->data;
      } else { //error
         return $res;
      }
   }

	public function get_token_wms() 
   {
      $this->initAPI(); 
      $url = $this->cpm_api->slug.'Auth';
      $curl = curl_init();
      curl_setopt_array($curl, array(
         CURLOPT_URL => $url,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_ENCODING => '',
         CURLOPT_MAXREDIRS => 10,
         CURLOPT_TIMEOUT => 0,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
         CURLOPT_CUSTOMREQUEST => 'POST',
         CURLOPT_POSTFIELDS =>'{"username":"'.$this->cpm_api->username.'","password":"'.$this->cpm_api->password.'"}',
         CURLOPT_HTTPHEADER => array(
               'Content-Type: application/json'
         ),
      ));
      $res = curl_exec($curl);
      curl_close($curl);
      $res = json_decode($res);
      if ($res->code == '200') //sukses
      {
         return $res->data;
      } else { //error
         return $res;
      }
   }							   

	public function cpm_date($date='', $fmt='Y-m-d')
   {
      if ($fmt == '.net')
      {
         preg_match('/([\d]{9})/', $date, $dt);
         return date('Y-m-d', $dt[0]);
      }
      else
      {
         return !empty($date) ? date($fmt, strtotime($date)) : date($fmt);
      }
   }

   protected function api_response($msg, $data = [], $errors = [])
   {
      //Generate API response
      $success    = empty($errors) ? true : false;
      $data       = empty($errors) ? $data : [];
      $response   = ['success' => $success, 'message' => $msg, 'data' => $data];
      $response   = [ "ErrorCode"=> "0000", "IsSuccess"=> true, "Message"=> "", "ResponseCode"=> null, "SolutionCode"=> null, 'Result' => $data];
      if (!$success)
      {
         $response = array_merge($response, ['errors' => $errors]);
      }
      return response()->json($response);
   }

   protected function api_catch($e)
   {
      //Catch Error
      return $this->api_response('Response Failed', [], ['error_code' => 500, 'error_msg' => [$e->getMessage()]]);
   }

   protected function initAPI()
   {
      $res = Host::where([['api_name','API_WMS'],['is_active','Yes']])->first();
      if(!empty($res)) $this->cpm_api = $res;
      $res = Host::where([['api_name','API_CRM'],['is_active','Yes']])->first();
      if(!empty($res)) $this->crm_api = $res;
	  $res = Host::where([['api_name','API_WMS'],['is_active','Yes']])->first();
      if(!empty($res)) $this->wms_api = $res;																		
      return [$this->cpm_api,$this->crm_api];
   }

    public function investor_sync()
    {
        try
        {
            ini_set('max_execution_time', '14400');

            $cif        = [];
            $investor   = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])
                        ->orderBy('u_investors.cif')
                        ->get();
            
            foreach ($investor as $inv)
            {
                $account = $this->investor_sync_account($inv);
                if ($account['success'])
                    $cif['account']['success'][$inv->cif] = $account['data'];
                else
                    $cif['account']['failed'][$inv->cif] = $account['message'];
                
                $address = $this->investor_sync_address($inv);
                if ($address['success'])
                    $cif['address']['success'][$inv->cif] = $address['data'];
                else
                    $cif['address']['failed'][$inv->cif] = $address['message'];
            }
            
            return $this->app_response('Update investor account and address', ['investor' => $cif]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function investor_sync_account($inv)
    {
        try
        {
            ini_set('max_execution_time', '14400');
            
            $res        = [];
            $account    = $this->api_ws(['sn' => 'InvestorAccount', 'val' => [$inv->cif]])->original['data'];
            if (!empty($account))
            {
                foreach ($account as $acc)
                {
                    $accNo  = !empty($acc->accountNo) ? $acc->accountNo : null;
                    $data   = ['investor_id'    => $inv->investor_id,
                               'account_name'   => !empty($acc->accountName) ? $acc->accountName : null,
                               'account_no'     => $accNo,
                               'currency_id'    => !empty($acc->currencyId) ? $this->db_row('currency_id', ['where' => [['currency_code', $acc->currencyCode]]], 'SA\Assets\Products\Currency')->original['data'] : null,
                               'bank_branch_id' => !empty($acc->bankBranchCode) ? $this->db_row('bank_branch_id', ['where' => [['branch_code', $acc->bankBranchCode]]], 'SA\Reference\Bank\Branch')->original['data'] : null,
                                'account_type_id'=> !empty($acc->accountTypeId) ? $this->db_row('account_type_id', ['where' => [['ext_code', $acc->accountTypeId]]], 'SA\Reference\Bank\AccountType')->original['data'] : null,
                               'ext_code'       => !empty($acc->accountTypeCode) ? $acc->accountTypeCode : null,
                               'is_data'        => 'WS',
                               'created_by'     => '::1',
                               'created_host'   => '127.0.0.1'
                              ];
                    $row    = Account::where([['investor_id', $inv->investor_id], ['account_no', $accNo], ['is_active', 'Yes']])->first();
                    $save   = empty($row) ? Account::create($data) : Account::where('investor_account_id', $row->investor_account_id)->update($data);
                    $res[]  = $data;
                }
            }
            return ['success' => true, 'data' => $res];
        }
        catch (\Exception $e)
        {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function investor_sync_address($inv)
    {
        try
        {
            $res        = [];
            $address    = $this->api_ws(['sn' => 'InvestorAddress', 'val' => [$inv->cif]])->original['data'];
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
                    $data       = ['investor_id'       => $inv->investor_id,
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
                    $row        = Address::where([['investor_id', $inv->investor_id], ['is_active', 'Yes'], ['address_type', $type_addr]])->first();
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

   public function investor_risk_profile_sync(Request $request) {
      try
      { 
         ini_set('max_execution_time', '3600');
         
         $this->initAPI();
         $token = $this->get_token_wms();

         if(!empty($request->cif)) {
            $where = [['is_active', 'Yes'],['cif', $request->cif]];
         } else {
            $where = [['is_active', 'Yes']];            
         }

         $investor = Investor::where($where)
                    ->orderBy('u_investors.cif')
                    ->get();
         
         $res = [];
         $detail_update = [];

         foreach($investor as $dt)
         {
            $wms = array();   
            $url = $this->wms_api->slug.'Investor/?key=cif&value='.$dt->cif.'&source=wms';
            $wms = json_decode($this->ext_api3($url, 'GET', ['Authorization: Bearer '.$token]),true); 


            if(!empty($wms['data'])) {
               if(!empty($wms['data']['cif']) && !empty($wms['data']['profileId'])) {
                  $riskProfile = Profile::where([['is_active', 'Yes'], ['ext_code', $wms['data']['profileId']] ])->first();
                  $investor = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes'], ['cif',$wms['data']['cif']] ])->update(['profile_id' => $riskProfile->profile_id]);
                  $detail_update[] = array('save_to_cpm'=> ($investor == true ? 'success' : 'failed'),'data_from_wms'=>$wms);  
               }      
            }
         }

        return response()->json([
         'detail_update' => $detail_update,
        ]);

      }
      catch (\Exception $e)
      {
         return $this->api_catch($e);
      }

   }
}
