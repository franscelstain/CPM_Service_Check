<?php

namespace App\Http\Controllers\Administrative\Broker;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
// use App\Models\Administrative\Config\Config;
use App\Models\Administrative\Mobile\MobileContent;
use App\Models\Administrative\Notification\Investor as NotifInvestor;
use App\Models\Administrative\Notification\Notification;
use App\Models\Administrative\Notification\NotificationInterval;
use App\Models\Administrative\Notification\User as NotifUser;
use App\Models\SA\Assets\Portfolio\Performance;
use App\Models\SA\Assets\Portfolio\Risk;
use App\Models\Transaction\TransactionHistory;
use App\Models\Users\Category;
use App\Models\Users\Investor\CardPriority;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\Edd;
use App\Models\Users\User;
use Illuminate\Http\Request;

class MessagesController extends AppController
{
    private function __notif($code, $interval = true)
    {
        try {
            $date       = [];
            $setNotif   = Notification::where([['is_active', 'Yes'], ['notif_code', $code]])
                        ->where(function($qry){
                            $qry->where('notif_web', true)
                                ->orWhere('notif_mail', true)
                                ->orWhere('notif_mobile', true);
                        })
                        ->orderBy('created_at', 'desc')
                        ->first();
            if (!empty($setNotif->id) && $interval)
            {
                $arr_intvl  = [];
                $interval   = NotificationInterval::where([['notif_id', $setNotif->id], ['is_active', 'Yes']])->orderBy('continuous', 'asc')->get();
                
                foreach ($interval as $int)
                {
                    $rmd = $int->reminder;
                    
                    if ($rmd == 'H' && !in_array($rmd, $arr_intvl))
                    {
                        $date[] = date('Y-m-d');
                        $arr_intvl[$rmd] = $int->count_reminder;
                    }
                    else
                    {
                        if ($int->continuous)
                        {
                            for ($i = $int->count_reminder; $i >= 1; $i--)
                            {
                                if (empty($arr_intvl[$rmd]) || !in_array($i, $arr_intvl[$rmd]))
                                {
                                    $opr    = $rmd == 'H-' ? '+' : '-';
                                    $date[] = date('Y-m-d', strtotime($opr.$i. ' days'));
                                }
                            }
                        }
                        elseif (empty($arr_intvl[$rmd]) || !in_array($int->count_reminder, $arr_intvl[$rmd]))
                        {
                            $opr    = $rmd == 'H-' ? '+' : '-';
                            $date[] = date('Y-m-d', strtotime($opr.$int->count_reminder. ' days'));
                            $arr_intvl[$rmd][] = $int->count_reminder;
                        }
                    }
                }
            }
            return (object) ['date' => $date, 'setup' => $setNotif];
        }
        catch (\Exception $e)
        {
            \Log::error('Error in __notif: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }
    
    private function __notif_assign_to($cat)
    {
        try {
            $assign = [];

            if(!empty($cat)) {
                $data   = Category::where('is_active', 'Yes')->whereIn('usercategory_id', $cat)->get();

                foreach ($data as $dt)
                {
                    if (in_array($dt->usercategory_name, ['Investor', 'Sales']))
                        $assign['inv'][$dt->usercategory_name] = $dt->usercategory_id;
                    else
                        $assign['usr'][] = $dt->usercategory_id;
                }
            }    
            
            return $assign;  
        }
        catch (\Exception $e)
        {
            \Log::error('Error in __notif_assign_to: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }      
    }
    
    public function __notif_batch()
    {
        try
        {
            $user = $this->auth_user();
            if ($user->usercategory_name == 'Investor') {
                NotifInvestor::where('investor_id', $user->id)->update(['notif_batch' => true, 'notif_read' => true]);
                // NotifInvestor::where('investor_id', $user->investor_id)->update(['notif_batch' => true, 'notif_read' => true]);
            } else {
                NotifUser::where('user_id', $user->id)->update(['notif_batch' => true, 'notif_read' => true]);
            }
            return $this->app_response('Notif Batch', 'Notif successfully updated');
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function __notif_all_read()
    {
        try
        {
            $user = $this->auth_user();
            if ($user->usercategory_name == 'Investor') {
                NotifInvestor::where('investor_id', $user->id)->update(['notif_read' => true, 'notif_web' => true]);
            } else {
                NotifUser::where('user_id', $user->id)->update(['notif_read' => true, 'notif_web' => true]);
            }
            return $this->app_response('Notif Batch', 'Notif successfully updated');
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function __notif_bell()
    {
        try
        {
            $auth = $this->auth_user();
            if ($auth->usercategory_name == 'Investor')
            {
                $data   = NotifInvestor::where([['investor_id', $auth->id], ['is_active', 'Yes'], ['notif_web', false]])->orderBy('notif_batch')->orderBy('notif_read')->orderBy('created_at', 'desc')->limit(5)->get();
                $batch  = NotifInvestor::where([['investor_id', $auth->id], ['is_active', 'Yes'], ['notif_batch', false]])->count();
            }
            else
            {
                $data   = NotifUser::where([['user_id', $auth->id], ['is_active', 'Yes'], ['notif_web', false]])->orderBy('notif_batch')->orderBy('notif_read')->orderBy('created_at', 'desc')->limit(20)->get();
                $batch  = NotifUser::where([['user_id', $auth->id], ['is_active', 'Yes'], ['notif_batch', false]])->count();
            }
            
            if ($batch > 0)
            {
                $this->__notif_send();
            }
            
            return $this->app_response('Notify', ['notif' => $data, 'batch' => $batch]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }
    
    private function __notif_email($notif, $dt, $mail = [])
    {
        try {
            $to_mail    = !empty($dt->email) ? $dt->email : '';
            if ($notif->notif_mail && !empty($notif->email_content_id) && !empty($to_mail))
            {
                $new    = array_map(function($q) use ($dt) { return !empty($dt->$q) ? $dt->$q : ''; }, $mail);
                $mail   = array_merge(['content_id' => $notif->email_content_id, 'to' => $to_mail], ['new' => $new]);
                $this->app_sendmail($mail);
            }
            return $mail;
        }
        catch (\Exception $e)
        {
            \Log::error('Error in __notif_email: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }
    
    private function __notif_publish($qry, $notif, $role = [])
    {
        try {
            $web    = $mobile = $email = [];
            $count  = !empty($role->qry) && $role->qry == 'not' ? count($qry) : $qry->count();
            if ($count > 0)
            {   
                if(!empty($notif->assign_to)) {    
                    $user       = $this->__notif_assign_to(json_decode($notif->assign_to));
                    $assign_to  = $user['inv'] ? $user['inv'] : array();
                    foreach ($qry as $q)
                    {
                        if (array_key_exists('Investor', $assign_to))
                        {  
                            $web['Investor'][]      = $this->__notif_web($notif, $q, $q->investor_id, $role->web, 'Investor');
                            $email['Investor'][]    = $this->__notif_email($notif, $q, $role->email->column);
                        }
                        
                        if (array_key_exists('Sales', $assign_to))
                        {   

                            if (isset($q->sales_id))
                            {
                                if (!empty($q->sales_id))
                                {
                                    $web['Sales'][]     = $this->__notif_web($notif, $q, $q->sales_id, $role->web);
                                    $email['Sales'][]   = $this->__notif_email($notif, $q, $role->email->column);
                                }
                            }
                            else
                            {
                                $user['usr'][] = $assign_to['Sales']; 
                            }
                        }
                        
                        if (!empty($user['usr']))
                        {
                            $qry_user = User::join('u_users_categories as b', 'b.usercategory_id', '=', 'u_users.usercategory_id')->whereIn('u_users.usercategory_id', $user['usr'])->where([['u_users.is_active', 'Yes'], ['b.is_active', 'Yes']])->get();
                            
                            foreach ($qry_user as $qu)
                            {
                                $web[$qu->usercategory_name][]      = $this->__notif_web($notif, $q, $qu->user_id, $role->web);
                                $email[$qu->usercategory_name][]    = $this->__notif_email($notif, $qu, $role->email->column);
                            }
                        }
                    }
                } else {
                    foreach ($qry as $q)
                    {   
                        if(!empty($q->investor_id)) 
                        {
                        $email['Investor'][]    = $this->__notif_email($notif, $q, $role->email->column);
                        $web['Investor'][]      = $this->__notif_web($notif, $q, $q->investor_id, $role->web, 'Investor');
                        }
                        
                        if(!empty($q->user_id)) { 
                        $web['Sales'][]     = $this->__notif_web($notif, $q, $q->user_id, $role->web);
                        $email['Sales'][]   = $this->__notif_email($notif, $q, $role->email->column);
                        }    

                        if(!empty($q->sales_id)) { 
                        $web['Sales'][]     = $this->__notif_web($notif, $q, $q->sales_id, $role->web);
                        $email['Sales'][]   = $this->__notif_email($notif, $q, $role->email->column);
                        }    
                    }    
                }    
            }
            return ['web' => $web, 'mobile' => $mobile, 'email' => $email];
        }
        catch (\Exception $e)
        {
            \Log::error('Error in __notif_pubish: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    private function __notif_publish_2($qry, $notif, $role = [])
    {
        $web   = $mobile = $email = [];
        $count = count($qry);    

        if ($count > 0)
        {
            $user       = $this->__notif_assign_to(json_decode($notif->assign_to));
            $assign_to  = $user['inv'];
            foreach ($qry as $q)
            {                
                if (array_key_exists('Investor', $assign_to))
                {
                    $web['Investor'][]      = $this->__notif_web($notif, (object) $q, $q['investor_id'], $role->web, 'Investor');
                    $email['Investor'][]    = $this->__notif_email($notif, (object)  $q, $role->email->column);
                }
                                
            }
        }
        return ['web' => $web, 'mobile' => $mobile, 'email' => $email];
    }

    private function __notif_replace($obj, $role, $message)
    {
        if (!empty($role))
        {
            foreach ($role as $msg_k => $msg_v) 
            {
                $rpl    = $msg_k == '{time_status}' ? strtotime($this->app_date()) >= strtotime($obj->$msg_v) ? 'sudah' : 'akan' : $obj->$msg_v;
                $message = str_replace($msg_k, $rpl, $message);
            }
        }
        return $message;
    }
    
    private function __notif_send()
    {
        $user = $this->auth_user();
        if ($user && isset($user->usercategory_name)) {
            if ($user->usercategory_name == 'Investor') {
                NotifInvestor::where('investor_id', $user->id)->update(['notif_send' => true, 'notif_read' => true]);
            } else {
                NotifUser::where('user_id', $user->id)->update(['notif_send' => true, 'notif_read' => true]);
            }
        } else {
            // Handle jika $user tidak valid atau properti usercategory_name tidak tersedia
            throw new \Exception("Invalid user or user category");
        }
    }
    
    public function __notif_unsent()
    {
        try
        {
            $user = $this->auth_user();
            if ($user->usercategory_name == 'Investor') {
                $data = NotifInvestor::where([['investor_id', $user->id], ['is_active', 'Yes'], ['notif_batch', false], ['notif_send', false]])->orderBy('created_at', 'desc');
            } else {
                $data = NotifUser::where([['user_id', $user->id], ['is_active', 'Yes'], ['notif_batch', false], ['notif_send', false]])->orderBy('created_at', 'desc');
            }
            $num    = $data->count();
            $notif  = $data->get();
            
            if ($num > 0) {
                $this->__notif_send();
            }
            
            return $this->app_response('Notify', ['notif' => $notif, 'batch' => $num]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }
    
    public function __notif_user(Request $request)
    {
        try
        {
            $auth   = $this->auth_user();
            $limit  = !empty($request->limit) ? $request->limit : 10;
            $page   = !empty($request->page) ? $request->page : 1;
            
            if ($auth->usercategory_name == 'Investor') {
                $data = NotifInvestor::where([['investor_id', $auth->id], ['is_active', 'Yes']])->orderBy('notif_batch')->orderBy('notif_read')->orderBy('created_at', 'desc');
            } else {
                $data = NotifUser::where([['user_id', $auth->id], ['is_active', 'Yes']])->orderBy('notif_batch')->orderBy('notif_read')->orderBy('created_at', 'desc');
            }
            return $this->app_response('Notification', $data->paginate($limit, ['*'], 'page', $page));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }
    
    private function __notif_web($notif, $obj, $id, $role = [], $user = '')
    {
        try {
            $data = [];
            if ($notif->notif_web)
            {
                $msg_text   = !empty($role->message) ? $this->__notif_replace($obj, $role->message, $notif->text_message) : $notif->text_message;
                $title      = !empty($role->title) ? $this->__notif_replace($obj, $role->title, $notif->title) : $notif->title; 
                $data       = ['notif_title'    => $title,
                            'notif_desc'     => $msg_text,
                            'notif_link'     => $notif->redirect,
                            'created_by'     => 'System',
                            'created_host'   => '::1'
                            ];
                
                if ($user == 'Investor') {
                    NotifInvestor::create(array_merge($data, ['investor_id' => $id]));
                } else {
                    NotifUser::create(array_merge($data, ['user_id' => $id]));
                }
            }
            return $data;
        }
        catch (\Exception $e)
        {
            \Log::error('Error in __notif_web: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }
    
    public function atm_expired($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if ($user == 'Sales')
            {
                $notif  = $this->__notif('ATMExpiredSales');
            }
            else
            {
                $notif  = $this->__notif('ATMExpiredInvestor');
            }

            if (!empty($notif->date))
            {
                foreach($notif->date as $dte)
                {
                    switch ($user)
                    {
                        case 'Sales':
                            $data   = Investor::select('u_investors.investor_id', 'u_investors.sales_id', 'c.email', 'b.card_expired', 'u_investors.fullname', 'u_investors.cif', 'c.fullname as sales_name')
                                    ->join('u_investors_card_priorities as b', 'b.cif', '=', 'u_investors.cif')->where('b.is_active', 'Yes')
                                     ->join('u_users as c','u_investors.sales_id', '=', 'c.user_id')->where('c.is_active', 'Yes');
                                      
                            $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'card_expired', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['sales_name', 'fullname', 'cif',  'card_expired']]]));
                            break;
                        default:
                            $data   = Investor::select('u_investors.investor_id', 'u_investors.email', 'b.card_expired', 'u_investors.fullname', 'u_investors.cif')
                                    ->join('u_investors_card_priorities as b', 'b.cif', '=', 'u_investors.cif')->where('b.is_active', 'Yes')
                                    ->where('u_investors.valid_account', 'Yes');
                            $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' => 'card_expired']], 'email' => ['column' => ['fullname', 'card_expired']]]));
                    }
                    $data   = $data->where([['u_investors.is_active', 'Yes'], ['b.card_expired', $dte]])->get();
                    $result = $this->__notif_publish($data, $notif->setup, $role);
                }
            }
            return $this->app_response('ATM Expired', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function birthday($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('BirthdaySales');
            }else{
                $notif  = $this->__notif('BirthdayInvestor');
            }

            if (!empty($notif->date))
            {
                foreach($notif->date as $dte)
                {

                    switch ($user) {
                        case 'Sales':
                            $data = Investor::select('u_investors.investor_id', 'u_investors.date_of_birth', 'u_investors.sales_id', 'b.email', 'u_investors.fullname', 'u_investors.cif', 'b.fullname as sales_name')
                                    ->join('u_users as b', 'u_investors.sales_id', '=', 'b.user_id')
                                    ->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes']]);
                            $role   = ['web' =>['message' => ['{time_status}' =>  'date_of_birth', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['sales_name', 'fullname', 'cif']]];
                            break;
                        default:
                            $data   = Investor::select('investor_id', 'email', 'date_of_birth', 'fullname', 'cif')
                                        ->where('is_active', 'Yes')->where('valid_account', 'Yes');
                            $role   = ['web' =>['message' => ['{fullname}' => 'fullname']],'email' => ['column' => ['fullname', 'cif', 'date_of_birth']]];
                    }

                    $data =  $data->whereMonth('u_investors.date_of_birth', date('m', strtotime($dte)))
                            ->whereDay('u_investors.date_of_birth', date('d', strtotime($dte)))
                            ->get();
                    $role   = $role;
                    $result = $this->__notif_publish($data, $notif->setup, json_decode(json_encode($role)));
                }
            }
            return $this->app_response('Birthday', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function edd_expired()
    {
        try
        {   
            ini_set('max_execution_time', '3600');
            $result = [];
            $notif  = $this->__notif('EDDExpired');

            if (!empty($notif->date))
            {
                foreach($notif->date as $dte)
                {   
                    $result[] = $dte;
                    $data   =  Investor::select('u_investors.sales_id','c.user_id','c.email', 'b.edd_date', 'u_investors.fullname', 'u_investors.cif','c.fullname as sales_name')
                            ->join('u_investors_edd as b', 'u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes')
                            ->join('u_users as c', 'u_investors.sales_id', '=', 'c.user_id')
                            ->where([['u_investors.is_active', 'Yes'], ['c.is_active', 'Yes']])
                            ->whereIn('b.edd_date', $notif->date)
                            ->get();
                             // return $this->app_response('Edd Expired', $data);
                    $role   = json_decode(json_encode(['web'=>['message' => ['{time_status}' =>  'edd_date', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['sales_name', 'cif', 'fullname']]]));
                     // $notif->setup->assign_to = !empty($notif->setup->assign_to) ? $notif->setup->assign_to : '';
                    $result = $this->__notif_publish($data, $notif->setup, $role);                    
                }
            }
            return $this->app_response('Edd Expired', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function risk_profile_expired($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('RiskProfileExpiredSales');
            }else{
                $notif  = $this->__notif('RiskProfileExpiredInvestor');
            }

            if (!empty($notif->date))
            {
                foreach($notif->date as $dte)
                {
                    switch ($user) {
                        case 'Sales':
                            $data   = Investor::select('u_investors.sales_id', 'u_investors.profile_expired_date', 'b.email', 'u_investors.cif', 'u_investors.fullname', 'c.profile_name' , 'b.fullname as sales_name')
                                    ->join('u_users as b', 'u_investors.sales_id', '=', 'b.user_id')
                                    ->join('m_risk_profiles as c','u_investors.profile_id', '=', 'c.profile_id')->where('c.is_active', 'Yes')
                                    ->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes']]);
                            $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' => 'profile_expired_date', '{profile_expired}' => 'profile_expired_date', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['sales_name', 'fullname', 'cif', 'profile_name', 'profile_expired_date']]]));
                            break;
                        default:
                            $data = Investor::select('investor_id', 'profile_expired_date', 'sales_id', 'email', 'cif', 'fullname')
                                    ->join('m_risk_profiles as b', 'u_investors.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes')
                                    ->where('u_investors.is_active', 'Yes')->where('u_investors.valid_account', 'Yes');
                            $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'profile_expired_date', '{profile_expired}' => 'profile_expired_date']],'email' => ['column' => ['fullname', 'profile_expired_date']]]));
                    }

                    $data   = $data->whereDate('u_investors.profile_expired_date', $dte)->get();
                    $role   = $role;
                    $result = $this->__notif_publish($data, $notif->setup, $role);
                }
            }
            return $this->app_response('Risk Profile Expired', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function min_aum($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('AumMinSales');
            }else{
                $notif  = $this->__notif('AumMinInvestor');
            }
            if (!empty($notif->setup))
            {
                switch ($user) {
                    case 'Sales':
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.fullname', 'u_investors.cif','u_investors.sales_id', 'e.aum_lastdate', 'b.target_aum_amount', 'c.email', 'b.current_aum_amount','c.fullname as sales_name')
                            ->join('u_investors_aum_provision as b', 'u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes')
                            ->join('u_users as c', 'u_investors.sales_id', '=', 'c.user_id')->where('c.is_active', 'Yes')
                            // ->join('u_investors_accounts as d', 'u_investors.investor_id', '=', 'd.investor_id')->where('d.is_active', 'Yes')
                            ->join('u_investors_aum_provision as e', 'u_investors.investor_id', '=', 'e.investor_id')->where('e.is_active', 'Yes');
                        $role   = json_decode(json_encode(['web' =>['message' => ['{TargetAUM}' =>  'target_aum_amount', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['sales_name', 'fullname', 'cif', 'aum_lastdate']]]));
                        break;
                    
                    default:
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.fullname','u_investors.cif','u_investors.email', 'd.aum_lastdate', 'b.target_aum_amount', 'b.current_aum_amount')
                                ->join('u_investors_aum_provision as b', 'u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes')
                                // ->join('u_investors_accounts as c', 'u_investors.investor_id', '=', 'c.investor_id')->where('c.is_active', 'Yes')
                                ->join('u_investors_aum_provision as d', 'u_investors.investor_id', '=', 'd.investor_id')
                                ->where('d.is_active', 'Yes')->where('u_investors.valid_account', 'Yes'); 
                        $role   = json_decode(json_encode(['web' =>['message' => ['{TargetAUM}' =>  'target_aum_amount']], 'email' => ['column' => ['fullname', 'cif']], 'email' => ['column' => ['fullname', 'aum_lastdate']]]));
                }

                $data = $data->where([['u_investors.is_active', 'Yes'], ['b.aum_lastdate', $notif->date]])
                    ->whereRaw('b.current_aum_amount < b.target_aum_amount')
                    ->get();
                $role   = $role;
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Aum Investor kurang dari ketentuan', $result); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }
    // public function min_aum($user)
    // {
    //     try
    //     {
    //         $user   = ucwords(str_replace('-', ' ', $user));
    //         $result = [];
    //         if($user == 'Sales')
    //         {
    //             $notif  = $this->__notif('AumMinSales');
    //         }else{
    //             $notif  = $this->__notif('AumMinInvestor');
    //         }

    //         if (!empty($notif->date))
    //         {
    //             foreach($notif->date as $dte)
    //             {
    //                 switch ($user) {
    //                     case 'Sales':
    //                         $data   = Investor::select('u_investors.investor_id', 'u_investors.fullname', 'u_investors.cif','u_investors.sales_id', 'b.aum_lastdate', 'b.target_aum_amount', 'c.email', 'd.account_no')
    //                             ->leftJoin('u_investors_aum_provision as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
    //                             ->leftJoin('u_users as c', function($qry) { return $qry->on('u_investors.sales_id', '=', 'c.user_id')->where('c.is_active', 'Yes'); })
    //                             ->leftJoin('u_investors_accounts as d', function($qry) { return $qry->on('u_investors.investor_id', '=', 'd.investor_id')->where('d.is_active', 'Yes'); });
    //                         $role   = json_decode(json_encode(['web' =>['message' => ['{target_aum}' =>  'aum_lastdate', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['fullname', 'cif', 'account_no']]]));
    //                         break;
                        
    //                     default:
    //                         $data   = Investor::select('u_investors.investor_id', 'u_investors.email', 'b.aum_lastdate', 'b.target_aum_amount', 'c.account_no')
    //                                 ->leftJoin('u_investors_aum_provision as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
    //                                 ->leftJoin('u_investors_accounts as c', function($qry) { return $qry->on('u_investors.investor_id', '=', 'c.investor_id')->where('c.is_active', 'Yes'); });
    //                         $role   = json_decode(json_encode(['web' =>['message' => ['{target_aum}' =>  'aum_lastdate']], 'email' => ['column' => ['fullname', 'cif', 'account_no']], 'email' => ['column' => ['fullname', 'cif', 'account_no']]]));
    //                 }
    //                 $data = $data->where([['u_investors.is_active', 'Yes']])
    //                     ->whereIn('b.aum_lastdate', $dte)
    //                     // ->orWhere('b.aum_lastdate', '=', $this->app_date())
    //                     ->get();
    //                 $role   = $role;
    //                 $result = $this->__notif_publish($data, $notif->setup, $role);
    //             }
    //         }
    //         return $this->app_response('Aum Investor kurang dari ketentuan', $result); 
    //     }
    //     catch(\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     } 
    // }
    
    public function managed_deposito($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('DepositoSales');
            }else{
                $notif  = $this->__notif('DepositoInvestor');
            }

            if (!empty($notif->setup))
            {
                switch ($user) {
                    case 'Sales':
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.sales_id', 'c.email', 'b.account_no', 'b.due_date', 'b.outstanding_date', 'u_investors.cif', 'u_investors.fullname', 'c.fullname as sales_name')
                                ->join('t_assets_outstanding as b', 'u_investors.investor_id', '=', 'b.investor_id')
                                ->join('u_users as c', 'u_investors.sales_id', '=', 'c.user_id')->where('c.is_active', 'Yes');
                                //->leftJoin('t_assets_outstanding as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                                // ->leftJoin('u_users as c', function($qry) { return $qry->on('u_investors.sales_id', '=', 'c.user_id')->where('c.is_active', 'Yes'); });
                        $role   = json_decode(json_encode(['web' =>['message' => ['{due_date}' =>  'due_date', '{account_no}' => 'account_no', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['sales_name', 'fullname', 'cif', 'account_no', 'due_date']]]));
                        break;
                    
                    default:
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.email', 'b.account_no', 'b.due_date', 'u_investors.cif', 'b.outstanding_date', 'u_investors.fullname')
                                //->leftJoin('t_assets_outstanding as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); });
                               ->join('t_assets_outstanding as b', 'u_investors.investor_id', '=', 'b.investor_id')
                               ->where('u_investors.valid_account', 'Yes');
                         $role  = json_decode(json_encode(['web' =>['message' => ['{due_date}' =>  'due_date', '{account_no}' => 'account_no']], 'email' => ['column' => ['fullname', 'account_no', 'due_date']]]));
                }
                
                $data   = $data->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes'], ['b.outstanding_date', $this->app_date()]])
                        ->whereIn('b.due_date', $notif->date)
                        // ->orWhere('b.due_date', '=', $this->app_date())
                        ->get();
                        // return $this->app_response('Expired Deposito Investor on sales', $data); 
                $role   = $role;
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Expired Deposito Investor on sales', $result); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function managed_nav($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('ManagedNavSales');
            }else{
                $notif  = $this->__notif('ManagedNavInvestor');
            }

            if (!empty($notif->setup))
            {
                switch ($user) {
                    case 'Sales':
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.sales_id', 'c.email', 'b.account_no', 'b.due_date')
                            ->leftJoin('t_assets_outstanding as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                            ->join('u_users as c', 'u_investors.sales_id', '=', 'c.user_id');
                             $data   = AssetOutstanding::select('c.return_1day', 'd.*', 'b.*','c.*', 'e.sales_id', 'f.email')
                            ->join('m_products as b', 't_assets_outstanding.product_id', '=', 'b.product_id')
                            ->join('m_products_period as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                            ->join('t_goal_investment as d', 'd.investor_id', '=', 't_assets_outstanding.investor_id')
                            ->join('u_investors as e', 't_assets_outstanding.investor_id', '=', 'e.investor_id')
                            ->join('u_users as f','e.sales_id', '=', 'b.user_id')->where('f.is_active', 'Yes');
                        break;
                    
                    default:
                        $data   = AssetOutstanding::select('c.return_1day', 'd.*', 'b.*', 'c.*', 'e.is_active', 'e.email')
                                ->join('m_products as b', 't_assets_outstanding.product_id', '=', 'b.product_id')
                                ->join('m_products_period as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                                ->join('t_goal_investment as d', 'd.investor_id', '=', 't_assets_outstanding.investor_id')
                                ->join('u_investors as e', 't_assets_outstanding.investor_id', '=', 'e.investor_id')
                                ->where('e.valid_account', 'Yes');
                        break;
                }

                $data   = $data->where([['c.is_active', 'Yes'], ['b.is_active', 'Yes'], ['t_assets_outstanding.is_active', 'Yes'], ['d.is_active', 'Yes'], ['u_investors.is_active', 'Yes'], ['t_assets_outstanding.outstanding_date', $this->app_date()], ['c.return_1day', '<=', -2]])
                        ->get();

                $result = $this->__notif_publish($data, $notif->setup);
            }
            return $this->app_response('Managed Nav', $result);        
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function performance($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $risk = $result = $role = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('PerformanceSales', false);
            }else{
                $notif  = $this->__notif('Performance', false);
            }

            if (!empty($notif->setup))
            {
                switch ($user)
                {
                    case 'Sales':
                        $data = Investor::select('u_investors.sales_id', 'u_investors.investor_id','u_investors.sid','b.portfolio_performance_date', 'b.portfolio_risk_id', 'b.portfolio_id', 'c.portfolio_risk_name', 'd.email', 'u_investors.cif', 'u_investors.fullname', 'c.portfolio_risk_name', 'e.goal_title', 'd.fullname as sales_name')
                            ->join('m_portfolio_performance as b', 'u_investors.investor_id', '=', 'b.investor_id')
                            ->join('m_portfolio_risk as c', 'b.portfolio_risk_id', '=', 'c.portfolio_risk_id')
                            ->join('u_users as d', 'u_investors.sales_id', '=', 'd.user_id')
                            ->join('t_goal_investment as e', 'b.portfolio_id', '=', 'e.portfolio_id')->where('e.is_active', 'Yes')
                            ->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes'], ['d.is_active', 'Yes']]);
                        break;
                    
                    default:
                        $data = Investor::select('u_investors.investor_id', 'u_investors.email', 'u_investors.cif','b.portfolio_performance_date', 'b.portfolio_risk_id', 'b.portfolio_id', 'c.portfolio_risk_name', 'u_investors.fullname', 'e.goal_title')
                            ->join('m_portfolio_performance as b', 'u_investors.investor_id', '=', 'b.investor_id')
                            ->join('m_portfolio_risk as c', 'b.portfolio_risk_id', '=', 'c.portfolio_risk_id')
                            ->join('t_goal_investment as e', 'b.portfolio_id', '=', 'e.portfolio_id')->where('e.is_active', 'Yes')
                            ->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                            ->where('u_investors.valid_account', 'Yes');
                }
                
                $pid    = '';
                $data   = $data->get();
                $cek_investor_id = array();
                foreach ($data as $dt) 
                {         
                    if(!in_array($dt->investor_id, $cek_investor_id))
                    {
                        if (!empty($dt->portfolio_id))
                        {
                            $last_portfolioperformance =  Performance::select('portfolio_performance_id', 'portfolio_performance_date', 'portfolio_risk_id')->where([['investor_id', $dt->investor_id], ['portfolio_id', $dt->portfolio_id]])->orderBy('portfolio_performance_date', 'DESC')->orderBy('portfolio_performance_id', 'DESC')->first();

                            if(!empty($last_portfolioperformance->portfolio_performance_date))
                            {
                                $last_one_month_portfolio =  date('Y-m-d', strtotime('-1 months', strtotime($last_portfolioperformance->portfolio_performance_date)));
                                
                                $last_date_month =  date('m', strtotime($last_one_month_portfolio));
                                $last_date_year  =  date('Y', strtotime($last_one_month_portfolio));
                                $last_one_month =  Performance::select('portfolio_performance_id', 'portfolio_performance_date', 'portfolio_risk_id')->where([['investor_id', $dt->investor_id], ['portfolio_id', $dt->portfolio_id]])->whereMonth('portfolio_performance_date', $last_date_month)->whereYear('portfolio_performance_date', $last_date_year)->orderBy('portfolio_performance_date', 'DESC')->orderBy('portfolio_performance_id', 'DESC')->first();
                                // return $this->app_response('xx', $last_one_month); 
                                if(!empty($last_one_month))
                                {
                                    // return $this->app_response('xx', $last_one_month); 
                                    if($last_portfolioperformance->portfolio_risk_id >  $last_one_month->portfolio_risk_id)
                                    {
                                        $last_portfolio_risk_month = Risk::select('portfolio_risk_name as risk_month')->where([['portfolio_risk_id', $last_portfolioperformance->portfolio_risk_id], ['is_active', 'Yes']])->first();

                                        $last_portfolio_risk_min_one_month = Risk::select('portfolio_risk_name as risk_one_month')->where([['portfolio_risk_id', $last_one_month->portfolio_risk_id], ['is_active', 'Yes']])->first();
                                        switch ($user)
                                        {
                                            case 'Sales':
                                                $data_mesg = Investor::select('u_investors.sales_id', 'u_investors.investor_id','u_investors.sid','b.portfolio_performance_date', 'b.portfolio_risk_id', 'b.portfolio_id', 'c.portfolio_risk_name', 'd.email', 'u_investors.cif', 'u_investors.fullname as fullname', 'd.fullname as sales_name','c.portfolio_risk_name', 'e.goal_title')
                                                    ->join('m_portfolio_performance as b', 'u_investors.investor_id', '=', 'b.investor_id')
                                                    ->join('m_portfolio_risk as c', 'b.portfolio_risk_id', '=', 'c.portfolio_risk_id')
                                                    ->join('u_users as d', 'u_investors.sales_id', '=', 'd.user_id')
                                                    ->join('t_goal_investment as e', 'b.portfolio_id', '=', 'e.portfolio_id')->where('e.is_active', 'Yes')
                                                    ->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes'], ['d.is_active', 'Yes'], ['u_investors.investor_id',$dt->investor_id], ['b.portfolio_id', $dt->portfolio_id]])->first();
                                                $role   = ['web' =>['message' => ['{portfolio_id}' =>  'portfolio_id', '{cif}' => 'cif', '{portfolio_risk_name}' => 'risk_month', '{fullname}' => 'fullname']], 'email' => ['column' => ['sales_name', 'cif', 'fullname', 'portfolio_id', 'goal_title', 'risk_month','risk_one_month']]];
                                                break;
                                            
                                            default:
                                                $data_mesg = Investor::select('u_investors.investor_id', 'u_investors.email', 'u_investors.cif','b.portfolio_performance_date', 'b.portfolio_risk_id', 'b.portfolio_id', 'c.portfolio_risk_name', 'u_investors.fullname', 'e.goal_title')
                                                    ->join('m_portfolio_performance as b', 'u_investors.investor_id', '=', 'b.investor_id')
                                                    ->join('m_portfolio_risk as c', 'b.portfolio_risk_id', '=', 'c.portfolio_risk_id')
                                                    ->join('t_goal_investment as e', 'b.portfolio_id', '=', 'e.portfolio_id')->where('e.is_active', 'Yes')
                                                    ->where([['u_investors.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes'], ['u_investors.investor_id',$dt->investor_id], ['b.portfolio_id', $dt->portfolio_id]])
                                                    ->where('u_investors.valid_account', 'Yes')
                                                    ->first();
                                                $role   = ['web' =>['message' => ['{portfolio_id}' =>  'portfolio_id', '{goal_title}' => 'goal_title', '{fullname}' => 'fullname']], 'email' => ['column' => ['fullname', 'portfolio_id', 'goal_title', 'risk_month', 'risk_one_month']]];
                                        }
                                        $uid    = $user == 'Sales' ? 'sales_id' : 'investor_id'; 
                                        $risk[] = (object) [$uid => $data_mesg->$uid, 'email' => $data_mesg->email, 'cif' => $data_mesg->cif, 'sid' => $data_mesg->sid, 'fullname' => $data_mesg->fullname, 'sales_name' => $data_mesg->sales_name, 'risk_profile_id' => $data_mesg->portfolio_risk_id, 'portfolio_id' => $data_mesg->portfolio_id, 'risk_one_month' => $last_portfolio_risk_min_one_month->risk_one_month, 'risk_month' => $last_portfolio_risk_month->risk_month,'goal_title' => $data_mesg->goal_title];
                                    }                                                       
                                }

                            }
                        }
                        else
                        {
                            $pid  = $dt->portfolio_id;
                        }
                    }
                    
                    $cek_investor_id[] = $dt->investor_id;
                }
                $role   = json_decode(json_encode(array_merge(['qry' => 'not'], $role)));
                $result = $this->__notif_publish($risk, $notif->setup, $role);
            }
            return $this->app_response('Performance', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    /*
    public function transactionsub($user)
    {
        try
        { 
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('TransactionSubSales');
            }else{
                $notif  = $this->__notif('TransactionSubInvestor');
            }

            if (!empty($notif->setup))
            {
                switch ($user) {
                    case 'Sales':
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.sales_id', 'e.email', 'b.account_no', 'b.transaction_date', 'u_investors.cif', 'u_investors.fullname')
                            ->leftJoin('t_trans_histories as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                            ->join('m_trans_reference as c', 'b.trans_reference_id', 'c.trans_reference_id')
                            ->leftJoin('m_trans_reference as d', function($qry) { return $qry->on('b.type_reference_id', '=', 'd.trans_reference_id')->where([['d.reference_type', 'Transaction Type'], ['d.is_active', 'Yes']]); })
                            ->leftJoin('u_users as e', function($qry) { return $qry->on('u_investors.sales_id', '=', 'e.user_id')->where('e.is_active', 'Yes'); });
                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['cif', 'fullname', 'account_no', 'transaction_date']]]));
                        break;
                    
                    default:
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.email', 'b.account_no', 'b.transaction_date', 'u_investors.cif', 'u_investors.fullname')
                            ->leftJoin('t_trans_histories as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                             ->join('m_trans_reference as c', 'b.trans_reference_id', 'c.trans_reference_id')
                            ->leftJoin('m_trans_reference as d', function($qry) { return $qry->on('b.type_reference_id', '=', 'd.trans_reference_id')->where([['d.reference_type', 'Transaction Type'], ['d.is_active', 'Yes']]); });
                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ['fullname', 'account_no', 'transaction_date']]]));
                }
                
                $data   = $data->where([['u_investors.is_active', 'Yes'], ['c.is_active', 'Yes'], ['d.reference_code', 'SUB']])
                        ->whereIn('b.transaction_date', $notif->date)
                        ->orWhere('b.transaction_date', '=', $this->app_date())
                        ->get();
                         // return $this->app_response('Transaction Subscription Investor on sales', $data); 
                $role   = $role;
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Transaction Subscription Investor on sales', $result); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function transactionred($user)
    {
        try
        {
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('TransactionRedSales');
            }else{
                $notif  = $this->__notif('TransactionRedInvestor');
            }

            if (!empty($notif->setup))
            {
                switch ($user) {
                    case 'Sales':
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.sales_id', 'e.email', 'b.account_no', 'b.transaction_date', 'u_investors.cif', 'u_investors.fullname')
                            ->leftJoin('t_trans_histories as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                            ->join('m_trans_reference as c', 'b.trans_reference_id', 'c.trans_reference_id')
                            ->leftJoin('m_trans_reference as d', function($qry) { return $qry->on('b.type_reference_id', '=', 'd.trans_reference_id')->where([['d.reference_type', 'Transaction Type'], ['d.is_active', 'Yes']]); })
                            ->leftJoin('u_users as e', function($qry) { return $qry->on('u_investors.sales_id', '=', 'e.user_id')->where('e.is_active', 'Yes'); });
                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['cif', 'fullname', 'account_no', 'transaction_date']]]));
                        break;
                    
                    default:
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.email', 'b.account_no', 'b.transaction_date', 'u_investors.cif', 'u_investors.fullname')
                            ->leftJoin('t_trans_histories as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                             ->join('m_trans_reference as c', 'b.trans_reference_id', 'c.trans_reference_id')
                            ->leftJoin('m_trans_reference as d', function($qry) { return $qry->on('b.type_reference_id', '=', 'd.trans_reference_id')->where([['d.reference_type', 'Transaction Type'], ['d.is_active', 'Yes']]); });
                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ['fullname', 'account_no', 'transaction_date']]]));
                }
                
                $data   = $data->where([['u_investors.is_active', 'Yes'], ['c.is_active', 'Yes'], ['d.reference_code', 'RED']])
                        ->whereIn('b.transaction_date', $notif->date)
                        ->orWhere('b.transaction_date', '=', $this->app_date())
                        ->get();
                         // return $this->app_response('Transaction Subscription Investor on sales', $data); 
                $role   = $role;
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Transaction Redemtion Investor on sales', $result); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function transactionswt($user)
    {
        try
        {   die('tes');
            $user   = ucwords(str_replace('-', ' ', $user));
            $result = [];
            if($user == 'Sales')
            {
                $notif  = $this->__notif('TransactionSwtSales');
            }else{
                $notif  = $this->__notif('TransactionSwtInvestor');
            }

            if (!empty($notif->setup))
            {
                switch ($user) {
                    case 'Sales':
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.sales_id', 'e.email', 'b.account_no', 'b.transaction_date', 'u_investors.cif', 'u_investors.fullname')
                            ->leftJoin('t_trans_histories as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                            ->join('m_trans_reference as c', 'b.trans_reference_id', 'c.trans_reference_id')
                            ->leftJoin('m_trans_reference as d', function($qry) { return $qry->on('b.type_reference_id', '=', 'd.trans_reference_id')->where([['d.reference_type', 'Transaction Type'], ['d.is_active', 'Yes']]); })
                            ->leftJoin('u_users as e', function($qry) { return $qry->on('u_investors.sales_id', '=', 'e.user_id')->where('e.is_active', 'Yes'); });
                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no', '{cif}' => 'cif', '{fullname}' => 'fullname']], 'email' => ['column' => ['cif', 'fullname', 'account_no', 'transaction_date']]]));
                        break;
                    
                    default:
                        $data   = Investor::select('u_investors.investor_id', 'u_investors.email', 'b.account_no', 'b.transaction_date', 'u_investors.cif', 'u_investors.fullname')
                            ->leftJoin('t_trans_histories as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                             ->join('m_trans_reference as c', 'b.trans_reference_id', 'c.trans_reference_id')
                            ->leftJoin('m_trans_reference as d', function($qry) { return $qry->on('b.type_reference_id', '=', 'd.trans_reference_id')->where([['d.reference_type', 'Transaction Type'], ['d.is_active', 'Yes']]); });
                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ['fullname', 'account_no', 'transaction_date']]]));
                }
                
                $data   = $data->where([['u_investors.is_active', 'Yes'], ['c.is_active', 'Yes'], ['d.reference_code', 'SWTIN']])
                        ->whereIn('b.transaction_date', $notif->date)
                        ->orWhere('b.transaction_date', '=', $this->app_date())
                        ->get();
                         // return $this->app_response('Transaction Subscription Investor on sales', $data); 
                $role   = $role;
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Transaction Switching Investor on sales', $result); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }
    */

    public function transaction($transId)
    {
        try
        {  
            $data =  Investor::select('b.trans_history_id','b.portfolio_id','u_investors.investor_id', 'u_investors.sales_id', 'u_investors.email',  'u_investors.cif', 'u_investors.fullname', 'u_investors.email', 
                                      'b.transaction_date','b.account_no as investment_account_no','b.portfolio_id','b.reference_no','b.unit as unit_purchase','b.amount as amount_to_purchase','b.unit as unit_redeem','b.amount as amount_to_redeem',
                                      'c.reference_name as transaction_type','c.reference_code', 'e.branch_name as bank_transfer', 'd.account_name as bank_account_name', 'd.account_no as bank_account_no')
                     ->join('t_trans_histories as b', 'u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes')
                     ->join('m_trans_reference as c', 'b.type_reference_id', '=', 'c.trans_reference_id')
                     ->join('u_investors_accounts as d', 'b.investor_account_id', '=', 'd.investor_account_id')
                     ->join('m_bank_branches as e', 'd.bank_branch_id', '=', 'e.bank_branch_id')
                     ->join('m_bank as f', 'e.bank_id', '=', 'f.bank_id')
                     ->where([['b.trans_history_id', $transId],['b.is_active','Yes']])
                     ->get();

            //$role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ['portfolio_id', 'investment_account_no', 'cif', 'bank_transfer','bank_account_name','bank_account_no','transaction_date','transaction_type','unit_purchase','amount_to_purchase','unit_redeem','amount_to_redeem']]]));

            if(!empty($data[0]->reference_code)) {
                if($data[0]->reference_code == 'SUB') {
                    //$role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ["fullname","transaction_date","transaction_type","portfolio_id","investment_account_no","unit_purchase","amount_to_redeem","cif","bank_transfer","bank_account_name","bank_account_no"]]]));                    
                    $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ["fullname","transaction_date","transaction_type","portfolio_id","investment_account_no","amount_to_purchase","cif","bank_account_name","bank_account_no"]]]));                    
                    $notif  = $this->__notif('TransactionSubscription');
                    $rst = $this->__notif_publish($data, $notif->setup, $role);
                } else if($data[0]->reference_code == 'RED') {
                   //$role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ["fullname","transaction_date","transaction_type","portfolio_id","investment_account_no","unit_redeem","amount_to_purchase","cif","bank_transfer","bank_account_name","bank_account_no"]]]));                    
                   $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ["fullname","transaction_date","transaction_type","portfolio_id","investment_account_no","unit_redeem","cif","bank_account_name","bank_account_no"]]]));                    
                   $notif  = $this->__notif('TransactionRedemption');
                   $rst = $this->__notif_publish($data, $notif->setup, $role);
                } else {
                    if(in_array(strtoupper(trim($data[0]->reference_code)), array('SWTIN','SWTOT'))) {
                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']], 'email' => ['column' => ['portfolio_id', 'investment_account_no', 'cif', 'bank_transfer','bank_account_name','bank_account_no','transaction_date','transaction_type','unit_purchase','amount_to_purchase','unit_redeem','amount_to_redeem']]]));
                        $notif  = $this->__notif('TransactionSwitching');
                        $tmp =  Investor::select('b.trans_history_id','u_investors.investor_id', 'u_investors.sales_id', 'u_investors.email',  'u_investors.cif', 'u_investors.fullname', 'u_investors.email',
                                      'b.transaction_date','b.account_no','b.portfolio_id','b.reference_no','b.unit as unit_purchase','b.amount as amount_to_purchase','b.unit as unit_redeem','b.amount as amount_to_redeem',
                                      'c.reference_name as transaction_type','c.reference_code')
                                 ->join('t_trans_histories as b', 'u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes')
                                 ->join('m_trans_reference as c', 'b.type_reference_id', '=', 'c.trans_reference_id')->where([['c.reference_type', 'Transaction Type']])
                                 //->join('u_investors_accounts as d','b.investor_account_id', '=', 'd.investor_account_id')
                                 //->join('m_bank_branches as e', 'd.bank_branch_id', '=', 'e.bank_branch_id')
                                 //->join('m_bank as f', 'e.bank_id', '=', 'f.bank_id')
                                 ->where([['b.account_no', $data[0]->account_no],['b.is_active','Yes']])
                                 ->orderBy('c.reference_code','asc')
                                 ->get();                                            
                        
                        $data = array();
                        $data[0]['email'] = $tmp[0]['email'];
                        $data[0]['investor_id'] =  $tmp[0]['investor_id']; 
                        $data[0]['portfolio_id'] = $tmp[0]['portfolio_id']; 
                        $data[0]['investment_account_no'] = $tmp[0]['investment_account_no']; 
                        $data[0]['cif'] = $tmp[0]['cif']; 
                        $data[0]['bank_transfer'] = $tmp[0]['bank_transfer']; 
                        $data[0]['bank_transfer'] = $tmp[0]['bank_transfer']; 
                        $data[0]['bank_account_name'] = $tmp[0]['bank_account_name']; 
                        $data[0]['bank_account_no'] = $tmp[0]['bank_account_no'];
                        $data[0]['switch_in_transaction_date'] = $tmp[0]['transaction_date']; 
                        $data[0]['switch_in_transaction_type'] = $tmp[0]['transaction_type']; 
                        $data[0]['switch_in_unit_purchase'] = $tmp[0]['unit_purchase']; 
                        $data[0]['switch_in_amount_to_purchase'] = $tmp[0]['amount_to_purchase']; 
                        $data[0]['switch_out_transaction_date'] = $tmp[1]['transaction_date']; 
                        $data[0]['switch_out_transaction_type'] = $tmp[1]['transaction_type']; 
                        $data[0]['switch_out_unit_redeem'] = $tmp[1]['unit_redeem']; 
                        $data[0]['switch_out_amount_to_redeem'] = $tmp[1]['amount_to_redeem']; 

                        $role   = json_decode(json_encode(['web' =>['message' => ['{time_status}' =>  'transaction_date', '{account_no}' => 'account_no']],'email' => ['column' => ['portfolio_id', 'investment_account_no', 'cif', 'bank_transfer','bank_account_name','bank_account_no','switch_in_transaction_date','switch_in_transaction_type','switch_in_unit_purchase','switch_in_amount_to_purchase','switch_out_transaction_date','switch_out_transaction_type','switch_out_unit_redeem','switch_out_amount_to_redeem']]]));

                        //return $this->app_response('Transaction Investor',$obj->count );
                        $rst = $this->__notif_publish_2($data, $notif->setup, $role);
                    }
                }  
            }                

            return $this->app_response('Transaction Investor', $rst); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function send_sid($cif,$sid,$ifua)
    {
        try
        {  
            $rst = array('cif'=>$cif,'sid'=>$sid,'ifua'=>$ifua);
            if(Investor::where([['cif', $cif],['is_active','Yes']])->update(['sid' => $sid,'ifua'=>$ifua])) {

                $data =  Investor::select('investor_id','cif','sid','u_investors.fullname', 'u_investors.email', 'u_investors.mobile_phone','u.fullname as sales_name','u.user_code as sales_code')
                         ->join('u_users as u', 'u_investors.sales_id', '=', 'u.user_id')->where('u.is_active', 'Yes')
                         ->where([['cif', $cif],['u_investors.is_active','Yes']])
                        ->get();                
                $role   = json_decode(json_encode(['web' =>['message' => ['{sid}' =>  'sid']], 'email' => ['column' => ['cif', 'sid', 'fullname','sales_name','sales_code']]]));

                $notif  = $this->__notif('SIDActive');
                $rste = $this->__notif_publish($data, $notif->setup, $role);
                
                $conf   = MobileContent::where([['mobile_content_name', 'Sid'], ['is_active', 'Yes']])->first();
                $msg    = !empty($conf->mobile_content_text) ? str_replace('{sid}', $sid, $conf->mobile_content_text) : '';
                $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$data['0']->mobile_phone, $msg]]);
                 // return $this->app_response('Update SID Investor By CIF', $smsgateway->original['code']); 

                if($smsgateway->original['code'] == '200')
                {
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_sms' => 'Yes']);

                }else{
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_sms' => 'No']);
                }

                if(!empty($rste['email']))
                {
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_email' => 'Yes']);   
                }else{
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_email' => 'No']);   
                }

                /*
                $api_ifua = $this->api_ws(['sn' => 'InvestorWMS', 'val' => [$cif]])->original;

                if(!empty($api_ifua['data']->ifua)) {
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['ifua' => $api_ifua['data']->ifua]);
                }
                */    
            }
            return $this->app_response('Update SID & IFUA Investor By CIF', $rst); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function send_sid_mf(Request $request)
    {
        try
        { 
            $blockSid = $request->IsBlockSID ? 1 : 0;
            $rst = [
                'cif' => $request->Cif,
                'sid' => $request->Sid,
                'ifua'=> $request->Ifua,
                'sid_blocked' => $blockSid
            ];
            if (Investor::where([['cif', $cif],['is_active','Yes']])->update(['sid' => $sid, 'ifua' => $ifua, 'sid_blocked' => $blockSid]))
            {

                $data =  Investor::select('investor_id','cif','sid','u_investors.fullname', 'u_investors.email', 'u_investors.mobile_phone','u.fullname as sales_name','u.user_code as sales_code')
                         ->join('u_users as u', 'u_investors.sales_id', '=', 'u.user_id')->where('u.is_active', 'Yes')
                         ->where([['cif', $cif],['u_investors.is_active','Yes']])
                        ->get();                
                $role   = json_decode(json_encode(['web' =>['message' => ['{sid}' =>  'sid']], 'email' => ['column' => ['cif', 'sid', 'fullname','sales_name','sales_code']]]));

                $notif  = $this->__notif('SIDActive');
                $rste = $this->__notif_publish($data, $notif->setup, $role);
                
                $conf   = MobileContent::where([['mobile_content_name', 'Sid'], ['is_active', 'Yes']])->first();
                $msg    = !empty($conf->mobile_content_text) ? str_replace('{sid}', $sid, $conf->mobile_content_text) : '';
                $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$data['0']->mobile_phone, $msg]]);
                 // return $this->app_response('Update SID Investor By CIF', $smsgateway->original['code']); 

                if($smsgateway->original['code'] == '200')
                {
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_sms' => 'Yes']);

                }else{
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_sms' => 'No']);
                }

                if(!empty($rste['email']))
                {
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_email' => 'Yes']);   
                }else{
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['notif_sid_send_email' => 'No']);   
                }

                /*
                $api_ifua = $this->api_ws(['sn' => 'InvestorWMS', 'val' => [$cif]])->original;

                if(!empty($api_ifua['data']->ifua)) {
                    Investor::where([['cif', $cif],['is_active','Yes']])->update(['ifua' => $api_ifua['data']->ifua]);
                }
                */    
            }
            return $this->app_response('Update SID & IFUA Investor By CIF', $rst); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function request_sid($inv)
    {
        try
        {  
            $data =  Investor::select('investor_id','cif','sid','u_investors.fullname', 'u_investors.email', 'u_investors.mobile_phone','u.fullname as sales_name','u.user_code as sales_code')
                     ->join('u_users as u', 'u_investors.sales_id', '=', 'u.user_id')->where('u.is_active', 'Yes')
                     ->where([['investor_id', $inv],['u_investors.is_active','Yes']])
                    ->get();                
            $role   = json_decode(json_encode(['web' =>['message' => ['{fullname}' =>  'fullname']], 'email' => ['column' => ['fullname', 'cif', 'sid', 'sales_name','sales_code']]]));

            $notif  = $this->__notif('ReqSID');
            $rste = $this->__notif_publish($data, $notif->setup, $role);
            $conf   = MobileContent::where([['mobile_content_name', 'RequestSID'], ['is_active', 'Yes']])->first();
            $msg    = !empty($conf->mobile_content_text) ? str_replace('{fullname}', $data[0]->fullname, $conf->mobile_content_text) : '';
            $sms_geteway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$data[0]->mobile_phone, $msg]]);
            // return $this->app_response('Request SID Investor', $sms_geteway); 
            if($sms_geteway->original['code'] == '200')
            {
                Investor::where([['investor_id', $inv],['is_active','Yes']])->update(['notif_req_sid_send_sms' => 'Yes']);
            }else{
                Investor::where([['investor_id', $inv],['is_active','Yes']])->update(['notif_req_sid_send_sms' => 'No']);
            }

            if(!empty($rste['email']))
            {
                Investor::where([['investor_id', $inv],['is_active','Yes']])->update(['notif_req_sid_send_email' => 'Yes']);   
            }else{
                Investor::where([['investor_id', $inv],['is_active','Yes']])->update(['notif_req_sid_send_email' => 'No']);   
            }
            
            return $this->app_response('Request SID Investor', $rste); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function transaction_notification_reattempt() {
        ini_set('max_execution_time', '3600');
        try {    
            $list_reattemtp_sms = []; 
             $dt = TransactionHistory::join('u_investors', 'u_investors.investor_id', '=', 't_trans_histories.investor_id')
                  ->join('m_products','m_products.product_id','=','t_trans_histories.product_id')
                  ->join('m_trans_reference', 'm_trans_reference.trans_reference_id', '=', 't_trans_histories.type_reference_id')                  
                  ->where([['u_investors.is_active', 'Yes'],
                           ['t_trans_histories.is_active','Yes']])
                  ->where(function ($query) { $query->where([['t_trans_histories.notif_send_sms','=','No']])->orWhereNull('t_trans_histories.notif_send_sms'); })
                  ->get(); 

            foreach($dt as $val) {
                if(strtoupper($val->reference_code == 'RED')) {
                  $conf    = MobileContent::where([['mobile_content_name', 'TransactionRedeem'], ['is_active', 'Yes']])->first();
                  $product_notification = $val->product_name.'Sejumlah ('.$val->unit.')Unit';                  
                } 

                if(strtoupper($val->reference_code == 'SUB')) {
                  $conf    = MobileContent::where([['mobile_content_name', 'TransactionSub'], ['is_active', 'Yes']])->first();
                  $product_notification = $val->product_name.'Sebesar (Rp. '.number_format($val->amount).')';
                } 

               $msg     = !empty($conf->mobile_content_text) ? str_replace('{product}', $product_notification, $conf->mobile_content_text) : '';
               $api_sms = $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$val->mobile_phone, $msg]]);   

               if (!empty($api_sms->original['code']) && $api_sms->original['code'] == 200) {
                 TransactionHistory::where(['trans_history_id' => $val->trans_history_id])->update(['notif_send_sms' => 'Yes']);                
                 $list_reattemtp_sms[] = ['trasaction_history_id'=>$val->trans_history_id, 'investor_id' => $val->investor_id,'fullname' => $val->fullname,'mobile_phone'=>$val->mobile_phone, 'product_id'=>$val->product_id,'product_name'=>$val->product_name,'trans_reference'=>$val->reference_code,'redeem_unit'=>$val->unit,'subscription_amount'=>$val->amount,'content_sms'=>$msg]; 
               } else {
                 TransactionHistory::where(['trans_history_id' => $val->trans_history_id])->update(['notif_send_sms' => 'No']);                                             
               }
            }


            $list_reattemtp_email = [];
            $dt = TransactionHistory::join('u_investors', 'u_investors.investor_id', '=', 't_trans_histories.investor_id')
                  ->join('m_products','m_products.product_id','=','t_trans_histories.product_id')
                  ->join('m_trans_reference', 'm_trans_reference.trans_reference_id', '=', 't_trans_histories.type_reference_id')                  
                  ->where([['u_investors.is_active', 'Yes'],
                           ['t_trans_histories.is_active','Yes']])
                  ->where(function ($query) { $query->where([['t_trans_histories.notif_send_email','=','No']])->orWhereNull('t_trans_histories.notif_send_email'); })
                  ->get();

            foreach($dt as $val) {
                $email_status_send = $this->transaction($val->trans_history_id);
                if($email_status_send->original['success'] == true) {
                   TransactionHistory::where(['trans_history_id' => $val->trans_history_id])->update(['notif_send_email' => 'Yes']); 
                   $list_reattemtp_email[] = $email_status_send->original;
                } else {
                   TransactionHistory::where(['trans_history_id' => $val->trans_history_id])->update(['notif_send_email' => 'No']); 
                   $list_reattemtp_email[] = $email_status_send->original;                    
                }                    
            }

           return $this->app_response('Transaction Notification Re-Attempt', ['list_sms' => $list_reattemtp_sms, 'list_email' => $list_reattemtp_email] ); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 

        /*    
        redem = kolom unit
        sub = kolom amount

        $sendEmailNotification = new Administrative\Broker\MessagesController;
        $api_email = $sendEmailNotification->transaction($trans_hist_return->trans_history_id);

        if(!empty($api_email->original['success']) && $api_email->original['success'] == 1) {
           TransactionHistory::where(['trans_history_id' => x`$trans_hist_return->trans_history_id])->update(['notif_send_email' => 'Yes']);                
        } else {
           TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_email' => 'No']);                                               
        }        

        $product_notification_unit_redeem = !empty($request->unit[$i]) ? $request->unit[$i] : 0;
        $product_notification = $product->product_name.' ( Redeem '.$product_notification_unit_redeem.' Unit )';
        $investor_mobile_phone  = Investor::select('mobile_phone')->where([['u_investors.is_active', 'Yes'],['investor_id',  $inv_id]])->first();
        $conf    = MobileContent::where([['mobile_content_name', 'TransactionRedeem'], ['is_active', 'Yes']])->first();
        $msg     = !empty($conf->mobile_content_text) ? str_replace('{product}', $product_notification, $conf->mobile_content_text) : '';
        $api_sms = $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$investor_mobile_phone->mobile_phone, $msg]]);   

        if(!empty($api_sms->original['code']) && $api_sms->original['code'] == 200) {
           TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_sms' => 'Yes']);                
        } else {
           TransactionHistory::where(['trans_history_id' => $trans_hist_return->trans_history_id])->update(['notif_send_sms' => 'No']);                                             
        }

             $product_notification_amount = !empty($request->total_amount) ? $request->total_amount : 0;
                        $product_notification = $product->product_name.' ( Pembelian Rp. '.number_format($product_notification_amount).')';
           
        */
    }

    public function request_crond_sid()
    {
        ini_set('max_execution_time', '3600');
        try
        {  
            $rste = $sms_geteway = [];
            $data =  Investor::select('investor_id','cif','sid','u_investors.fullname', 'u_investors.email', 'u_investors.mobile_phone','u.fullname as sales_name','u.user_code as sales_code', 'u_investors.notif_req_sid_send_sms', 'u_investors.notif_req_sid_send_email')
                     ->join('u_users as u', 'u_investors.sales_id', '=', 'u.user_id')->where('u.is_active', 'Yes')
                     ->where('u_investors.is_active','Yes')
                     ->where(function ($query) { $query->where([['u_investors.notif_req_sid_send_sms','=','No']])
                     ->orWhereNull('u_investors.notif_req_sid_send_sms'); })
                     ->get();   
                         // return $this->app_response('Request SID Investor', $data); 
            foreach ($data as $dt) 
            {
                $conf   = MobileContent::where([['mobile_content_name', 'RequestSID'], ['is_active', 'Yes']])->first();
                $msg    = !empty($conf->mobile_content_text) ? str_replace('{fullname}', $dt->fullname, $conf->mobile_content_text) : '';
                $sms_geteway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$dt->mobile_phone, $msg]]);
                if($sms_geteway->original['code'] == '200')
                {
                    Investor::where([['cif', $dt->cif], ['notif_req_sid_send_sms','No'], ['is_active','Yes']])->update(['notif_req_sid_send_sms' => 'Yes']);
                }else{
                    Investor::where([['cif', $dt->cif], ['notif_req_sid_send_sms','No'], ['is_active','Yes']])->update(['notif_req_sid_send_sms' => 'No']);
                }
            }
            
            $data_email =  Investor::select('investor_id','cif','sid','u_investors.fullname', 'u_investors.email', 'u_investors.mobile_phone','u.fullname as sales_name','u.user_code as sales_code', 'u_investors.notif_req_sid_send_email')
                     ->join('u_users as u', 'u_investors.sales_id', '=', 'u.user_id')->where('u.is_active', 'Yes')
                     ->where('u_investors.is_active','Yes')
                     ->where(function ($query) { $query->where([['u_investors.notif_req_sid_send_email','=','No']])
                     ->orWhereNull('u_investors.notif_req_sid_send_email'); })
                     ->get(); 
            foreach ($data_email as $de) 
            {
                $role   = json_decode(json_encode(['web' =>['message' => ['{fullname}' =>  'fullname']], 'email' => ['column' => ['cif', 'sid', 'fullname','sales_name','sales_code']]]));
                $notif  = $this->__notif('ReqSID');
                $rste = $this->__notif_publish($data_email, $notif->setup, $role);
              
                if(!empty($rste['email']))
                {
                    Investor::where([['data_email', $de->cif],['notif_req_sid_send_email','No'], ['is_active','Yes']])->update(['notif_req_sid_send_email' => 'Yes']);   
                }else{
                    Investor::where([['data_email', $de->cif], ['notif_req_sid_send_email','No'], ['is_active','Yes']])->update(['notif_req_sid_send_email' => 'No']);   
                }
            }
            return $this->app_response('Request SID Investor',  array('sms'=>$sms_geteway, 'emai'=>$rste)); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function send_crond_sid()
    {
        ini_set('max_execution_time', '3600');
        try
        {  
            $smsgateway = $rste = [];
            $data =  Investor::select('investor_id','cif','sid','u_investors.fullname', 'u_investors.email', 'u_investors.mobile_phone','u.fullname as sales_name','u.user_code as sales_code','u_investors.notif_sid_send_sms')
                         ->join('u_users as u', 'u_investors.sales_id', '=', 'u.user_id')->where('u.is_active', 'Yes')
                         ->where('u_investors.is_active','Yes')
                         ->where(function ($query) { $query->where([['u_investors.notif_sid_send_sms','=','No']])->orWhereNull('u_investors.notif_sid_send_sms'); })
                        ->get();     
            foreach ($data as $dt) 
            {
                $conf   = MobileContent::where([['mobile_content_name', 'Sid'], ['is_active', 'Yes']])->first();
                $msg    = !empty($conf->mobile_content_text) ? str_replace('{sid}', $dt->sid, $conf->mobile_content_text) : '';
                $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$dt->mobile_phone, $msg]]);
                if($smsgateway->original['code'] == '200')
                {
                    Investor::where([['cif', $dt->cif],['is_active','Yes']])->update(['notif_sid_send_sms' => 'Yes']);
                }else{
                    Investor::where([['cif', $dt->cif],['is_active','Yes']])->update(['notif_sid_send_sms' => 'No']);
                }
            }

            $data_email =  Investor::select('investor_id','cif','sid','u_investors.fullname', 'u_investors.email', 'u_investors.mobile_phone','u.fullname as sales_name','u.user_code as sales_code', 'u_investors.notif_sid_send_email')
                         ->join('u_users as u', 'u_investors.sales_id', '=', 'u.user_id')->where('u.is_active', 'Yes')
                         ->where('u_investors.is_active','Yes')
                         ->where(function ($query) { $query->where([['u_investors.notif_sid_send_email','=','No']])->orWhereNull('u_investors.notif_sid_send_email'); })
                        ->get(); 

            foreach ($data_email as $de) 
            {
                
                $role   = json_decode(json_encode(['web' =>['message' => ['{sid}' =>  'sid']], 'email' => ['column' => ['cif', 'sid', 'fullname','sales_name','sales_code']]]));

                $notif  = $this->__notif('SIDActive');
                $rste = $this->__notif_publish($data_email, $notif->setup, $role);
                if(!empty($rste['email']))
                {
                    Investor::where([['cif', $de->cif], ['notif_sid_send_email', 'No'], ['is_active','Yes']])->update(['notif_sid_send_email' => 'Yes']);   
                }else{
                    Investor::where([['cif', $de->cif], ['notif_sid_send_email', 'No'], ['is_active','Yes']])->update(['notif_sid_send_email' => 'No']);   
                }
            }
            return $this->app_response('Update SID Investor By CIF',  array('sms'=>$smsgateway, 'emai'=>$rste)); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function transaction_switching($switch_out_trans_id,$switch_in_trans_id)
    {
        try
        {  
            $mail_respon = '';
            $data_switch_out =  Investor::select('b.trans_history_id','b.portfolio_id','u_investors.investor_id', 'u_investors.sales_id', 'u_investors.email',  'u_investors.cif', 'u_investors.fullname', 'u_investors.email', 
                                      'b.transaction_date','b.account_no as investment_account_no','b.portfolio_id','b.reference_no','b.unit as unit_purchase','b.amount as amount_to_purchase','b.unit as unit_redeem','b.amount as amount_to_redeem',
                                      'c.reference_name as transaction_type','c.reference_code', 'e.branch_name as bank_transfer', 'd.account_name as bank_account_name', 'd.account_no as bank_account_no', 'p.product_name','p.product_code','b.unit')
                     ->join('t_trans_histories as b', 'u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes')
                     ->join('m_trans_reference as c', 'b.type_reference_id', '=', 'c.trans_reference_id')
                     ->join('u_investors_accounts as d', 'b.investor_account_id', '=', 'd.investor_account_id')
                     ->join('m_bank_branches as e', 'd.bank_branch_id', '=', 'e.bank_branch_id')
                     ->join('m_products as p', 'b.product_id', '=', 'p.product_id')                     
                     ->join('m_bank as f', 'e.bank_id', '=', 'f.bank_id')
                     ->where([['b.trans_history_id', $switch_out_trans_id],['b.is_active','Yes']])
                     ->first();

            $data_switch_in =  Investor::select('b.trans_history_id','b.portfolio_id','u_investors.investor_id', 'u_investors.sales_id', 'u_investors.email',  'u_investors.cif', 'u_investors.fullname', 'u_investors.email', 
                                      'b.transaction_date','b.account_no as investment_account_no','b.portfolio_id','b.reference_no','b.unit as unit_purchase','b.amount as amount_to_purchase','b.unit as unit_redeem','b.amount as amount_to_redeem',
                                      'c.reference_name as transaction_type','c.reference_code', 'e.branch_name as bank_transfer', 'd.account_name as bank_account_name', 'd.account_no as bank_account_no', 'p.product_name','p.product_code','b.unit')
                     ->join('t_trans_histories as b', 'u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes')
                     ->join('m_trans_reference as c', 'b.type_reference_id', '=', 'c.trans_reference_id')
                     ->join('u_investors_accounts as d', 'b.investor_account_id', '=', 'd.investor_account_id')
                     ->join('m_bank_branches as e', 'd.bank_branch_id', '=', 'e.bank_branch_id')
                     ->join('m_products as p', 'b.product_id', '=', 'p.product_id')                                          
                     ->join('m_bank as f', 'e.bank_id', '=', 'f.bank_id')
                     ->where([['b.trans_history_id', $switch_in_trans_id],['b.is_active','Yes']])
                     ->first();

            if(!empty($data_switch_out) && !empty($data_switch_in)) {
                $notif  = $this->__notif('TransactionSwitching'); 
                $to_mail    = !empty($data_switch_out->email) ? $data_switch_out->email : '';
                if (!empty($notif->setup->email_content_id) && !empty($to_mail))
                {

                    $dt_columen = [$data_switch_out->fullname,$data_switch_out->cif,$data_switch_in->transaction_date,$data_switch_in->unit, $data_switch_in->product_name,$data_switch_out->transaction_date,$data_switch_out->unit, $data_switch_out->product_name];
                    $mail   = array_merge(['content_id' => $notif->setup->email_content_id, 'to' => $to_mail], ['new' => $dt_columen]);
                    $mail_respon =  $this->app_sendmail($mail);                    
                }
            }                

            return $this->app_response('Transaction Investor',$mail_respon); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }    


    public function transaction_switching_notification_reattempt() {
        ini_set('max_execution_time', '3600');
        try {    
             $list_reattemtp_sms = []; 
             $dt = TransactionHistory::join('u_investors', 'u_investors.investor_id', '=', 't_trans_histories.investor_id')
                  ->join('m_products','m_products.product_id','=','t_trans_histories.product_id')
                  ->join('m_trans_reference', 'm_trans_reference.trans_reference_id', '=', 't_trans_histories.type_reference_id')                  
                  ->where([['u_investors.is_active', 'Yes'],
                           ['t_trans_histories.is_active','Yes']])
                  ->whereIn('m_trans_reference.reference_code',['SWTOT'])
                  ->where(function ($query) { $query->where([['t_trans_histories.notif_send_sms','=','No']])->orWhereNull('t_trans_histories.notif_send_sms'); })
                  ->get(); 

            foreach($dt as $val) {
                $unit = $val->unit;
                $product_switching_out = !empty($val->product_code) ? $val->product_code : '';
                $trans_history_id[] = $val->trans_history_id;

                $product_switching_in = TransactionHistory::select('t_trans_histories.trans_history_id','m_products.product_code','m_products.product_name')
                                        ->join('m_products','m_products.product_id','=','t_trans_histories.product_id')
                                        ->join('m_trans_reference', 'm_trans_reference.trans_reference_id', '=', 't_trans_histories.type_reference_id')                  
                                        ->where([['t_trans_histories.reference_no',$val->reference_no],['t_trans_histories.is_active','Yes']])
                                        ->whereIn('m_trans_reference.reference_code',['SWTIN'])->first();

                $trans_history_id[] = !empty($product_switching_in) ? $product_switching_in->trans_history_id : '' ;
                $product_switching_in = !empty($product_switching_in->product_code) ? $product_switching_in->product_code : '';

                $sms_switch_content = [$product_switching_out,$unit,$product_switching_in,$unit];
                $investor_mobile_phone  = Investor::select('mobile_phone')->where([['u_investors.is_active', 'Yes'],['investor_id',  $val->investor_id]])->first();
                $conf    = MobileContent::where([['mobile_content_name', 'TransactionSwitching'], ['is_active', 'Yes']])->first();
                $msg     = !empty($conf->mobile_content_text) ? str_replace(['{switch_out_product}','{switch_out_unit}','{switch_in_product}','{switch_in_unit}'], $sms_switch_content, $conf->mobile_content_text) : '';

                $api_sms = $smsgateway = $this->api_ws(['sn' => 'SmsGateway', 'val' => [$investor_mobile_phone->mobile_phone, $msg]]); 
                if(!empty($api_sms->original['code']) && $api_sms->original['code'] == 200) {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_sms' => 'Yes']);
                   $data_switch['notif_send_sms'] = 'Yes';
                } else {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_sms' => 'No']);                                             
                   $data_switch['notif_send_sms'] = 'No';
                }

                $list_reattemtp_sms[] = ['data'=>$val,'msg'=>$msg,'mobile_phone'=>$val->mobile_phone,'notif_send_sms'=>$data_switch['notif_send_sms']]; 
            }

            $list_reattemtp_email = [];
            $dt = TransactionHistory::join('u_investors', 'u_investors.investor_id', '=', 't_trans_histories.investor_id')
                  ->join('m_products','m_products.product_id','=','t_trans_histories.product_id')
                  ->join('m_trans_reference', 'm_trans_reference.trans_reference_id', '=', 't_trans_histories.type_reference_id')                  
                  ->where([['u_investors.is_active', 'Yes'],
                           ['t_trans_histories.is_active','Yes']])
                  ->whereIn('m_trans_reference.reference_code',['SWTOT'])
                  ->where(function ($query) { $query->where([['t_trans_histories.notif_send_email','=','No']])->orWhereNull('t_trans_histories.notif_send_email'); })
                  ->get(); 

            foreach($dt as $val) {
                $trans_history_id[] = $val->trans_history_id;
                $product_switching_in = TransactionHistory::select('t_trans_histories.trans_history_id','m_products.product_code','m_products.product_name')
                                        ->join('m_products','m_products.product_id','=','t_trans_histories.product_id')
                                        ->join('m_trans_reference', 'm_trans_reference.trans_reference_id', '=', 't_trans_histories.type_reference_id')                  
                                        ->where([['t_trans_histories.reference_no',$val->reference_no],['t_trans_histories.is_active','Yes']])
                                        ->whereIn('m_trans_reference.reference_code',['SWTIN'])->first();
                $trans_history_id[] = !empty($product_switching_in) ? $product_switching_in->trans_history_id : '' ;

                $api_email = $this->transaction_switching($trans_history_id[0],$trans_history_id[1]);
                if(!empty($api_email->original['success']) && $api_email->original['success'] == 1) {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_email' => 'Yes']);  
                   $data_switch['notif_send_email'] = 'Yes';
                } else {
                   TransactionHistory::whereIn('trans_history_id',$trans_history_id)->update(['notif_send_email' => 'No']);                                               
                   $data_switch['notif_send_email'] = 'No';
                }     
                
                $list_reattemtp_email[] = ['data'=>$val,'email'=>$val->email,'notif_send_email'=>$data_switch['notif_send_email']]; 
            }

           return $this->app_response('Transaction Notification Re-Attempt', ['list_sms' => $list_reattemtp_sms, 'list_email' => $list_reattemtp_email] ); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function send_ifua($cif,$ifua)
    {
        try
        {  
            $rst = Investor::where([['cif', $cif],['is_active','Yes']])->update(['ifua' => $ifua]);
            return $this->app_response('Update IFUA Investor By CIF', ['cif'=>$cif,'ifua'=>$ifua]); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

}
