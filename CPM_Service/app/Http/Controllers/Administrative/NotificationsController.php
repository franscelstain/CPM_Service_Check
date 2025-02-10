<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\AppController;
use App\Interfaces\NotificationRepositoryInterface;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\Financial\Planning\Goal\InvestmentDetail;
use App\Models\Administrative\Notification\Notification;
use App\Models\Administrative\Notification\NotificationInterval;
use App\Models\Administrative\Notify\NotificationInvestor;
use App\Models\Transaction\TransactionHistory;
use App\Models\Transaction\TransactionHistoryDay;
use Illuminate\Http\Request;
use Auth;

class NotificationsController extends AppController
{
    protected $notificationRepo;

    public function __construct(NotificationRepositoryInterface $notificationRepo)
    {
        $this->notificationRepo = $notificationRepo;
    }

 	public function index()
    {
        try
        {
            $notif = $this->notificationRepo->getActiveNotifications();
            return $this->app_response('Notification Setup', ['key' => 'id', 'list' => $notif]);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
 	}

    public function detail($id)
    {
        // Gunakan is_numeric untuk memeriksa apakah input ID valid sebagai angka
        if (!is_numeric($id) || (string)(int)$id !== (string)$id) {  // ID bisa berupa angka atau string digit
            return $this->app_response('Invalid ID', [], ['error_code' => 400, 'error_msg' => ['The ID must be a valid integer.']]);
        }

        try
        {
            // Panggil repository untuk mendapatkan detail notifikasi
            $notif = $this->notificationRepo->getNotificationDetailById($id);

            if (!$notif) {
                return $this->app_response('Notification Not Found', [], ['error_code' => 404, 'error_msg' => ['Notification not found.']]);
            }
            
            return $this->app_response('Notification Detail', $notif);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function save(Request $request, $id = null)
    {
        try {
            $validationErrors = $this->validateRequest($request, Notification::rules());
            // Jika ada error validasi, return response error
            if ($validationErrors) {
                return $this->app_response('Validation Failed', [], $validationErrors);
            }

            // Validasi manual untuk "H-" dan "H+" di reminder
            if (isset($request->reminder) && is_array($request->reminder)) {
                $errors = [];
                foreach ($request->reminder as $index => $reminderValue) {
                    // Jika reminder adalah "H-" atau "H+", count_reminder di indeks yang sama harus diisi
                    if (in_array($reminderValue, ['H-', 'H+'])) {
                        if (empty($request->count_reminder[$index]) || !is_numeric($request->count_reminder[$index])) {
                            $errors["count_reminder.$index"] = "The count_reminder at index $index must be a number and is required when reminder is '$reminderValue'.";
                        }
                    }
                }

                // Jika validasi gagal, return error
                if (!empty($errors)) {
                    return $this->app_response('Validation Failed', [], ['error_code' => 422, 'error_msg' => $errors]);
                }
            }

            // Panggil db_manager untuk mendapatkan data pengguna dan IP
            $manager = $this->db_manager($request);

            // Siapkan data yang diperlukan dari request
            $data = [
                'title' => $request->title,
                'text_message' => $request->text_message,
                'notif_code' => $request->notif_code,
                'email_content_id' => $request->email_content_id,
                'redirect' => $request->redirect,
                'assign_to' => !empty($request->assign_to) ? json_encode($request->assign_to) : null,
                'notif_web' => !empty($request->notif_web) ? $request->notif_web : 'f',
                'notif_mail' => !empty($request->notif_mail) ? $request->notif_mail : 'f',
                'notif_mobile' => !empty($request->notif_mobile) ? $request->notif_mobile : 'f'
            ];

            // Ambil data untuk NotificationInterval
            $intervals = [
                'reminder' => $request->reminder,
                'count_reminder' => $request->count_reminder,
                'continuous' => $request->continuous
            ];

            // Tentukan apakah insert atau update
            if ($id) {
                $msg = 'Updated';
                // Update notifikasi
                $notification = $this->notificationRepo->updateNotification($data, $intervals, $id, $manager);

                // Jika update gagal karena ID tidak ditemukan, return error
                if (isset($notification['error_msg'])) {
                    return $this->app_response('Notification Not Found', [], $notification);
                }
            } else {
                $msg = 'Created';
                // Insert notifikasi
                $notification = $this->notificationRepo->insertNotification($data, $intervals, $manager);
            }

            return $this->app_response('Notification ' . $msg, $notification);

        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function delete($id)
    {
        try {
            // Validasi ID
            if (!is_numeric($id) || (string)(int)$id !== (string)$id) {
                return $this->app_response('Invalid ID', [], ['error_code' => 400, 'error_msg' => ['The ID must be a valid integer.']]);
            }
    
            // Panggil method dari repository untuk menghapus notification
            $isDeleted = $this->notificationRepo->deleteNotificationById((int)$id);
    
            // Jika tidak ditemukan notification yang dihapus
            if (!$isDeleted) {
                // Kembalikan pesan "Notification Not Found"
                return $this->app_response('Notification Not Found', [], ['error_code' => 404, 'error_msg' => ['Notification not found.']]);
            }
    
            return $this->app_response('Notification Deleted', ['id' => $id]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }
    
    public function interval($id)
    {
        try {
            // Validasi ID
            if (!is_numeric($id) || (string)(int)$id !== (string)$id) {
                return $this->app_response('Invalid ID', [], ['error_code' => 400, 'error_msg' => ['The ID must be a valid integer.']]);
            }

            // Gunakan repository untuk mendapatkan interval notifikasi
            $data = $this->notificationRepo->getNotificationIntervals($id);
            
            return $this->app_response('Notif Interval', $data);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }
	
	public function get_data()
    {
        try {
            // Ambil ID investor dari Auth
            $investorId = Auth::id();

            // Gunakan repository untuk mengambil data dan badge
            $data = $this->notificationRepo->getData($investorId);

            // Kembalikan respons dengan data yang diambil dari repository
            return $this->app_response('Notification', $data);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function badge_default($id)
    {
        try {
            // Validasi ID
            if (!is_numeric($id) || (string)(int)$id !== (string)$id) {
                return $this->app_response('Invalid ID', [], ['error_code' => 400, 'error_msg' => ['The ID must be a valid integer.']]);
            }

            // Update badge default menggunakan repository
            $this->notificationRepo->updateBadgeDefault($id);

            // Ambil badge terbaru dari repository berdasarkan investor ID (Auth::id())
            $badge = $this->notificationRepo->getBadge(Auth::id());

            return $this->app_response('Notification', ['msg' => 'Success', 'data' => $badge]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function notif_read($id)
    {
        try {
            // Validasi ID
            if (!is_numeric($id) || (string)(int)$id !== (string)$id) {
                return $this->app_response('Invalid ID', [], ['error_code' => 400, 'error_msg' => ['The ID must be a valid integer.']]);
            }

            // Update status notifikasi sebagai sudah dibaca
            $this->notificationRepo->markNotificationAsRead($id);

            // Ambil status notifikasi yang belum dibaca
            $notif_status = $this->notificationRepo->getNotificationStatus(Auth::id());

            return $this->app_response('Notification', ['state' => 'success', 'data' => $notif_status]);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
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
                        // $msg = 'Notifikasi saya, apabila nilai total balance pada portfolio goals '.$dt->portfolio_id.' saya kurang dari ideal balance. cek diportfolio goals '.$dt->portfolio_id.'.';
                        
                        $notif[] = $msg;
                        $act    = empty($intvl->id) ? 'created' : 'updated';

                        $data = [
                            'investor_id'   => $dt['investor_id'],
                            'notif_title'   => 'Notifikasi Rebalancing',
                            'notif_desc'    => $msg,
                            'notif_email'   => true,
                            'notif_mobile'  => true,
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
            $goals = Investment::where([['t_goal_investment.is_active', 'Yes']])->get();
            
            if (!empty($goals))
            {
                foreach ($goals as $dt)
                {
                    if (!empty($dt))
                    {
                        $balance    = TransactionHistoryDay::select('t_trans_histories_days.*', 'b.product_name')
                                    ->join('m_products as b', 't_trans_histories_days.product_id', '=', 'b.product_id')
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
                                'notif_email'   => true,
                                'notif_mobile'  => true,
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
                    'notif_email'   => true,
                    'notif_mobile'  => true,
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
}
