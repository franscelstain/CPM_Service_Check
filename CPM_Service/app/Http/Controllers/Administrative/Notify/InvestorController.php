<?php

namespace App\Http\Controllers\Administrative\Notify;

use App\Http\Controllers\AppController;
use App\Models\Financial\AssetOutstanding;
use App\Models\Auth\Investor;
use App\Models\SA\Assets\Products\Fee;
use App\Models\SA\Assets\Products\Price;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\Financial\Planning\Goal\InvestmentDetail;
use App\Models\Administrative\Notify\CategorySetup;
use App\Models\Administrative\Notification\Investor as notif_investor;
use App\Models\Administrative\Notify\NotificationInvestor;
use App\Models\Administrative\Notify\InvestorIntervalSetup;
use App\Models\Investor\Notify\Notification;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use Illuminate\Http\Request;
use Auth;

class InvestorController extends AppController
{
	public $table = 'Administrative\Notify\InvestorSetup';

 	public function index()
    {
        try
        {
            $data   = [];
            $notif  = notif_investor::select('m_notification_investor.*', 'b.category_name')
                    ->join('m_notification_categories as b', 'm_notification_investor.category_id', '=', 'b.id')
                    ->where([['m_notification_investor.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->get();
            
            foreach ($notif as $n)
            {
                $data[] = [
                    'id'            => $n->id,
                    'title'         => $n->title,
                    'message'       => $n->text_message,
                    'category'      => $n->category_name,
                    'notif_mail'     => $n->notif_mail ? 'Yes' : 'No',
                    'notif_web'      => $n->notif_web ? 'Yes' : 'No',
                    'notif_mobile'   => $n->notif_mobile ? 'Yes' : 'No'
                ];
            }
            
            return $this->app_response('Notif Investor Setup', ['key' => 'id', 'list' => $data]);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
 	}

    public function category()
    {
        try
        {
            $data = CategorySetup::where([['assign_to', 'Investor'], ['is_active', 'Yes']])->get();
            return $this->app_response('Notification', $data);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

	public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function interval($id)
    {
        try
        {
            $qry    = InvestorIntervalSetup::where([['investor_notif_id', $id], ['is_active', 'Yes']])->get();
            $data   = $qry->count() > 0 ? $qry : [];
            return $this->app_response('Notif Investor Interval', $data);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        try
        {
            $request->request->add(['is_mail' => $request->is_mail  == 'true'? 't' : 'f' ]);
            $id = $this->db_save($request, $id, ['res' => 'id']);
            
            if ($id)
            {
                $reminder   = $request->reminder;
                $c_reminder = $request->count_reminder;
                $continuous = $request->continuous;
                $manager    = $this->db_manager($request);
                
                InvestorIntervalSetup::where('investor_notif_id', $id)->update(['is_active' => 'No']);
                
                for ($i = 0; $i < count($reminder); $i++)
                {
                    $count  = $reminder[$i] == 'H' ? [] : [['count_reminder', $c_reminder[$i]]];
                    $intvl  = InvestorIntervalSetup::where(array_merge([['investor_notif_id', $id], ['reminder', $reminder[$i]]], $count))->first();
                    $act    = empty($intvl->id) ? 'created' : 'updated';
                    $data   = ['investor_notif_id'  => $id,
                               'reminder'           => $reminder[$i],
                               'count_reminder'     => !empty($c_reminder[$i]) ? $c_reminder[$i] : null,
                               'continuous'         => !empty($continuous[$i]) && $continuous[$i] == 'true' ? 't' : 'f',
                               $act.'_by'           => $manager->user,
                               $act.'_host'         => $manager->ip,
                               'is_active'          => 'Yes'
                              ];
                    $save   = empty($intvl->id) ? InvestorIntervalSetup::create($data) : InvestorIntervalSetup::where('id', $intvl->id)->update($data);
                }
            }
            
            return $this->app_partials(1, 0, ['id' => $id]);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
	
	public function get_data()
    {


		$data      = Notification::select('h_notification_investor.*')
                    ->join('u_investors as ui', 'h_notification_investor.investor_id', '=', 'ui.investor_id')
                    ->where('ui.investor_id',Auth::id())
                    ->get();


        $badge     = Notification::select('h_notification_investor.notif_status_batch')
                    ->where('h_notification_investor.investor_id',Auth::id())
                    ->where('h_notification_investor.notif_status_batch','f')
                    ->get();
        
        return $this->app_response('Notification', ['list' => $data,'badge'=>$badge,'key'=>'id']);
	}

    public function badge_default($id)
    {
    	try {
			    $data      = Notification::where('investor_id',$id)->update(['notif_status_batch'=>'t']);
			    $badge     = Notification::select('h_notification_investor.notif_status_batch')
                    ->where('h_notification_investor.investor_id',Auth::id())
                    ->where('h_notification_investor.notif_status_batch','f')
                    ->get();
			    return $this->app_response('Notification', ['msg' =>'Success','data'=>$badge]);
    	
			} catch (QueryException $e) {
			    $message = $e;
			    return response()->json($message, 500);
			}
    }

    public function notif_read($id){
    	try {

    	       $data 			 = Notification::where('id',$id)->update(['notif_status'=>'t','notif_status_batch'=>'t']);
    	       $notif_status     = Notification::select('h_notification_investor.notif_status')
                    ->where('h_notification_investor.investor_id',Auth::id())
                    ->where('h_notification_investor.notif_status','f')
                    ->get();
    	       return $this->app_response('Notification', ['state' =>'succes','data'=>$notif_status]);
    	
    	}catch (QueryException $e) {
			    $message = $e;
			    return response()->json($message, 500);
			}
    }

    public function notif_rebalancing()
    {
        try
        {
            $prod       = $notif = [];
            $prj_amt    = 0;
            $goals      = Investment::where([ ['t_goal_investment.is_active', 'Yes']])->get();
            
            foreach ($goals as $dt)
            {
                $balance    = TransactionHistoryDay::join('m_products as b', 't_trans_histories_days.product_id', '=', 'b.product_id')
                            ->where([['investor_id', $dt->investor_id], ['portfolio_id', $dt->portfolio_id], ['history_date', $this->app_date()], ['t_trans_histories_days.is_active', 'Yes'], ['b.is_active', 'Yes']])
                            ->sum('current_balance');
                $prj_amt    = 0;
                
                if ($balance > 0)
                {
                    $d1         = new \DateTime($dt->goal_invest_date);
                    $d2         = new \DateTime(date('Y-m-d'));
                    $diff       = $d2->diff($d1);
                    $month      = 0;
                    $product    = InvestmentDetail::select('net_amount', 'expected_return_month', 'investment_type')
                                ->join('m_products as b', 't_goal_investment_detail.product_id', '=', 'b.product_id')
                                ->where([['goal_invest_id', $dt->goal_invest_id], ['t_goal_investment_detail.is_active', 'Yes'], ['b.is_active', 'Yes']])
                                ->get();

                    if ($diff->y > 0)
                        $month = $diff->m > 0 ? ($diff->y*12)+$diff->m : $diff->y*12;
                    else
                        $month = $diff->m;

                    $prj_amt = $month == 0 ? $dt->first_investment : 0;

                    foreach ($product as $prd)
                    {                    
                        if ($month > 0)
                        {
                            if ($prd->investment_type == 'Lumpsum')
                                $prj_amt += $prd->net_amount * pow(1 + $prd->expected_return_month, $month);
                            else
                                $prj_amt += $prd->expected_return_month > 0 ? (($prd->net_amount * (1 + $prd->expected_return_month)) * (pow(1 + $prd->expected_return_month, $month) - 1)) / $prd->expected_return_month : 0;
                        }
                    }

                    $hd = $prj_amt - $balance;
                    if ($hd > 0)
                    {
                        $persentage = (($prj_amt / $balance)-1)*100;

                        $msg ='Nilai portfolio '.$dt->portfolio_id.' turun dari '.number_format($persentage, 2).'%. Segera lakukan transaksi subscription agar tujuan kamu tetap tercapai tepat waktu.';
                        
                        $notif[] = $msg;
                        $act    = empty($intvl->id) ? 'created' : 'updated';

                        $data = [
                            'investor_id'   => $dt['investor_id'],
                            'notif_title'   => 'Notifikasi Rebalancing',
                            'notif_desc'    => $msg,
                            'notif_email'   => '1',
                            'notif_web'     => '0',
                            'notif_mobile'  => '1',
                            $act.'_by'      => 'System',
                            $act.'_host'    => '::1',
                            'is_active'     => 'Yes'
                        ];
                        
                        NotificationInvestor::create($data);

                    }
                }
            
            }
            return $this->app_response('Notif Rebelancing', $notif);        
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function managed_funds()
    {
        try
        {
            $notif = [];
            $goals = Investment::where([ ['t_goal_investment.is_active', 'Yes']])->get();
            
            if (!empty($goals))
            {
                foreach ($goals as $dt)
                {
                    if(!empty($dt))
                    {
                        $balance    = TransactionHistoryDay::select('t_trans_histories_days.*', 'b.product_name')
                            ->join('m_products as b', 't_trans_histories_days.product_id', '=', 'b.product_id',)
                            ->where([['investor_id', $dt->investor_id], ['portfolio_id', $dt->portfolio_id], ['history_date', $this->app_date()], ['t_trans_histories_days.is_active', 'Yes'], ['b.is_active', 'Yes'], ['t_trans_histories_days.returns', '<', 0]])
                            ->get();

                        foreach ($balance as $blc) 
                        {
                            if (!empty($blc))
                            {
                               $msg = 'Nilai Balance pada produk '.$blc->product_name.' di portfolio '.$dt->portfolio_id.' mengalami penurunan sebanyak '.number_format($blc->returns, 2).'%.';
                                $notif[] = $msg;                                              
                            }
                            $act    = empty($intvl->id) ? 'created' : 'updated';
                            $data = [
                                'investor_id'   => $blc['investor_id'],
                                'notif_title'   => 'Manage Funds',
                                'notif_desc'    => $msg,
                                'notif_email'   => '1',
                                'notif_api'     => '0',
                                'notif_mobile'  => '1',
                                $act.'_by'      => 'System',
                                $act.'_host'    => '::1',
                                'is_active'     => 'Yes'
                            ];
                            
                            NotificationInvestor::create($data);
                        }
                    }
                }
            }
            return $this->app_response('Notif Rebelancing', $notif);        
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
            $notif = [];
            $assets = AssetOutstanding::select('c.return_1day', 'd.*', 'b.*','c.*')
                ->join('m_products as b', 't_assets_outstanding.product_id', '=', 'b.product_id')
                ->join('m_products_period as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                ->join('t_goal_investment as d', 'd.investor_id', '=', 't_assets_outstanding.investor_id')
                ->where([['c.is_active', 'Yes'], ['b.is_active', 'Yes'], ['t_assets_outstanding.is_active', 'Yes'], ['d.is_active', 'Yes'], ['outstanding_date', $this->app_date()], ['c.return_1day', '<=', -2]])
                ->get();

            foreach ($assets as $ast) 
            {
                if (!empty($ast))
                {
                    $msg = 'NAV Produk '.$ast->product_name.' turun '.number_format($ast->return_1day).'.';
                        
                    $notif[] = $msg;                                              
                }
                $act    = empty($intvl->id) ? 'created' : 'updated';
                $data = [
                    'investor_id'   => $ast['investor_id'],
                    'notif_title'   => 'Manage Nav',
                    'notif_desc'    => $msg,
                    'notif_email'   => '1',
                    'notif_api'     => '0',
                    'notif_mobile'  => '',
                    $act.'_by'      => 'System',
                    $act.'_host'    => '::1',
                    'is_active'     => 'Yes'

                        ];
                  NotificationInvestor::create($data);
            }
            
            return $this->app_response('Notif Rebelancing', $notif);        
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }

    public function send_message_wms()
    {
        try
        {
            $res = [];
            $data = NotificationInvestor::join('u_investors as b', 'h_notification_investor.investor_id', '=', 'b.investor_id')
                    ->where([['h_notification_investor.is_active', 'Yes'], ['h_notification_investor.notif_api', 'false'], ['b.is_active', 'Yes']])
                    ->get();

            foreach ($data as $dt)
            {
                $email          = $dt->notif_email ? $dt->email : '';
                $mobile_phone   = $dt->mobile_phone;
                $val            = [$dt->fullname, $dt->fullname, $email, $mobile_phone, $dt->notif_desc];

                $api = $this->api_ws(['sn' => 'Message', 'val' => $val])->original;
                if(!empty($api['code']) && $api['code'] == 200)
                {
                    NotificationInvestor::where('id', $dt->id)->update(['notif_api' => true]);
                    $res[] = $api['data'];
                }else{
                    $res[] = $api;
                }
            }
            return $this->app_response('Message WMS', $res);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        } 
    }
}
