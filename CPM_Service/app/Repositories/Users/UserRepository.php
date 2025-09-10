<?php

namespace App\Repositories\Users;

use App\Models\Auth\User as UserAuth;
use App\Models\Users\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function authUser($username)
    {
        return UserAuth::active()
            ->whereHas('category', function ($q) {
                $q->where('is_active', 'Yes');
            })
            ->where('username', $username)
            ->where('is_active', 'Yes')
            ->first();
    }

    public function deactivateUserById($userId)
    {
        return User::where('user_id', $userId)->update(['is_enable' => 'No']);
    }

    public function findByEmail($email)
    {
        return User::where('email', $email)
            ->where('is_active', 'Yes')
                ->first();
    }

    public function updateTokenById($userId, $token)
    {
        return User::where('user_id', $userId)->update(['token' => $token]);
    }

    public function updateTokenWithLastLDAPById($userId, $token)
    {
        return User::where('user_id', $userId)
                ->update([
                    'token' => $token,
                    'last_ldap_login_at' => Carbon::now()
                ]);
    }
}