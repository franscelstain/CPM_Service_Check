<?php

namespace App\Http\Controllers\Administrative\Broker;

date_default_timezone_set('Asia/Jakarta');

use App\Http\Controllers\AppController;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\Edd;
use App\Models\Users\Investor\AumTarget;
use App\Models\Users\Investor\Account;
use App\Models\Users\Investor\CardPriority;
use App\Models\Administrative\Notify\NotificationSetup;
use App\Models\Administrative\Notify\NotificationIntervalSetup;
use Carbon\Carbon;

class BrokerInvestorController extends AppController
{

	public function riskProfileNotif(){
    	
		$instance       ='riseprofilexpired';
		$tglCurrent 	=date('Y-m-d');
		$arr        	=array();

		$getData 	    = Investor::select('investor_id','profile_expired_date')->whereNotNull('profile_expired_date')->get();

        $getSetUp		= NotificationIntervalSetup::select('m_notification_investor_interval.*','m_notification_investor.*')
        ->join('m_notification_investor', 'm_notification_investor.category', '=', 'm_notification_investor_interval.content_category')
        ->where('m_notification_investor.category','RiskProfileExpired')->get();
        
        // dd($getSetUp);

		foreach($getData as $idx=>$value){
			
			foreach($getSetUp as $idxx=>$valueSetUp){
			    
				 if($valueSetUp->reminder == 'h+'){
					if(date('Y-m-d', strtotime('+'.$valueSetUp->count_reminder.' days', strtotime($value->profile_expired_date))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				 }
				else if($valueSetUp->reminder == 'h-'){
					if(date('Y-m-d', strtotime('-'.$valueSetUp->count_reminder.' days', strtotime($value->profile_expired_date))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
				else if($valueSetUp->reminder == 'h') {
					if(date('Y-m-d',strtotime($value->profile_expired_date)) == $tglCurrent) {	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']     ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
		    }
		}
	    $publish = $this->serviceBroker($instance,$arr);
	}

	public function cardNotif(){

		$instance       ='cardexpired';
		$tglCurrent 	=date('Y-m-d');
		$arr        	=array();

		$getData 	    = CardPriority::select('u_investors.investor_id','u_investors.cif','u_investors_card_priorities.card_expired')
		->join('u_investors','u_investors.cif', '=','u_investors_card_priorities.cif')
		->whereNotNull('u_investors_card_priorities.card_expired')->get();

        $getSetUp		= NotificationIntervalSetup::select('m_notification_investor_interval.*','m_notification_investor.*')->join('m_notification_investor', 'm_notification_investor.category', '=', 'm_notification_investor_interval.content_category')->where('content_category','ATMExpired')->get();
        
        foreach($getData as $idx=>$value){
			
			foreach($getSetUp as $idxx=>$valueSetUp){
			
				 if($valueSetUp->reminder == 'h+'){
					if(date('Y-m-d', strtotime('+'.$valueSetUp->count_reminder.' days', strtotime($value->card_expired))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							} 

							array_push($arr, $value);
						}
				 }
				else if($valueSetUp->reminder == 'h-'){
					if(date('Y-m-d', strtotime('-'.$valueSetUp->count_reminder.' days', strtotime($value->card_expired))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
				else if($valueSetUp->reminder == 'h') {
					if(date('Y-m-d',strtotime($value->card_expired)) == $tglCurrent) {	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;	
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
		    }
		}

		$publish = $this->serviceBroker($instance,$arr);
	}

    public function eddNotif(){

    	$instance       ='eddExpired';
		$tglCurrent 	=date('Y-m-d');
		$arr        	=array();

		$getData 	    = Edd::select('investor_id','edd_date')->whereNotNull('edd_date')->where('status_active','Yes')->get();

		$getSetUp		= NotificationIntervalSetup::select('m_notification_investor_interval.*','m_notification_investor.*')->join('m_notification_investor', 'm_notification_investor.category', '=', 'm_notification_investor_interval.content_category')->where('content_category','EDDExpired')->get();

		foreach($getData as $idx=>$value){
			
			foreach($getSetUp as $idxx=>$valueSetUp){

				if($valueSetUp->reminder == 'h+'){
					if(date('Y-m-d', strtotime('+'.$valueSetUp->count_reminder.' days', strtotime($value->profile_expired_date))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				 }
				else if($valueSetUp->reminder == 'h-'){
					if(date('Y-m-d', strtotime('-'.$valueSetUp->count_reminder.' days', strtotime($value->profile_expired_date))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
				else if($valueSetUp->reminder == 'h') {
					if(date('Y-m-d',strtotime($value->profile_expired_date)) == $tglCurrent) {	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']     ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
			}
		}
    }

    public function AumNotif(){

    	$instance       ='AumNotif';
		$tglCurrent 	=date('Y-m-d');
		$arr        	=array();
         
		$getData 	    = AumTarget::select('edd_date')->whereNotNull('edd_date')->where('status_active','Yes')->get();

		$getSetUp		= NotificationIntervalSetup::select('m_notification_investor_interval.*','m_notification_investor.*')->join('m_notification_investor', 'm_notification_investor.category', '=', 'm_notification_investor_interval.content_category')->where('content_category','AumNotif')->get();

		foreach($getData as $idx=>$value){
			
			foreach($getSetUp as $idxx=>$valueSetUp){

				if($valueSetUp->reminder == 'h+'){
					if(date('Y-m-d', strtotime('+'.$valueSetUp->count_reminder.' days', strtotime($value->profile_expired_date))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				 }
				else if($valueSetUp->reminder == 'h-'){
					if(date('Y-m-d', strtotime('-'.$valueSetUp->count_reminder.' days', strtotime($value->profile_expired_date))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
				else if($valueSetUp->reminder == 'h') {
					if(date('Y-m-d',strtotime($value->profile_expired_date)) == $tglCurrent) {	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']     ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
			}
		}	
    }

	public function birthDayNotif(){

		$instance   ="birthdayinvestor";
		$tglCurrent = date('m-d');
		$arr        = array();

		$getData 	= Investor::select('investor_id','date_of_birth')->whereNotNull('date_of_birth')->get();

		$getSetUp		= NotificationIntervalSetup::select('m_notification_investor_interval.*','m_notification_investor.*')
        ->join('m_notification_investor', 'm_notification_investor.category', '=', 'm_notification_investor_interval.content_category')
        ->where('m_notification_investor.category','BirthDay')->get();

        foreach($getData as $idx=>$value){
			
			foreach($getSetUp as $idxx=>$valueSetUp){

				if($valueSetUp->reminder == 'h+'){
					if(date('m-d', strtotime('+'.$valueSetUp->count_reminder.' days', strtotime($value->date_of_birth))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				 }
				else if($valueSetUp->reminder == 'h-'){
					if(date('m-d', strtotime('-'.$valueSetUp->count_reminder.' days', strtotime($value->date_of_birth))) == $tglCurrent)
						{	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']        ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
				else if($valueSetUp->reminder == 'h') {
					if(date('m-d',strtotime($value->date_of_birth)) == $tglCurrent) {	
							$value['investor_id'] =$value->investor_id;
				        	$value['title'] 	  =$valueSetUp->title;
							$value['body']  	  =$valueSetUp->body;
							$value['status']      = 0;
							$value['status_batch']= 0;
							$value['href']     ="message/notify/".$value->investor_id;
							$value['created_at']  = date('Y-m-d H:i:s');

							if($valueSetUp['is_mail']=='t'){
							  $value['is_mail'] ='Yes';
							  $value['category_mail'] =$valueSetUp->category;
							}
							else{
							  $value['is_mail'] ='No';
							}

							array_push($arr, $value);
						}
				}
			}
		}

	    $publish = $this->serviceBroker($instance,$arr);
	}
}