<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
class BaseController extends Controller{


    //成功返回
    public function success($data,$msg="ok"){
        $this->parseNull($data);
        $result = [
            "code"=>200,
            "message"=>$msg,
            "data"=>$data,
        ];
        return response()->json($result,200);
    }


    //失败返回
    public function error($code=4000,$msg="fail"){
        $result = [
            "code"=>$code,
            "message"=>$msg,
        ];
        return response()->json($result,200);
    }

    //如果返回的数据中有 null 则那其值修改为空 （安卓和IOS 对null型的数据不友好，会报错）
    private function parseNull(&$data){
        if(is_array($data)){
            foreach($data as &$v){
                $this->parseNull($v);
            }
        }else{
            if(is_null($data)){
                $data = "";
            }
        }
    }
    public function handleBackData($data){
         $backData['result'] = $data->items();
         $backData['pageInfo'] =  ['currentPage'=>$data->currentPage(),'totalPage'=>$data->total()];
        return $backData;
    }

}
?>
