<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class UserTokenCheck
{
    /**
     * 身份验证.
     *
     * Author : xuewei
     * Date: 2020-05-1
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
public function handle($request, Closure $next)
{
    try {

        if (! $user = JWTAuth::parseToken()->authenticate()) {
            return response()->json([
                'code' =>4001 ,
                'message' => '用户不存在!'
            ]);
        }

    } catch (TokenExpiredException $e) {

        return response()->json([
            'code' => 4002,
            'message' => 'token过期，请重新登录！'
        ]);

    } catch (TokenInvalidException $e) {

        return response()->json([
            'code' => 4003,
            'message' => '身份验证不通过！'
        ]);

    } catch (JWTException $e) {
        return response()->json([
            'code' => 4001,
            'message' => '请登录后请求数据！'
        ]);

    }
    return $next($request);
}
}
