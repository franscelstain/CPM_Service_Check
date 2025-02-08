<?php

namespace App\Http\Controllers\Administrative\Broker;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\Administrative\Notification\Investor as notif_investor;
use App\Models\Administrative\Notify\InvestorIntervalSetup;
use App\Models\Investor\Notify\Notification;
use App\Models\Administrative\Notification\Notification as Notif;
use App\Models\Administrative\Notification\NotificationInterval;
use App\Models\Users\Investor\Edd;
use App\Models\Users\Investor\CardPriority;
use App\Models\Users\Investor\Investor;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use Auth;

class InvestorController extends AppController
{
    private function __notif($category, $interval = true)
    {
        $date       = [];
        $setNotif   = Notif::where([['is_active', 'Yes'], ['notif_code', $category]])
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
    
    public function __notif_batch()
    {
        try
        {
            Notification::where([['investor_id', Auth::id()]])->update(['notif_batch' => true, 'notif_read' => true]);
            return $this->app_response('Notif Batch', 'Notif successfully updated');
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function __notif_publish($qry, $notif, $role = [])
    {
        $publish = [];
        if ($qry->count() > 0)
        {
            foreach ($qry as $q)
            {
                $filter = !empty($role->filter) ? $role->filter : '';
                $ts     = !empty($filter) ? strtotime($this->app_date()) >= strtotime($q->$filter) ? 'sudah' : 'akan' : '';
                $msg    = str_replace('{time_status}', $ts, $notif->text_message);
                $data   = [
                    'investor_id'   => $q->investor_id,
                    'notif_title'   => $notif->title,
                    'notif_desc'    => $msg,
                    'notif_link'    => $notif->redirect,
                    'notif_web'     => $notif->notif_web ? 't' : 'f',
                    'notif_email'   => $notif->notif_mail ? 't' : 'f',
                    'notif_mobile'  => $notif->notif_mobile ? 't' : 'f',
                    'created_by'    => 'System',
                    'created_host'  => '::1'
                ];
                $publish[] = $data;
                Notification::create($data);
            }
        }
        return $publish;
    }
    
    private function __notif_send()
    {
        Notification::where([['investor_id', Auth::id()]])->update(['notif_send' => true]);
    }
    
    public function atm_expired()
    {
        try
        {
            $result = [];
            $notif  = $this->__notif('ATMExpiredInvestor');
            if (!empty($notif->date))
            {
                $data   = CardPriority::select('b.investor_id', 'u_investors_card_priorities.card_expired')
                        ->join('u_investors as b', 'u_investors_card_priorities.cif', '=', 'b.cif')
                        ->where([['u_investors_card_priorities.is_active', 'Yes'], ['b.is_active', 'Yes'], ['b.valid_account', 'Yes']])
                        ->whereIn('card_expired', $notif->date)->get();
                $role   = (object) ['filter' => 'card_expired'];
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('ATM Expired', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function birthday()
    {
        try
        {
            $result = [];
            $notif  = $this->__notif('BirthdayInvestor');

            if (!empty($notif->date))
            {
        		foreach($notif->date as $dte)
        		{
                    $data       = Investor::select('investor_id', 'date_of_birth')
                                ->where([['is_active', 'Yes'], ['valid_account', 'Yes']])
                                ->whereMonth('date_of_birth', date('m', strtotime($dte)))
                                ->whereDay('date_of_birth', date('d', strtotime($dte)))
                                ->get();
                    $role       = (object) ['filter' => 'date_of_birth'];
                    $result[]   = $this->__notif_publish($data, $notif->setup, $role);
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
            $result = [];
            $notif  = $this->__notif('EddExpired');
            if (!empty($notif->date))
            {
                $data   = Edd::where('is_active', 'Yes')->whereIn('edd_date', $notif->date)->orWhere('edd_date', '<', $this->app_date())->get();
                $role   = (object) ['filter' => 'edd_date'];
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Edd Expired', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function list_notify()
    {
        try
        {
            $data   = Notification::where([['investor_id', Auth::id()], ['is_active', 'Yes'], ['notif_web', true]])->orderBy('notif_batch')->orderBy('notif_read')->orderBy('created_at', 'desc')->get();
            $batch  = Notification::where([['investor_id', Auth::id()], ['is_active', 'Yes'], ['notif_batch', false], ['notif_web', true]])->count();
            
            if ($batch > 0)
                $this->__notif_send();
            
            return $this->app_response('Notify', ['notif' => $data, 'batch' => $batch]);
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
            $result = [];
            $notif  = $this->__notif('RiskProfileExpiredInvestor');
            if (!empty($notif->date))
            {
                $data   = Investor::select('investor_id', 'profile_expired_date')
                        ->where([['is_active', 'Yes'], ['valid_account', 'Yes']])
                        ->whereIn('profile_expired_date', $notif->date)->get();
                $role   = (object) ['filter' => 'profile_expired_date'];
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Risk Profile Expired', $result);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function unsent_notify()
    {
        try
        {
            $data   = Notification::where([['investor_id', Auth::id()], ['is_active', 'Yes'], ['notif_batch', false], ['notif_web', true], ['notif_send', false]])->orderBy('created_at', 'desc');
            $num    = $data->count();
            $notif  = $data->get();
            
            if ($num > 0)
                $this->__notif_send();
            
            return $this->app_response('Notify', ['notif' => $notif, 'batch' => $num]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }

    public function sent_notify()
    {
        try
        {
            $res    = [];
            $data   = Notification::select('h_notification_investor.*', 'b.*', 'c.email as email_sales' )
                    ->join('u_investors as b', 'h_notification_investor.investor_id', '=', 'b.investor_id')
                    ->join('u_users as c', 'b.sales_id', '=', 'c.user_id')
                    ->where([['h_notification_investor.is_active', 'Yes'], ['h_notification_investor.notif_send', false], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']])
                    ->get();

            foreach ($data as $dt)
            {
                if($dt->notif_email == true)
                {  
                    $this->app_sendmail(['to' => $dt->email_sales, 'content' =>  'Notification', 'new' => $dt->notif_desc]);           
                }

                if($dt->notif_mobile == true)
                {
                    $this->mobile_notif($dt);
                }
                Notification::where('id', $dt->id)->update(['notif_send' => true]);
                // $this->__notif_send();
            }
            return $this->app_response('Notify', ['notif' => $data]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }        
    }

    public function mobile_notif($dt)
    {
        try
        {
            $res            = [];
            $email          = $dt->notif_email ? $dt->email : '';
            $mobile_phone   = $dt->mobile_phone;
            $val            = [$dt->fullname, $dt->fullname, $email, $mobile_phone, $dt->notif_desc];
            $api            = $this->api_ws(['sn' => 'Message', 'val' => $val])->original;
            if (!empty($api['code']) && $api['code'] == 200)
            {
                // Notification::where('id', $dt->id)->update(['notif_send' => true]);
                $res[] = $api['data'];
            }
            else
            {
                $res[] = $api;
            }

            // return $this->app_response('Notify', ['notif' => $res]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function managed_funds()
    {
        try
        {
            $result = [];
            $notif  = $this->__notif('ManagedFunds', false);
            if (!empty($notif->setup))
            {
                $goals = Investment::where([ ['t_goal_investment.is_active', 'Yes']])->get();
            
                if (!empty($goals))
                {
                    foreach ($goals as $dt)
                    {
                        if(!empty($dt))
                        {
                            $data    = TransactionHistoryDay::select('t_trans_histories_days.*', 'b.product_name')
                                        ->join('m_products as b', 't_trans_histories_days.product_id', '=', 'b.product_id',)
                                        ->where([['investor_id', $dt->investor_id], ['portfolio_id', $dt->portfolio_id], ['history_date', $this->app_date()], ['t_trans_histories_days.is_active', 'Yes'], ['b.is_active', 'Yes'], ['t_trans_histories_days.returns', '<', 0]])
                                        ->get();
                            $result = $this->__notif_publish($data, $notif->setup);
                         }
                    }
                }
            }
            return $this->app_response('managed Funds', $result); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function managed_nav()
    {
        try
        {
            $result = [];
            $notif  = $this->__notif('ManagedNav', false);
            if (!empty($notif->setup))
            {
                $data   = AssetOutstanding::select('c.return_1day', 'd.*', 'b.*','c.*')
                        ->join('m_products as b', 't_assets_outstanding.product_id', '=', 'b.product_id')
                        ->join('m_products_period as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                        ->join('t_goal_investment as d', 'd.investor_id', '=', 't_assets_outstanding.investor_id')
                        ->where([['c.is_active', 'Yes'], ['b.is_active', 'Yes'], ['t_assets_outstanding.is_active', 'Yes'], ['d.is_active', 'Yes'], ['t_assets_outstanding.outstanding_date', $this->app_date()], ['c.return_1day', '<=', -2]])
                        ->get();
                // $role   = (object) ['filter' => 'edd_date'];
                $result = $this->__notif_publish($data, $notif->setup);
            }
            return $this->app_response('managed Nav', $result);        
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    // public function managed_riskprofile_sales()
    // {
    //     try
    //     {
    //         $result = [];
    //         $notif  = $this->__notif('RiskProfileSales', false); 
    //          return $this->app_response('risk profile investor on sales', $notif); 
    //         if (!empty($notif->setup))
    //         {
    //             $data   = Investor::select('u_investors.investor_id','u_investors.profile_expired_date')
    //                     ->leftJoin('u_users as b', function($qry) { return $qry->on('u_investors.sales_id', '=', 'b.user_id')->where('b.is_active', 'Yes'); })
    //                     ->where('u_investors.is_active', 'Yes')
    //                     ->whereIn('profile_expired_date', $notif->date)
    //                     ->orWhere('profile_expired_date', '<', $this->app_date())
    //                     ->get();
    //             $role   = (object) ['filter' => 'profile_expired_date'];
    //             $result = $this->__notif_publish($data, $notif->setup, $role);

    //         }
    //         return $this->app_response('risk profile investor on sales', $result); 
    //     }
    //     catch(\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     } 
    // }

    // public function managed_birthday_sales()
    // {
    //     try
    //     {
    //         $result = [];
    //         $notif  = $this->__notif('BirtdaySales', false);
    //         if (!empty($notif->setup))
    //         {
    //             $data   = Investor::select('u_investors.investor_id','u_investors.date_of_birth')
    //                     ->leftJoin('u_users as b', function($qry) { return $qry->on('u_investors.sales_id', '=', 'b.user_id')->where('b.is_active', 'Yes'); })
    //                     ->whereIn('u_investors.date_of_birth', $notif->date)
    //                     ->orWhere('u_investors.date_of_birth', '<', $this->app_date())
    //                     ->get();
    //             $role   = (object) ['filter' => 'date_of_birth'];
    //             $result = $this->__notif_publish($data, $notif->setup, $role);
    //         }
    //         return $this->app_response('birthday investor on sales', $result); 
    //     }
    //     catch(\Exception $e)
    //     {
    //         return $this->app_catch($e);
    //     } 
    // }

    public function managed_deposito()
    {
        try
        {
            $result = [];
            $notif  = $this->__notif('DepositoInvestorSales', false);

            if (!empty($notif->setup))
            {
                $data   = Investor::select('u_investors.investor_id', 'b.account_no', 'b.due_date')
                        ->leftJoin('t_assets_outstanding as b', function($qry) { return $qry->on('u_investors.investor_id', '=', 'b.investor_id')->where('b.is_active', 'Yes'); })
                        ->where([['u_investors.is_active', 'Yes'], ['u_investors.valid_account', 'Yes']])
                        ->whereIn('b.due_date', $notif->date)
                        ->orWhere('b.due_date', '=', $this->app_date())
                        ->get();
                $role   = (object) ['filter' => 'b.due_date'];
                $result = $this->__notif_publish($data, $notif->setup, $role);
            }
            return $this->app_response('Expired Deposito Investor on sales', $data); 
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }
}