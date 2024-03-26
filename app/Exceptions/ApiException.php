<?php
namespace App\Exceptions;

use Exception;
class ApiException extends Exception
{
    function __construct($msg='')
    {
        parent::__construct($msg);
    }
     /**
         * 报告这个异常。
         *
         * @return void
         */
        public function report()
        {
        }

        /**
         * 将异常渲染至 HTTP 响应值中。
         *
         * @param  \Illuminate\Http\Request
         * @return \Illuminate\Http\Response
         */
        public function render($request)
        {
            //这里是对异常的处理
            // var_dump($request);
           // return response()->json(['code' => $this->getCode(), 'message' => $this->getMessage()],400);
            return response()->json(['code' => 10001, 'message' => $this->getMessage()],400);
        }
}
