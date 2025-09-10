<?php

namespace App\Interfaces\Auth;

interface InvestorAuthRepositoryInterface
{
    public function checkCif($cif);    
    public function findByIdentity($identity_no);
    public function findByIdentityAndEmail($identity_no, $email);
    public function getPasswordRules();
    public function updateInvestor($investor, array $data);
}
