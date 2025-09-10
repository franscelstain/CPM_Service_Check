<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Config\Config;
use App\Models\Financial\Condition\AssetLiability;
use App\Models\Financial\Condition\IncomeExpense;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\Users\Investor\Edd;
use App\Models\Users\Investor\EddFamily;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\InvestorHistory;
use App\Models\Users\Investor\Account;
use App\Models\Users\Investor\Address;
use App\Models\Users\Investor\Question;
use App\Models\Users\Investor\Audit;
use App\Models\Users\Investor\ResetPassword;
use App\Models\Administrative\Notify\NotificationInvestor;
use App\Models\Transaction\TransactionHistory;
use Illuminate\Http\Request;
use Schema;

class InvestorsController extends AppController
{
	public function api_edd(Request $request)
    {
	    try
	    {
	        $edd      	= [];
	        $success    = $fails = 0;
	        $investor   = Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])->get();
	        foreach ($investor as $inv)
	        {
	            $api = $this->api_ws(['sn' => 'InvestorEdd', 'val' => [$inv->cif]])->original['data'];	            
                if (!empty($api))
                {
                    foreach ($api as $a)
                    {	            
                    	$save = $this->save_edd($request, $inv->investor_id, $api);
						if (!empty($save->original['errors']))
                        {
                            $edd[] = array_merge(['investor_id' => $inv->investor_id], (array) $save->original['errors']);
                            $fails++;
                        }
                        else
                        {
                            $edd[] = $save;
                            $success++;
                        }
                    }
                }
	        }
	        return $this->app_partials($success, $fails, $edd);
	    }
	    catch(\Exception $e)
	    {
	        return $this->app_catch($e);
	    }
    }   

    private function save_edd($request, $inv_id, $a)
    {
    	try
	    {
	    	$fm 	= [];
	    	$edd 	= Edd::where('investor_id', $inv_id)->first();
	    	$act 	= empty($edd->investor_id) ? 'cre' : 'upd';	    	
	    	$family = ['father' => 'Ayah', 'mother' => 'Ibu', 'wife' => 'Pasangan', 'sibling' => 'Saudara', 'first_child' => 'AnakPertama', 'second_child' =>'AnakKedua'];

	    	$data = [
	    		'investor_id' 						=> $inv_id,
	    		'edd_date'      					=> $a->tanggal,
	    		'investment_objectives' 			=> !empty($a->tujuanInvestasi) ? $a->tujuanInvestasi : null,
	    		'hobby' 							=> !empty($a->hobi) ? $a->hobi : null,
	    		'other_hobby' 						=> !empty($a->hobiLainnya) ? $a->hobiLainnya : null,
	    		'organization' 						=> !empty($a->organisasi) ? $a->organisasi : null,
	    		'other_organization' 				=> !empty($a->organisasiLainnya) ? $a->organisasiLainnya : null,
	    		'bank' 								=> !empty($a->bank) ? $a->bank : null,
	    		'other_bank' 						=> !empty($a->bankLainnya) ? $a->bankLainnya : null,
	    		'insurance' 						=> !empty($a->asuransi) ? $a->asuransi : null,
	    		'other_insurance' 					=> !empty($a->asuransiLainnya) ? $a->asuransiLainnya : null,
	    		'product' 							=> !empty($a->jenisProduk) ? $a->jenisProduk : null,
	    		'other_product' 					=> !empty($a->jenisProdukLainnya) ? $a->jenisProdukLainnya : null,
	    		'credit_card' 						=> !empty($a->kartuKredit) ? $a->kartuKredit : null,
	    		'other_credit_card' 				=> !empty($a->kartuKreditLainnya) ? $a->kartuKreditLainnya : null,
	    		'relation_name' 					=> !empty($a->namaRelasi) ? $a->namaRelasi : null,
	    		'relation_type' 					=> !empty($a->jenisRelasi) ? $a->jenisRelasi : null,
	    		'relation_work' 					=> !empty($a->pekerjaanRelasi) ? $a->pekerjaanRelasi : null,
	    		'relation_office' 					=> !empty($a->alamatKantorRelasi) ? $a->alamatKantorRelasi : null,
	    		'is_investor_relation'				=> $a->apakahRelasiInvestor == true ? 't' : 'f',
	    		'media_name' 						=> !empty($a->namaMedia) ? $a->namaMedia : null,
	    		'conclusion_desc' 					=> !empty($a->deskripsiKesimpulan) ? $a->deskripsiKesimpulan : null,
	    		'marketing' 						=> !empty($a->marketing) ? $a->marketing : null,
	    		'marketing_recommendation' 			=> !empty($a->rekomendasiMarketing) ? $a->rekomendasiMarketing : null,
	    		'marketing_recommendation_desc'		=> !empty($a->deskripsiRekomendasiMarketing) ? $a->deskripsiRekomendasiMarketing : null,
	    		'branch_manager' 					=> !empty($a->managerCabang) ? $a->managerCabang : null,
	    		'agreed_create_account' 			=> $a->telahSetujuMembuatRekening == true ? 't' : 'f',
	    		'branch_manager_recommendation_desc'=> !empty($a->deskripsiRekomendasiManagerCabang) ? $a->deskripsiRekomendasiManagerCabang : null,
	    		'is_active'							=> 'Yes',
	    		$act.'ated_by' 						=> 'System',
	    		$act.'ated_host'					=> '::1'
	    	];


	    	$request->request->replace($data);
	            
	        if ($validate = $this->app_validate($request, Edd::rules(), true))
	            return $validate;
	        
	        if(empty($edd->investor_edd_id))
			{
				$save_edd 	= Edd::create($data);
				$edd_id 	= $save_edd->investor_edd_id;
			}				 
			else
			{
				$save_edd 	= Edd::where('investor_edd_id', $edd->investor_edd_id)->update($data);    
				$edd_id 	= $edd->investor_edd_id;
			}
	    	
	    	if ($save_edd)
	    	{
	    		EddFamily::where('investor_edd_id', $edd_id)->update(['is_active' => 'No']);
	    		foreach ($family as $fm_k => $fm_v) 
	    		{
		    		$fm[] = $this->save_edd_family($edd_id, $a, $fm_k, $fm_v);
	    		}
	    	}
	    	return array_merge($data, ['family' => $fm]);
	    }
	    catch(\Exception $e)
	    {
	        return $this->app_catch($e);
	    }
    }

    private function save_edd_family($edd_id, $a, $fm_k, $fm_v)
    {
    	try
    	{
	    	$edd_fm 		= EddFamily::where([['investor_edd_id', $edd_id], ['relationship', $fm_k]])->first();
			$act 			= empty($edd_fm->investor_edd_family_id) ? 'cre' : 'upd';
			$name 			= 'nama'.$fm_v;
			$birth 			= 'tanggalLahir'.$fm_v;
			$job  			= 'pekerjaan'.$fm_v;
			$salary		 	= 'penghasilan'.$fm_v;
			$is_investor 	= 'apakah'.$fm_v.'Investor';
				
			$data_fm = [
				'investor_edd_id'	=> $edd_id, 
				'family_name' 		=> !empty($a->$name) ? $a->$name : null,
				'date_of_birth'		=> !empty($a->$birth) ? $a->$birth : null,
				'occupation' 		=> !empty($a->$job) ? $a->$job : null,
				'salary' 			=> !empty($a->$salary) ? $a->$salary : null,
				'is_investor' 		=> $a->$is_investor == true ? 't' : 'f',
				'relationship' 		=> $fm_k,
				'is_active'			=> 'Yes',
				$act.'ated_by' 		=> 'System',
				$act.'ated_host'	=> '::1'
			];
			
			$fm[] = $data_fm;

			if (empty($edd_fm->investor_edd_family_id))
				EddFamily::create($data_fm);
			else
				EddFamily::where('investor_edd_family_id', $edd_fm->investor_edd_family_id)->update($data_fm);

			return $data_fm;
		}
	    catch(\Exception $e)
	    {
	        return $this->app_catch($e);
	    }
    }

    public function valid_account()
    {
        try
        {
        	$failed  = $success = 0;
            $array   = [];
            $config  = Config::where([['is_active', 'Yes'], ['config_name', 'ValidAccountExpired'], ['config_type', 'General']])->first();
            if (!empty($config->config_value))
            {
	            $date   = date('Y-m-d', strtotime('-'.$config->config_value.' day '. $this->app_date()));
	            $data   = Investor::where('valid_account', 'No')->whereDate('created_at', '<', $date)->get();
	          	$col 	= Schema::getColumnListing('u_investors');
            
	            foreach ($data as $dt) 
	            {
	            	$inv = []; 
	            	foreach ($col as $cl) 
	            	{
	            		$inv[$cl] = $dt->$cl;   
	            	}
		           	$inv['time_log'] = date('Y-m-d');

	                if (!$valid = $this->save_valid_account($dt->investor_id))
	                {
	                	$array['failed'][] 	= $dt;
	                	$failed++;
	                }
	                else
	                {
	                	$array['success'][] = $dt;
	                	$success++;
	                }	
	            }
            }
            return $this->app_response('Update Investor Valid Account', ['success' => $success, 'failed' => $failed, 'investor_id' => $array]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function save_valid_account($data)
    {
    	return Investor::where('investor_id', $data)->update(['is_active' => 'No', 'valid_account' => 'No']);
    }

    public function reset_token()
    {
        try
        {
            $rst = array();
            $idle_timeout = env('IDLE_TIMEOUT',15);
            $data = Investor::where('is_active', 'Yes')->get();
            foreach($data as $val) {
              if (!empty($val->last_activity_at)) {
                   $timediff = round((time()- strtotime($val->last_activity_at))/60);
                   if($timediff > $idle_timeout) {
                    Investor::where([['email', $val->email], ['is_active', 'Yes']])->update(['last_activity_at' => null, 'token' => null]);
                    $rst[] = array('minute' => $timediff, 'investor_email' => $val->email, 'fullname' => $val->fullname);
                  }
              } else {
                    Investor::where([['email', $val->email], ['is_active', 'Yes']])->update(['last_activity_at' => null, 'token' => null]);             
                    $rst[] = array('minute' => 'Last Active is Null', 'investor_email' => $val->email, 'fullname' => $val->fullname);
              }
              
            }

            return $this->app_response('Update Investor Reset Token', ['investor' => $rst]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    } 

    // public function update_ifua()
    // {
    //     try
    //     {  
    //     	$data_update = [];
    //         $inv =  Investor::select('investor_id','cif')
    //                  ->where('is_active', 'Yes')
    //                  ->whereNotNull('sid')
    //                  ->whereNull('ifua')
    //                 ->get();    


    //         foreach($inv as $val) {
    //         	if(!empty($val->cif)) {
    //                $api_ifua = $this->api_ws(['sn' => 'InvestorWMS', 'val' => [$val->cif]])->original;
	//                 if(!empty($api_ifua['data']->ifua)) {
	//                     Investor::where([['cif', $val->cif],['is_active','Yes']])->update(['ifua' => $api_ifua['data']->ifua]);
	// 		   			$data_update[] = ['cif'=>$val->cif,'ifua'=>$api_ifua['data']->ifua];
	//                 }    
    //         	}
    //         }

    //         return $this->app_response('Update IFUA Investor By CIF', $data_update); 
    //     }
    //     catch(\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     } 
    // }  
	
	public function update_ifua()
    {
        try
        {  
				$chunkSize = 1000;
			$data_update = [];
			Investor::select('investor_id','cif')
	         ->where([['is_active', 'Yes'], ['valid_account', 'Yes']])
	         ->chunk($chunkSize, function ($inv) use (&$data_update) {
	             foreach ($inv as $val) {
	                 if (!empty($val->cif)) {
	                     $api_sid = $this->api_ws(['sn' => 'InvestorWMS', 'val' => [$val->cif]])->original;
	                     if (!empty($api_sid['data']->sidMf) || !empty($api_sid['data']->ifua)) {
							Investor::where([['cif', $val->cif], ['is_active', 'Yes']])
								->update([
									'ifua' => $api_sid['data']->ifua ?? null,
									'sid'  => $api_sid['data']->sidMf ?? null
								]);
							$data_update[] = [
								'cif'  => $val->cif,
								'sid'  => $api_sid['data']->sidMf ?? null,
								'ifua' => $api_sid['data']->ifua ?? null
							];
	                     }    
	                 }
	             }
	         });

            return $this->app_response('Update IFUA Investor By CIF', $data_update); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }                
              
}