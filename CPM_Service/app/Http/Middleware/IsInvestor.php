<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as Auth;

class IsInvestor
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (($request->fullUrl() != route('email.request.verification') && $request->fullUrl() != route('users.auth.investor')) && (!$request->user() || !$request->user()->hasVerifiedEmail()))
        {
            throw new AuthorizationException('Unauthorized, your email address '. $request->user() .' is not verified.');
        }
        return $next($request);
    }
}
