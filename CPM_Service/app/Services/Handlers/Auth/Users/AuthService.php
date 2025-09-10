<?php

namespace App\Services\Handlers\Auth\Users;

use App\Repositories\Auth\UserPasswordAttemptRepository;
use App\Repositories\Users\UserRepository;
use App\Services\Http\LdapService;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    protected $userRepo;
    protected $ldapService;
    protected $attemptRepo;

    public function __construct(
        UserRepository $userRepo, 
        LdapService $ldapService,
        UserPasswordAttemptRepository $attemptRepo
    ){
        $this->userRepo = $userRepo;
        $this->ldapService = $ldapService;
        $this->attemptRepo = $attemptRepo;
    }

    public function loginUser(array $credentials, int $maxAttempts = 5) {
        try {
            $token = Auth::guard('admin')->attempt($credentials);
            if (!$token) {
                return $this->handleFailedLogin($credentials['email'], $maxAttempts);
            }

            $user = Auth::guard('admin')->user();

            if ($user->is_active === 'No') {
                return [
                    'status' => 404,
                    'message' => 'User not registered',
                    'errors' => ['Sorry, your account is not registered']
                ];
            }
            elseif ($user->is_enable === 'No') {
                return [
                    'status' => 401,
                    'message' => 'Account Blocked',
                    'errors' => ['Sorry, your account has been disabled']
                ];
            }

            $this->attemptRepo->resetAttempts($user->id);

            return  [
                'status' => 200,
                'message' => 'Login Successful',
                'data' => [
                    'token' => $token,
                    'expires_in' => config('jwt.ttl') * 60, // in seconds
                    'user' => $user
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Internal Server Error',
                'errors' => [$e->getMessage()]
            ];
        }
    }

    public function ldapLogin(array $credentials) {
         
        try {
            $ldap = $this->ldapService->login($credentials['username'], $credentials['password']);
            if ($ldap['status'] === 200) {
                $user = $this->userRepo->authUser($credentials['username']);
                if ($user) {
                    if ($user->is_enable === 'No') {
                        return [
                            'status' => 401,
                            'message' => 'Account Blocked',
                            'errors' => ['Sorry, your account has been disabled']
                        ];
                    }
                    
                    $token = Auth::guard('admin')->login($user);
                    Auth::factory()->setTTL(180);
                    
                    $this->userRepo->updateTokenWithLastLDAPById($user->user_id, $token);

                    return [
                        'status' => 200,
                        'message' => 'Login Successful',
                        'data' => [
                            'token' => $token,
                            'expires_in' => config('jwt.ttl') * 60, // in seconds
                            'user' => $user
                        ]
                    ];

                } else {
                    return [
                        'status' => 404,
                        'message' => 'User not registered',
                        'errors' => ['User exists in LDAP but not found in local DB']
                    ];
                }
            } else {
                return $ldap;
            }            
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Internal Server Error',
                'errors' => [$e->getMessage()]
            ];
        }
    }

    protected function isTokenValid($token): bool
    {
        try {
            JWTAuth::setToken($token)->authenticate();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function handleFailedLogin($email, $maxAttempts)
    {
        $user = $this->userRepo->findByEmail($email);
        if ($user) {
            if ($user->is_enable === 'No') {
                return [
                    'status' => 401,
                    'message' => 'Account Blocked',
                    'errors' => ['Sorry, your account has been disabled']
                ];
            }

            $attemptCount = $this->attemptRepo->incrementAttempt($user->user_id);
            if ($attemptCount >= $maxAttempts) {
                $this->userRepo->deactivateUserById($user->user_id);
                return [
                    'status' => 403,
                    'message' => 'Account Blocked',
                    'errors' => ["Account has been blocked due to $maxAttempts failed login attempts."]
                ];
            } else {
                return [
                    'status' => 401,
                    'message' => 'Login Failed',
                    'errors' => ["Incorrect password. Attempt $attemptCount of $maxAttempts."]
                ];
            }
        } else {
            return [
                'status' => 404,
                'message' => 'Account Not Found',
                'errors' => ['Email not registered or account not found.']
            ];
        }
    }
}