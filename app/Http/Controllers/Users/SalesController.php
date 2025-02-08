<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\AppController;
use App\Models\SA\Transaction\Reference;
use App\Models\Users\Category;
use App\Models\Users\Investor\Investor;
use App\Models\Users\InvestorPasswordAttemp;
use App\Models\Users\User;
use App\Models\Users\UserPasswordAttemp;
use App\Models\Users\SalesBranch;
use App\Models\Users\Investor\CardPriority;
use App\Models\SA\Assets\AumTarget;
use App\Models\Users\Investor\AumProvision; 
use App\Models\Transaction\TransactionHistoryDay;
use App\Models\Users\UserSalesDetail; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Auth;

class SalesController extends AppController
{
    public $table = 'Users\User';

    public function index()
    {
        try
        {
            $sales  = User::select('u_users.*', 'b.usercategory_name')
                    ->join('u_users_categories as b', 'u_users.usercategory_id', '=', 'b.usercategory_id')
                    ->where([['u_users.is_active', 'Yes'], ['b.usercategory_name', 'Sales'], ['b.is_active', 'Yes']])
                    ->get();
            return $this->app_response('Sales', ['key' => 'user_id', 'list' => $sales]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function branch($salesid)
    {
        try
        {
            $data = SalesBranch::where([['sales_id', $salesid], ['is_active', 'Yes']])->first();
            return $this->app_response('Sales Branch', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
        try
        {     
            $manage = $this->db_manager($request);
            if ($request->method() == 'DELETE')
            {
                User::where('user_id', $id)->update(['is_active' => 'No', 'updated_by' => $manage->user]);
            }
            else
            {
                // if (!empty($this->app_validate($request, User::rules($id, $request))))
                // {
                //     exit();
                // }

                $category   = Category::where([['usercategory_name', 'Sales'], ['is_active', 'Yes']])->first();
                $fullname   = $request->input('fullname');
                $email      = $request->input('email');
                $password   = $request->input('password');
                $is_enable   = $request->input('is_enable');
  
                if (!empty($category->usercategory_id))
                {
                     $user   = !empty($id) ? User::where('user_id', $id)->first() : [];
                    $ext    = !empty($user->ext_code) ? $user->ext_code : $request->user_code;
                    $st     = $request->method() == 'POST' && empty($id) ? 'cre' : 'upd';
                    $data   = ['fullname'           => $fullname,
                               'email'              => $email,
                               'usercategory_id'    => $category->usercategory_id,
                               'leader_id'          => !empty($request->leader_id) ? $request->leader_id : null,
                               'user_code'          => !empty($request->user_code) ? $request->user_code : null,
                               'date_of_birth'      => !empty($request->date_of_birth) ? $request->date_of_birth : null,
                               'mobile_phone'       => !empty($request->mobile_phone) ? $request->mobile_phone : null,
                               'ext_code'           => !empty($ext) ? $ext : null,
                               $st.'ated_by'        => $manage->user,
                               $st.'ated_host'      => $manage->ip,
                               'is_enable'          => $is_enable
                               ];
                    
                    if (!empty($password))
                        $data = array_merge($data, ['password' => app('hash')->make($password)]);

                    $qry = $request->method() == 'POST' && empty($id) ? User::create($data) : User::where('user_id', $id)->update($data);

                    if ($qry && $request->input('sendmail') == 'Yes')
                        $this->app_sendmail(['to' => $email, 'content' => 'New User', 'new' => [$fullname, $email, $password, $this->app_date()]]);                    
        
                    if($is_enable == 'Yes') 
                    {
                        UserPasswordAttemp::where('user_id', empty($id) ? $qry->id : $id)->update(['is_active' => 'No','attempt_count' => 0]); 
                    }                        
                }
            }
            return $this->app_partials(1, 0, ['id' => empty($id) ? $qry->id : $id]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert     = $update = 0;
            $data       = [];            
            $category   = Category::where([['usercategory_name', 'Sales'], ['is_active', 'Yes']])->first();
            $api        = $this->api_ws(['sn' => 'Sales'])->original['data'];

            if (!empty($category->usercategory_id))
            {
                $managed        = $this->db_manager($request);
                $leader_code    = [];
                foreach ($api as $a)
                {
                    if (!empty($a->salesCode))
                    {
                        $qry    = User::where([['ext_code', $a->salesCode], ['is_active', 'Yes']])->first();
                        $id     = !empty($qry->user_id) ? $qry->user_id : null;
                        $act    = empty($qry->user_id) ? 'cre' : 'upd';
                        $email  = !empty($qry->user_id) ? $qry->email : $a->email;
                        $data   = [
                            'usercategory_id'   => $category->usercategory_id,
                            'user_code'         => $a->salesCode,
                            'fullname'          => $a->salesName,
                            'email'             => $email,
                            'date_of_birth'     => !empty($a->birthDate) ? $a->birthDate : null,
                            'mobile_phone'      => !empty($a->handphoneNo) ? $a->handphoneNo : null,
                            'ext_code'          => $a->salesCode,
                            'is_data'           => !empty($id) ? $qry->is_data : 'WS',
                            $act.'ated_by'   => $managed->user,
                            $act.'ated_host' => $managed->ip
                        ];
                          
                        $sales_id   = empty($qry->user_id) ?  User::create($data) : User::where('user_id', $qry->user_id)->update($data);
                        $user_id    = empty($qry->user_id) ? $sales_id->user_id : $qry->user_id;

                        if (!empty($a->teamLeaderCode))
                            $leader_code[$user_id] = $a->teamLeaderCode;

                        $this->ws_salesbranch($a, $user_id, $managed);
                        
                        if (empty($id))
                            $insert++;
                        else
                            $update++;
                    }
                }
                $this->ws_dataleader($leader_code);
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['insert' => $insert, 'update' => $update]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    private function ws_dataleader($leader_code)
    {
        if (!empty($leader_code))
        {
            foreach ($leader_code as $l_key => $l_val)
                User::where('user_id', $l_key)->update(['leader_id' => $this->db_row('user_id', ['where' => [['ext_code', $l_val]]], 'Users\User')->original['data']]);
        }
    }

    private function ws_salesbranch($a, $user_id, $managed)
    {
        if (!empty($a->branchId) && !empty($a->branch))
        {
            $qry_s  = SalesBranch::where([['sales_id', $user_id]])->first();
            $id_s   = !empty($qry_s->branch_id) ? $qry_s->branch_id : null;
            $act    = empty($qry_s->branch_id) ? 'cre' : 'upd';
            $sb     = ['sales_id'       => $user_id,
                       'branch_code'    => $a->branchId, 
                       'branch_name'    => $a->branch,
                       'is_active'      => 'Yes',
                       $act.'ated_by'   => $managed->user,
                       $act.'ated_host' => $managed->ip
                      ];

            $ids    = empty($qry_s->branch_id) ? SalesBranch::create($sb) : SalesBranch::where('branch_id', $qry_s->branch_id)->update($sb);
        }
    }
    public function change_password(Request $request)
    {
    	try
        {
			$error  = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data   = [];
            if (Auth::guard('admin')->user()->id)
            {
				$validate = ['oldpassword' => 'required|min:8', 'password' => 'required|confirmed|min:8|different:oldpassword'];
                if (!empty($this->app_validate($request, $validate)))
					exit;
                if (Hash::check($request->input('oldpassword'), Auth::guard('admin')->user()->password)) 
                {
					      User::where('user_id', Auth::guard('admin')->user()->id)->update(['token' => null, 'password'=>app('hash')->make($request->input('password'))]);
					
					$data   = ['id' => Auth::guard('admin')->user()->id];
                    $error  = [];
                }
                else
                {
                    $error  = ['error_code' => 422, 'error_msg' => ['Invalid password']];
                }
            }
            return $this->app_response('Change Password', $data, $error);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function change_photo(Request $request)
    {
     try
        {
            $error  = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data   = [];

            if (Auth::guard('admin')->user()->id)
            {
                $file = $request->file('photo_profile');
                // return  $file;
                // $request->request->add(['photo_profile'=> $request->file('photo_profile'));
                $this->db_save($request, Auth::guard('admin')->user()->id, ['validate' => 'true']);
                
               //  $user   = Investor::find(Auth::id());
                $user   = User::find(Auth::guard('admin')->user()->id);
                // return  $user;
                $data   = ['img' => $file];
                $error  = [];
            }
            return $this->app_response('Change Photo', $data, $error);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }   
    }

    protected function form_ele()
    {
        return ['path' => 'investor/img'];
    }

    public function investor_list(Request $request) 
    {
		try
        {
            $investor  = Investor::select('u_investors.*')
                    ->where([['sales_id', $this->auth_user()->id], ['is_active', 'Yes']])
                    ->get();
            return $this->app_response('Investor', ['key' => 'investor_id', 'list' => $investor]);
         }
         catch (\Exception $e)
         {
             return $this->app_catch($e);
         }
    }

    /*public function drop_fund(Request $request) {
        $type = $request->type;
        $start_date = $request->start_date;
        $end_date   = $request->end_date;
        $data  = Investor::select('u_investors.*')
                ->where([['sales_id', $this->auth_user()->id], ['is_active', 'Yes']])
                ->get();
        $idx = 0;
        foreach ($data as $dt) 
        {
            $prior  = CardPriority::where([['cif', $dt->cif], ['is_active', 'Yes']])->first();
            $created_at = !empty($prior->created_at) ? $prior->created_at : '';
            $updated_at = !empty($prior->updated_at) ? $prior->updated_at : '';
            $is_priority = !empty($prior->is_priority) ? $prior->is_priority : false;
            $pre_approve = !empty($prior->pre_approve) ? $prior->pre_approve : false;
            if (!empty($prior) ) {
                $inv_prior   = ($prior->is_active and $prior->pre_approve)? true : false;
                $inv_prior   = ($prior->is_active and $prior->pre_approve)? 'Priority' : 'Non-Priority';
                $user_status = ($prior->is_active)? 'Active' : 'Non Active';
            } else {
                // note: akan ditambahkan nanti
                //$dt['drop_date']        = ' - ';
                //$dt['customer_type']    = ' - ';
                //$dt['user_status']      = ' - ';
                //$dt['salesname']        = $this->auth_user()->fullname;
                $inv_prior   = false;
                $inv_prior   = 'Non-Priority';
                $user_status =  'Non Active';
            }
            $dropdate = !empty($updated_at) || !empty($created_at) ? !empty($created_at) ? date_format($created_at,'d/m/Y') :date_format($updated_at,'d/m/Y') : '';
            // if (!empty($updated_at)) $dropdate = date_format($updated_at,'d/m/Y'); 
            $dt['cif']              = $dt->cif;
            $dt['drop_date']        = $dropdate; //date_format($prior->created_at,'d/m/Y'); //date_format(strtotime($dropdate), 'd/m/Y');
            $dt['customer_type']    = (!$is_priority and !$pre_approve)? 'Non Priority' : 'Priority';
            $dt['user_status']      = $user_status;
            $dt['salesname']        = $this->auth_user()->fullname;

            if ($start_date!='' and $end_date!='' and !( $created_at >= $start_date and  $created_at <= $end_date)) unset($data[$idx]);
            if ($start_date=='' and $end_date!='' and !( $created_at <= $end_date)) unset($data[$idx]);
            if ($start_date!='' and $end_date=='' and !( $created_at >= $start_date)) unset($data[$idx]);
            // if ($status!='' and $status!=$dt['user_status']) unset($data[$idx]);
            if ($type!='' and $type!=$inv_prior) unset($data[$idx]);
            $idx++;
        }
        return $this->app_response('investor', $data);   
    }*/

     public function drop_fund(Request $request) 
     {
        $hasil       = [];
        $type       = $request->type;
        $start_date = $request->start_date;
        $end_date   = $request->end_date;
        $limit      = !empty($request->limit) ? $request->limit : 10;
        $page       = !empty($request->page) ? $request->page : 1;
        $offset     = ($page-1)*$limit;
        $data   = Investor::select('u_investors.*')->where([['sales_id', $this->auth_user()->id],['is_active', 'Yes']]);
          
       
       
        if (!empty($request->search))
        {
           $data  = $data->where(function($qry) use ($request) {
                        $qry->where('u_investors.fullname', 'ilike', '%'. $request->search .'%')
                            ->orWhere('u_investors.cif', 'ilike', '%'. $request->search .'%')
                            ->orWhere('u_investors.sid', 'ilike', '%'. $request->search .'%');
                    });
        }
                 
        $idx = 0;
        foreach ($data->get() as $dt) 
        {
            $prior  = CardPriority::where('is_active', 'Yes')->whereIn('cif',[$dt->cif])->first();
                        // return $this->app_response('ccc', $prior);
            $created_at = !empty($prior->created_at) ? $prior->created_at : '';
            $updated_at = !empty($prior->updated_at) ? $prior->updated_at : '';
            $is_priority = !empty($prior->is_priority) ? $prior->is_priority : false;
            $pre_approve = !empty($prior->pre_approve) ? $prior->pre_approve : false;
            $dropdate = !empty($updated_at) || !empty($created_at) ? !empty($created_at) ? date_format($created_at,'d/m/Y') :date_format($updated_at,'d/m/Y') : '';

            if (!empty($prior) ) {
                $inv_prior   = false;
                $inv_prior   = 'Non-Priority';
                $user_status =  'Non Active';
                $hasil[] = [
                    'cif'           => $prior->cif,
                    'fullname'      => $prior->fullname,
                    'drop_date'     => $dropdate,
                    'customer_type' => (!$is_priority and !$pre_approve)? 'Non Priority' : 'Priority',
                    'user_status'   => $user_status,
                    'salesname'     => $this->auth_user()->fullname,
                    'inv_prior'     => false,
                    'inv_prior'     => 'Non-Priority',
                    'user_status'   => 'Non Active'
                ];
            }

            // if (!empty($prior) ) {
            //     $user_status = ($prior->is_active)? 'Active' : 'Non Active';
            //     $inv_prior   = ($prior->is_active and $prior->pre_approve)? true : false;
            //     $inv_prior   = ($prior->is_active and $prior->pre_approve)? 'Priority' : 'Non-Priority';
            //     $hasil[]     = [
            //         'cif'           => $dt->cif,
            //         'fullname'      => $dt->fullname,
            //         'drop_date'     => $dropdate,
            //         'customer_type' => (!$is_priority and !$pre_approve)? 'Non Priority' : 'Priority',
            //         'user_status'   => $user_status,
            //         'salesname'     => $this->auth_user()->fullname,
            //         'inv_prior'     => ($prior->is_active and $prior->pre_approve)? true : false,
            //         'inv_prior'     => ($prior->is_active and $prior->pre_approve)? 'Priority' : 'Non-Priority',
            //         'user_status'   => ($prior->is_active)? 'Active' : 'Non Active' 
            //     ];
            // } else {
            //     $inv_prior   = false;
            //     $inv_prior   = 'Non-Priority';
            //     $user_status =  'Non Active';
            //     $hasil[] = [
            //         'cif'           => $dt->cif,
            //         'fullname'      => $dt->fullname,
            //         'drop_date'     => $dropdate,
            //         'customer_type' => (!$is_priority and !$pre_approve)? 'Non Priority' : 'Priority',
            //         'user_status'   => $user_status,
            //         'salesname'     => $this->auth_user()->fullname,
            //         'inv_prior'     => false,
            //         'inv_prior'     => 'Non-Priority',
            //         'user_status'   => 'Non Active'
            //     ];
            // }

            //  if ($start_date!='' and $end_date!='' and !( $created_at >= $start_date and  $created_at <= $end_date)) unset($data[$idx]);
            // if ($start_date=='' and $end_date!='' and !( $created_at <= $end_date)) unset($data[$idx]);
            // if ($start_date!='' and $end_date=='' and !( $created_at >= $start_date)) unset($data[$idx]);
            // // if ($status!='' and $status!=$dt['user_status']) unset($data[$idx]);
            // if ($type!='' and $type!=$inv_prior) unset($data[$idx]);
            // $idx++;
        }
        $total = $data->count();
        $total_data = $page*$limit;
        $paginate = [
            'current_page'  => $page,
            'data'          => $hasil,
            'from'          => $page > 1 ?  1 + (($page-1) * $limit) : 1,
            'per_page'      => $limit,
            'to'            => $total_data >= $total ? $total : $total_data,
            'total'         => $total
        ];
        return $this->app_response('investor', $paginate);   
    }

    public function aum_priority(Request $request) 
    {
        $out        = [];
        $type       = $request->type;
        $start_date = $request->start_date;
        $end_date   = $request->end_date;
        $limit      = !empty($request->limit) ? $request->limit : 10;
        $page       = !empty($request->page) ? $request->page : 1;
        $offset     = ($page-1)*$limit;
        $aum        = AumTarget::where([['is_active', 'Yes']])->orderBy('effective_date', 'desc')->first();
        $app_date   = $this->is_date($this->app_date()) ? $this->app_date() : date('Y-m-d');
        $cat_id     = $aum->asset_category;
        
        if(!is_array($cat_id)) $cat_id=[$cat_id];
        $data  = Investor::selectRaw("u_investors.cif, u_investors.investor_id, u_investors.fullname, max(outstanding_date)as date, sum(balance_amount)as amount")
                    ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
                    ->leftJoin('m_products as mp', 'mp.product_id', 'tao.product_id')
                    ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                    ->where([['sales_id', $this->auth_user()->id], ['u_investors.valid_account', 'Yes'], ['u_investors.is_active', 'Yes'], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'] ])
                    ->WhereIn('mac.asset_category_id', $cat_id)   
                    ->groupBy(['u_investors.cif', 'u_investors.investor_id', 'u_investors.fullname' ]);
            
        if (!empty($request->search))
        {
            $data  = $data->where(function($qry) use ($request) {
                    $qry->where('u_investors.fullname', 'ilike', '%'. $request->search .'%')
                        ->orWhere('u_investors.cif', 'ilike', '%'. $request->search .'%');
                });
        }

        foreach ($data->get() as $dat)    
        {
            $amount_goal = TransactionHistoryDay::where([['investor_id', $dat->investor_id], ['history_date', $app_date], ['is_active', 'Yes']])->whereRaw("LEFT(portfolio_id, 1) = '2'")->sum('current_balance');
            $amount_nongoal = TransactionHistoryDay::where([['investor_id', $dat->investor_id], ['history_date', $app_date], ['is_active', 'Yes']])->where(function($qry) { $qry->whereRaw("LEFT(portfolio_id, 1) NOT IN ('2', '3')")->orWhereNull('portfolio_id'); })->sum('current_balance');
            $tot_amount = $amount_goal + $amount_nongoal; 

            if( $tot_amount < $aum->target_aum ) 
            {
                $prior  = CardPriority::where([['cif', $dat->cif], ['is_active', 'Yes']])->first();
                $last = $this->AUM_lastdate($dat->investor_id, $cat_id, $aum->target_aum);
                if(!is_null($last)) 
                {
                    $lastdate = date('Y-m-d',  strtotime($last['last_date']));
                    $diff = $this->dateDiff($lastdate, $this->app_date());
                } else {
                    $lastdate = date('Y-m-d');
                    $diff= 0;
                }

                if ( $last['last_amount']>0 and $last['last_amount']<$aum->target_aum )  {
                    /*
                        Priority
                            1. Is Priority = Yes
                            2. Is Pre Approve = No
                        Pre Approve
                            1. Is Priority = Yes
                            2. Is Pre Approve = Yes
                            or
                            1. Is Priority = No
                            2. Is Pre Approve = Yes
                        Non Priority
                            1. Is Priority = No
                            2. Is Pre Approve = No
                    */
                    $cust_type = '-';
                    if (isset($prior->is_priority) and isset($prior->pre_approve)) {
                        if ( $prior->is_priority && !$prior->pre_approve ) $cust_type = 'Priority';
                        if ( $prior->is_priority && $prior->pre_approve ) $cust_type = 'Pre-Approve';
                        if ( !$prior->is_priority && $prior->pre_approve ) $cust_type = 'Pre-Approve';
                        if ( !$prior->is_priority && !$prior->pre_approve ) $cust_type = 'Non-Priority';
                    }

                    if(empty($request->type))
                    {
                        $out[] = [ 
                            'investor_id'           => $dat->investor_id,
                            'fullname'              => $dat->fullname,
                            'cif'                   => $dat->cif,
                            'target_AUM_date'       => $aum->effective_date,
                            'target_AUM_amount'     => (float)$aum->target_aum,
                            'AUM_lastdate'          => $lastdate,
                            'AUM_lastamount'        => (float)$last['last_amount'],
                            'customer_type'         => $cust_type,
                            'current_AUM'           => (float)$dat->amount,
                            'days'                  => $diff,
                            'AUM_current'           => $this->amount_all($dat->investor_id),
                        ];
                    }

                    if($cust_type == $request->type)
                    {
                        $out[] = [ 
                            'investor_id'           => $dat->investor_id,
                            'fullname'              => $dat->fullname,
                            'cif'                   => $dat->cif,
                            'target_AUM_date'       => $aum->effective_date,
                            'target_AUM_amount'     => (float)$aum->target_aum,
                            'AUM_lastdate'          => $lastdate,
                            'AUM_lastamount'        => (float)$last['last_amount'],
                            'customer_type'         => $cust_type,
                            'current_AUM'           => (float)$dat->amount,
                            'days'                  => $diff,
                            'AUM_current'           => $this->amount_all($dat->investor_id),
                        ];
                    }

                    //save to DB
                    $savedata = [
                        'aum_lastdate'          => $lastdate,
                        'target_aum_amount'     => (float)$aum->target_aum,
                        'current_aum_amount'    => (float)$dat->amount,
                        'is_active'             => 'Yes',
                    ];
                    $aum_prov  = AumProvision::where([['investor_id', $dat->investor_id], ['is_active', 'Yes']])->first();
                    if (!empty($aum_prov))
                    {
                        //update
                        $key = $aum_prov->investors_aum_id;
                        $savedata = array_merge($savedata, [
                            'updated_by'    => (!empty($this->auth_user()))? $this->auth_user()->usercategory_name.':'.$this->auth_user()->id.':'.$this->auth_user()->fullname : '',
                            'updated_host'  => 'System'
                        ]);
                        AumProvision::where([['investors_aum_id', $key]])->update($savedata);
                    } else {
                        //insert
                        $savedata = array_merge($savedata, [
                            'investor_id'   => $dat->investor_id, 
                            'created_by'    => (!empty($this->auth_user()))? $this->auth_user()->usercategory_name.':'.$this->auth_user()->id.':'.$this->auth_user()->fullname : '',
                            'created_host'  => 'System' //$ip,
                        ]);
                        AumProvision::create($savedata);
                    }
                }
            }
        }
        $total = $data->count();
        $total_data = $page*$limit;
        $paginate = [
            'current_page'  => $page,
            'data'          => $out,
            'from'          => $page > 1 ?  1 + (($page-1) * $limit) : 1,
            'per_page'      => $limit,
            'to'            => $total_data >= $total ? $total : $total_data,
            'total'         => $total
        ];
        return $this->app_response('investor', $paginate); 
    }

    public function get_aum_priority() 
    {
        $out = [];
        $aum  = AumTarget::where([['is_active', 'Yes']])->orderBy('effective_date', 'desc')->first();
        $cat_id = $aum->asset_category;
        //foreach ($aumTarget as $aum) 
        { 
            $app_date = $this->is_date($this->app_date())? $this->app_date() : date('Y-m-d');
            if(!is_array($cat_id)) $cat_id=[$cat_id];
            $data  = Investor::selectRaw("u_investors.cif, u_investors.investor_id, u_investors.fullname, max(outstanding_date)as date, sum(balance_amount)as amount")
                        ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
                        ->leftJoin('m_products as mp', 'mp.product_id', 'tao.product_id')
                        ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                        ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                        //->where([['sales_id', $this->auth_user()->id], ['u_investors.valid_account', 'Yes'], ['u_investors.is_active', 'Yes'], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'], ['tao.outstanding_date', $app_date] ])
                        ->where([['u_investors.valid_account', 'Yes'], ['u_investors.is_active', 'Yes'], ['tao.is_active', 'Yes'], ['mp.is_active', 'Yes'], ['mac.is_active', 'Yes'], ['mact.is_active', 'Yes'] ])
                        ->WhereIn('mac.asset_category_id', $cat_id)   
                        ->groupBy(['u_investors.cif', 'u_investors.investor_id', 'u_investors.fullname' ])
                        ->get();
            foreach ($data as $dat)    
            {
                $amount_goal = TransactionHistoryDay::where([['investor_id', $dat->investor_id], ['history_date', $app_date], ['is_active', 'Yes']])->whereRaw("LEFT(portfolio_id, 1) = '2'")->sum('current_balance');
                $amount_nongoal = TransactionHistoryDay::where([['investor_id', $dat->investor_id], ['history_date', $app_date], ['is_active', 'Yes']])->where(function($qry) { $qry->whereRaw("LEFT(portfolio_id, 1) NOT IN ('2', '3')")->orWhereNull('portfolio_id'); })->sum('current_balance');
                $tot_amount = $amount_goal + $amount_nongoal; 

                if( $tot_amount < $aum->target_aum ) 
                {
                    $prior  = CardPriority::where([['cif', $dat->cif]])->first();
                    //if(!empty($prior))
                    {
                        $last = $this->AUM_lastdate($dat->investor_id, $cat_id, $aum->target_aum);
                        if(!is_null($last)) 
                        {
                            $lastdate = date('Y-m-d',  strtotime($last['last_date']));
                            $diff = $this->dateDiff($lastdate, $this->app_date());
                        } else {
                            $lastdate = date('Y-m-d');
                            $diff= 0;
                        }
                    }

                    if ( $last['last_amount']>0 and $last['last_amount']<$aum->target_aum )  {
                        $cust_type = '-';
                        if (isset($prior->is_priority) and isset($prior->pre_approve)) {
                            if ( $prior->is_priority && !$prior->pre_approve ) $cust_type = 'Priority';
                            if ( $prior->is_priority && $prior->pre_approve ) $cust_type = 'Pre Approve';
                            if ( !$prior->is_priority && $prior->pre_approve ) $cust_type = 'Pre Approve';
                            if ( !$prior->is_priority && !$prior->pre_approve ) $cust_type = 'Non Priority';
                        }

                        $out[] = [ 
                            'investor_id'           => $dat->investor_id,
                            'fullname'              => $dat->fullname,
                            'cif'                   => $dat->cif,
                            'target_AUM_date'       => $aum->effective_date,
                            'target_AUM_amount'     => (float)$aum->target_aum,
                            'AUM_lastdate'          => $lastdate,
                            'AUM_lastamount'        => (float)$last['last_amount'],
                            'customer_type'         => $cust_type,
                            //'current_AUM'           => (float)$dat->amount,
                            'days'                  => $diff,
                            'AUM_current'         => $this->amount_all($dat->investor_id),
                        ];

                        //save to DB
                        $savedata = [
                            'aum_lastdate'          => $lastdate,
                            'target_aum_amount'     => (float)$aum->target_aum,
                            'current_aum_amount'    => (float)$dat->amount,
                            'is_active'             => 'Yes',
                        ];
                        $aum_prov  = AumProvision::where([['investor_id', $dat->investor_id], ['is_active', 'Yes']])->first();
                        if (!empty($aum_prov))
                        {
                            //update
                            $key = $aum_prov->investors_aum_id;
                            $savedata = array_merge($savedata, [
                                'updated_by'    => 'System',
                                'updated_host'  => 'System'
                            ]);
                            AumProvision::where([['investors_aum_id', $key]])->update($savedata);
                        } else {
                            //insert
                            $savedata = array_merge($savedata, [
                                'investor_id'   => $dat->investor_id, 
                                'created_by'    => 'System',
                                'created_host'  => 'System' //$ip,
                            ]);
                            AumProvision::create($savedata);
                        }
                    } 
                }
            }
        }
        return $this->app_response('investor', $out);      
    }

    function dateDiff($date_1, $date_2, $differenceFormat='%a' )
    {
        $datetime1 = date_create($date_1);
        $datetime2 = date_create($date_2);
        $interval  = date_diff($datetime1, $datetime2);
        return $interval->format($differenceFormat);
    }

   function AUM_lastdate($inv_id, $asset_category_id, $aum_target) 
    {
        $data  = Investor::selectRaw('outstanding_date,sum(balance_amount)')
                    ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
                    ->leftJoin('m_products as mp', 'mp.product_id', 'tao.product_id')
                    ->leftJoin('m_asset_class as mac', 'mac.asset_class_id', 'mp.asset_class_id')
                    ->leftJoin('m_asset_categories as mact', 'mact.asset_category_id', 'mac.asset_category_id')
                    ->where([['u_investors.valid_account', 'Yes'], ['u_investors.is_active', 'Yes'],
                        ['u_investors.investor_id', $inv_id]
                    ])
                    ->whereIn('mac.asset_category_id', $asset_category_id)
                    ->groupBy('outstanding_date')
                    ->orderBy('outstanding_date', 'desc')
                    ->get();
        $last =[];
        foreach ($data as $dat) 
        {
            if ($last==[]) {$last = $dat;continue;} //first init data
            if ($dat->sum >= $aum_target) {
                // return ['last_date'=>$dat->outstanding_date, 'last_amount'=>$dat->sum];
                return ['last_date'=>$last->outstanding_date, 'last_amount'=>$last->sum];
            }
            $last = $dat;
        }
        if ($last!=[]) return ['last_date'=>$last->outstanding_date, 'last_amount'=>$last->sum];
        return null;
    }

    function amount_all($investor_id) 
    {
        $data  = Investor::selectRaw("u_investors.cif, u_investors.investor_id, max(outstanding_date)as date, sum(balance_amount)as amount")
                        ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'u_investors.investor_id')
                        ->where([['u_investors.valid_account', 'Yes'], ['u_investors.is_active', 'Yes'],
                            // ['u_investors.investor_id', $investor_id], ['outstanding_date', '2021-04-08']
                            ['u_investors.investor_id', $investor_id], ['outstanding_date', $this->app_date()]
                        ])
                        ->groupBy(['u_investors.cif', 'u_investors.investor_id'])
                        ->first();
        return (!empty($data))? (float)$data->amount : 0;
    }

    function sales()
    {
        try
        {
            $data  =  User::selectRaw("u_users.fullname, u_users.user_code, u_users.photo_profile, sum(balance_amount)as amount")
                        ->leftJoin('u_investors as ui', 'ui.sales_id', 'u_users.user_id')
                        ->leftJoin('t_assets_outstanding as tao', 'tao.investor_id', 'ui.investor_id')
                        ->where([['ui.valid_account', 'Yes'], ['ui.is_active', 'Yes'], ['tao.is_active', 'Yes'], ['u_users.is_active', 'Yes'], ['tao.outstanding_date', '>=',  date('Y-m-01', strtotime('-1 month'))], ['tao.outstanding_date', '<',  date('Y-m-01')]])   
                        ->groupBy(['u_users.user_id'])
                        ->limit(10)
                        ->get();
            return $this->app_response('Sales dashboard', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail_sub($id)
    {   
        $data = UserSalesDetail::where([['user_id', $id]])->first();
        return $this->app_response('Sales Detail', $data);
    }


    function is_date($str) 
    {
        //if (DateTime::createFromFormat('Y-m-d', $str) !== false) {
        if (date('Y-m-d', strtotime($str)) == $str) {
            return true; // it's a date
        } else {
            return false;
        }
    }

}