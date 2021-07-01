<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
		 if($request->is("api/*")){
            //如果错误是 ValidationException的一个实例，说明是一个验证的错误
            if($exception instanceof ValidationException){
                $result = [
                    "code"=>3000,
                    //这里使用 $exception->errors() 得到验证的所有错误信息，是一个关联二维数组，所以                使用了array_values()取得了数组中的值，而值也是一个数组，所以用的两个 [0][0]
                    "message"=>array_values($exception->errors())[0][0]
                ];
                return response()->json($result);
            }
            if($exception instanceof TokenInvalidException){
                return response()->json([
                    'code' => 4003,
                    'message' => '身份验证不通过！'
                ]);

            }
            if($exception instanceof TokenExpiredException){
                return response()->json([
                    'code' => 4002,
                    'message' => 'token过期，请重新登录！'
                ]);

            }
            if($exception instanceof JWTException){
               return response()->json([
                   'code' => 4001,
                   'message' => '请登录后请求数据！'
               ]);

            }

            //如果错误是 ValidationException的一个实例，说明是一个验证的错误
            if($exception instanceof QueryException){
                $result = [
                    "code"=>8000,
                    "message"=>'数据操作异常！'
                ];
                return response()->json($result);
            }
           return response()->json(['code' => 4000, 'message' => $exception->getMessage()],400);
        }
        if ($exception instanceof \App\Exceptions\ApiException)  {
             return $exception->render($request);
        }

        return parent::render($request, $exception);
    }
}
