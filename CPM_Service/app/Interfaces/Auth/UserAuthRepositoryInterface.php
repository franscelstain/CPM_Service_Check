<?php

namespace App\Interfaces\Auth;

interface UserAuthRepositoryInterface
{
    public function clearUserToken($userId);
    public function checkOldPassword($userId, $oldPassword);
    public function login(array $credentials);
    public function updatePassword($userId, $newPassword);
}
