<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\AppController;
use App\Models\Users\User;
use App\Models\Users\InvestorPasswordAttemp;
use App\Models\Users\UserPasswordAttemp;
use Illuminate\Http\Request;

class UsersController extends AppController
{
    public $table = 'Users\User';

    public function index()
    {
        try
        {
            $data   = User::select('u_users.*', 'b.usercategory_name')
                    ->join('u_users_categories as b', 'u_users.usercategory_id', '=', 'b.usercategory_id')
                    ->where([['b.is_active', 'Yes'], ['u_users.is_active', 'Yes']])
                    ->whereNotIn('b.usercategory_name', ['Sales', 'Investor'])
                    ->get();
            return $this->app_response('User', ['key' => 'user_id', 'list' => $data]);
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
            $file = $request->file('photo_profile');
            $error  = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data   = [];
            if ($this->auth_user()->id)
            {
                if (!empty($this->app_validate($request, ['photo_profile' => 'required|image|mimes:jpeg,png,jpg,gif'])))
                
                $request->request->add(['fullname' => $this->auth_user()->fullname]);
                $this->db_save($request, $this->auth_user()->id, ['validate' => true, 'path' => 'users/img']);
                
                $user   = User::find($this->auth_user()->id);
                $data   = ['img' => $user->photo_profile];
                $error  = [];
            }
             
            return $this->app_response('Change Photo', $data, $error);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }   
    }

    public function detail($id)
    {
        $data = (object) ['user_id' => ''];
        if ($id != 'x')
        {
			$data   = User::select('u_users.*')
                    ->join('u_users_categories as b', 'u_users.usercategory_id', '=', 'b.usercategory_id')
                    ->where([['user_id', $id],['b.is_active', 'Yes']])
                    // ->whereIn('b.usercategory_name', ['Super Admin'])
                     ->whereNotIn('b.usercategory_name', ['Sales', 'Investor'])
                    ->first();
            $id     = 'x';
		}
        return !empty($data->user_id) ? $this->app_response('User Detail', $data) : $this->db_detail($id); 
    }

    public function save(Request $request, $id = null)
    {
        try
        {
            $saveby = $this->auth_user()->usercategory_name.':'.$this->auth_user()->id.':'.$this->auth_user()->fullname;
            if ($request->method() == 'DELETE')
            {

                User::where('user_id', $id)->update(['is_active' => 'No', 'updated_by' => $saveby]);

            }
            else
            {
                // if (!empty($this->app_validate($request, User::rules($id, $request))))
                // {
                //     exit();
                // }
                
                $fullname   = $request->input('fullname');
                $email      = $request->input('email');
                $password   = $request->input('password');
                $is_active  = $request->input('is_active');
                $is_enable  = $request->input('is_enable');
                    
                $st     = $request->method() == 'POST' && empty($id) ? 'cre' : 'upd';
                $data   = ['fullname'           => $fullname,
                           'email'              => $email,
                           'is_active'          => $is_active,
                           'usercategory_id'    => $request->input('usercategory_id'),
                           $st.'ated_by'        => $saveby,
                           $st.'ated_host'      => $request->input('ip'),
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
            return $this->app_partials(1, 0, ['id' => empty($id) ? $qry->id : $id]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}