<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AppController;
use App\Interfaces\Auth\UserAuthRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends AppController
{
    protected $authRepository;

    public function __construct(UserAuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param  Request  $request
     * @return Response
     */

    public function login(Request $request)
    {
        try {
            $validationErrors = $this->validateRequest($request, [
                'email' => 'required|string|email|max:255',
                'password' => 'required|string|min:6|max:255',
            ]);

            // Jika ada error validasi, return response error
            if ($validationErrors) {
                return $this->app_response('Validation Failed', [], $validationErrors);
            }

            $credentials = $request->only('email', 'password');
            $result = $this->authRepository->login($credentials);

            if (isset($result['error_code'])) {
                return $this->app_response('Login Failed', [], $result);
            }

            $msg = 'Login Success';
            $data = [
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'expires_in' => $result['expires_in'],
                'user' => $result['user'],
            ];

            return $this->app_response($msg, $data);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }	
	
    public function logout()
    {
        $data = $error = [];
        $message = 'Logout successful';
        $user = Auth::guard('admin')->user();

        if ($user) {
            // Bersihkan token di database
            $this->authRepository->clearUserToken($user->id);
    
            // Invalidasi token menggunakan Auth::guard
            Auth::guard('admin')->logout();
            $data = ['success' => true];
        } else {
            $message = 'Unauthorized';
            $error = ['error_code' => 401, 'error_msg' => ['User not authenticated']];
        }
    
        return $this->app_response($message, $data, $error);
    }
    
    public function user_auth()
    { 
        return $this->app_response('Auth', Auth::guard('admin')->user());
    }

    public function change_password(Request $request)
    {
        try
        {
            $error  = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data   = [];
            $user = Auth::guard('admin')->user();
            
            if ($user && $user->id) {
                // Validasi input
                $validationErrors = $this->validateRequest($request, [
                    'old_password' => 'required|min:8',
                    'password' => 'required|confirmed|min:8|different:old_password'
                ]);
    
                // Jika ada error validasi, return response error
                if ($validationErrors) {
                    return $this->app_response('Validation Failed', [], $validationErrors);
                }

                // Cek apakah password baru sama dengan email (username)
                if ($user->email == $request->input('password')) {
                    $error = ['error_code' => 422, 'error_msg' => ['Password cannot be the same as email']];
                } else { 
                    // Gunakan repository untuk memeriksa password lama
                    if (!$this->authRepository->checkOldPassword($user->id, $request->input('old_password'))) {
                        // Jika password lama salah
                        $error = ['error_code' => 422, 'error_msg' => ['Invalid old password']];
                    } else {
                        // Jika password lama benar, update password
                        $this->authRepository->updatePassword($user->id, $request->input('password'));
                        
                        $data = ['id' => $user->id];
                        $error = []; // Kosongkan error karena update berhasil
                    }
                }
            }
            
            return $this->app_response('Change Password', $data, $error);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
}