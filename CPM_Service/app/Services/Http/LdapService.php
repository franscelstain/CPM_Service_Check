<?php

namespace App\Services\Http;

use Illuminate\Support\Facades\Log;
use LdapRecord\Container;

class LdapService
{
    public function login($username, $password) {
        $isUpn  = (bool) config('ldap.is_upn_login', true);
        $domain = (string) config('ldap.domain', '');
        $attr   = (string) config('ldap.user_dn_attr', 'uid');
        $baseDn = (string) config('ldap.connections.default.base_dn', '');

        $bindAs = $isUpn
            ? (strpos($username, '@') !== false ? $username : ($username . '@' . $domain))
            : ($attr . '=' . addcslashes(trim($username), ',+="#<>;\\') . ($baseDn ? ',' . $baseDn : ''));

        $mode = $isUpn ? 'UPN' : 'DN';

        try {
            $conn = Container::getDefaultConnection();
            $ok = $conn->auth()->attempt($bindAs, $password, true);
            if (!$ok) {
                Log::warning('LDAP login FAILED', ['bind_as' => $bindAs, 'mode' => $mode]);
                return [
                    'status' => 401,
                    'message' => 'Unauthorized: invalid username or password',
                    'errors' => ['Invalid LDAP credentials']
                ];
            }

            return [
                'status' => 200,
                'message' => 'Login Successful',
                'data' => [
                    'mode' => $mode,
                    'bind_as' => $bindAs,
                    'attrs' => $this->getAttributLdap($conn, $username)
                ]
            ];


        } catch (\Throwable $e) {
            Log::error('LDAP login EXCEPTION', ['err' => $e->getMessage()]);
            return [
                'status' => 500,
                'message' => 'LDAP authentication error',
                'errors' => [$e->getMessage()]
            ];
        }
    }

    private function getAttributLdap($conn, $username) {        
        $baseDn = (string) config('ldap.connections.default.base_dn', '');
        $attrs = [
            'cn','displayName','givenName','sn','mail','telephoneNumber',
            'userPrincipalName','sAMAccountName','uid',
            'memberOf','distinguishedName'
        ];

        if (strpos($username, '@') !== false) {
            $fval   = $this->escapeLdapFilter($username);
            $filter = '(userPrincipalName=' . $fval . ')';
        } else {
            $fval   = $this->escapeLdapFilter($username);
            $filter = '(|(sAMAccountName=' . $fval . ')(uid=' . $fval . '))';
        }

        $ldap = $conn->getLdapConnection(); // low-level
        $sr   = @$ldap->search($baseDn ?: '', $filter, $attrs);
        $entry = null;

        if ($sr) {
            $entries = @$ldap->getEntries($sr);
            if (is_array($entries) && (int)($entries['count'] ?? 0) > 0) {
                $e = $entries[0];

                // inline helpers: ambil single value & array values
                $get = function ($k) use ($e) {
                    $k = strtolower($k);
                    foreach ($e as $kk => $vv) {
                        if (is_string($kk) && strtolower($kk) === $k) {
                            if (is_array($vv) && isset($vv['count'])) return $vv['count'] > 0 ? $vv[0] : null;
                            return is_string($vv) ? $vv : null;
                        }
                    }
                    return null;
                };
                $getArr = function ($k) use ($e) {
                    $k = strtolower($k);
                    foreach ($e as $kk => $vv) {
                        if (is_string($kk) && strtolower($kk) === $k && is_array($vv)) {
                            $out = []; $cnt = (int)($vv['count'] ?? 0);
                            for ($i = 0; $i < $cnt; $i++) $out[] = $vv[$i];
                            return $out;
                        }
                    }
                    return [];
                };

                // Normalisasi minimal ke key "umum"
                $entry = [
                    'dn'    => $get('distinguishedName'),
                    'upn'   => $get('userPrincipalName'),
                    'uid'   => $get('uid'),
                    'name'  => (function () use ($get) {
                        $a = $get('displayName'); if (is_string($a) && trim($a) !== '') return $a;
                        $b = $get('cn');         if (is_string($b) && trim($b) !== '') return $b;
                        return null;
                    })(),
                    'mail'  => $get('mail'),
                    'phone' => $get('telephoneNumber'),
                    'login' => [
                        'samaccountname' => $get('sAMAccountName'),
                        'uid'            => $get('uid'),
                    ],
                    'memberOf' => $getArr('memberOf'),
                    // opsional: expose mentah juga kalau perlu debugging
                    // '_raw' => $e,
                ];
            }
        }

        return $entry;
    }

    // --- util kecil untuk filter LDAP (RFC4515 minimal) ---
    private function escapeLdapFilter($v)
    {
        $map = ['\\'=>'\5c','*'=>'\2a','('=>'\28',')'=>'\29',"\x00"=>'\00'];
        return strtr((string) $v, $map);
    }
}