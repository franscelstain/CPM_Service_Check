<?php 

namespace App\Http\Middleware;

use Closure;
use Auth;

class AuthType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$auth_type)
    {
        $auth = Auth::check() ? Auth::user() : Auth::guard('admin')->user();
        if (!in_array($auth->usercategory_name, $auth_type))
        {
            return response()->json([
                'success'   => false, 
                'message'   => 'Permission Denied', 
                'data'      => [], 
                'errors'    => [
                    'error_code' => 403,
                    'error_msg'  => 'You do not have the required permissions.'
                ]
            ], 403);
        }
        return $next($request);
    }
}