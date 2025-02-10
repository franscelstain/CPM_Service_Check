<?php

namespace App\Repositories\Auth;

use App\Interfaces\Auth\InvestorAuthRepositoryInterface;
use App\Models\Administrative\Config\Config;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\CardPriority;

class InvestorAuthRepository implements InvestorAuthRepositoryInterface
{
    public function checkCif($cif)
    {
        return CardPriority::where([['cif', $cif], ['is_active', 'Yes']])->first();
    }

    public function findByIdentity($identity_no)
    {
        return Investor::where([['is_active', 'Yes'], ['identity_no', $identity_no]])->first();
    }
    public function findByIdentityAndEmail($identity_no, $email)
    {
        return Investor::where([['is_active', 'Yes'], ['valid_account', 'Yes']])
            ->where(function ($qry) use ($identity_no, $email) {
                return $qry->where([['identity_no', $identity_no], ['valid_account', 'Yes']])
                    ->orWhere('email', $email);
            })->count();
    }

    public function getPasswordRules()
    {
        // Ambil aturan password dari tabel Config dengan config_name
        $configs = Config::whereIn('config_name', [
            'PasswordLength',
            'PasswordComplexityAlphabet',
            'PasswordComplexityNumeric',
            'PasswordComplexitySymbol',
            'PasswordComplexityUppercase'
        ])->where('config_type', 'Password')
        ->where('is_active', 'Yes')
        ->get()->pluck('config_value', 'config_name');

        // Jika tidak ada config, langsung return array kosong
        if ($configs->isEmpty()) {
            return [];
        }

        // Aturan dinamis dari database
        $rules = [];

        if ($configs->has('PasswordLength')) {
            $rules['min_length'] = $configs->get('PasswordLength');
        }

        if ($configs->get('PasswordComplexityUppercase') === 'Yes') {
            $rules['uppercase'] = true;
        }

        if ($configs->get('PasswordComplexityAlphabet') === 'Yes') {
            $rules['lowercase'] = true;
        }

        if ($configs->get('PasswordComplexityNumeric') === 'Yes') {
            $rules['number'] = true;
        }

        if ($configs->get('PasswordComplexitySymbol') === 'Yes') {
            $rules['special'] = true;
        }

        return $rules;
    }

    public function updateInvestor($investor, array $data)
    {
        Investor::where('investor_id', $investor->investor_id)->update([
            "is_active" => "Yes",
            "identity_no" => $data['identity_no'],
            "email" => $data['email'],
            "password" => app('hash')->make($data['password']),
            "updated_by" => 'Investor:'. $investor->investor_id .':'. $investor->fullname,
            "updated_host" => $data['ip']
        ]);
    }
}
