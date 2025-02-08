<?php

namespace App\Http\Controllers\Administrative\Notify;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Notify\NotificationUsers;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
//use Auth;

class UsersNotificationController extends AppController
{

	public function get_data(){
        
        $auth=Auth::guard('admin')->id();

		$data      = NotificationUsers::select('h_notification_users.*')
                    ->where('h_notification_users.users_on',$auth)
                    ->where('h_notification_users.notif_status_batch','f')
                    ->get();

        $badge     = NotificationUsers::select('h_notification_users.notif_status_batch')
                    ->where('h_notification_users.users_on',$auth)
                    ->where('h_notification_users.notif_status_batch','f')
                    ->get();

        // return $this->app_response('Notify', ['list' => $data]);
        return $this->app_response('Notification', ['list' => $data,'badge'=>$badge,'key'=>'id']);

	}

	/**
     * @param $val
     * @return void
     */
	private function saveToUser($val){	
                DB::table('h_notification_users')->insert([
                 'notif_title'  =>"Registered",
                 'notif_desc'   => "Investor have registered in application",
                 'notif_status' => 'f',
                 'notif_status_batch' => 'f',
                 'notif_href'         => 'notify/investor/'.$val['user_id'],
                 'created_at'         => date('Y-m-d H:i:s'),
                 'users_on'           => $val['user_id']
                ]);
    }

    /**
     * @param $arr
     */
    public function notificationReceivedAll($arr){    

        foreach ($arr as $idx=>$value) {
            $this->saveToUser($value);
        };
    }  

    public function notif_read_user($id){
        try {
        
               $auth=Auth::guard('admin')->id();
              
               $data         = NotificationUsers::where('id',$id)->update(['notif_status'=>'t','notif_status_batch'=>'t']);
               $notif_status = NotificationUsers::select('h_notification_users.notif_status')
                    ->where('h_notification_users.user_on',$auth)
                    ->where('h_notification_users.notif_status','f')
                    ->get();
               return $this->app_response('Notification', ['state' =>'succes','data'=>$notif_status]);
        
        }catch (QueryException $e) {
                $message = $e;
                return response()->json($message, 500);
        }
    }
}





