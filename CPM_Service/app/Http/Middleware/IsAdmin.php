<?php 

namespace App\Http\Middleware;

use Closure;
use Auth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::guard('admin')->check())
        {   
            return response()->json([
                'success'   => false, 
                'message'   => 'Unauthorized', 
                'data'      => [], 
                'errors'    => ['error_code' => 401, 'error_msg' => 'Please log in to continue or provide valid credentials.']
            ], 401);
        }   

        return $next($request);
    }
}