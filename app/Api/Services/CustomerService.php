<?php
namespace App\Api\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Api\Models\Customer\Customer as CustomerModel;
use App\Api\Models\Company\CompanyVariable;
// use App\Api\Models\Customer\CustomerLeaseback as CustomerLeasebackModel;
use App\Api\Models\Customer\CustomerFollow as CustomerFollowModel;
use App\Api\Models\Customer\CustomerRemind;
use App\Api\Models\Customer\CustomerInvoice as InvoiceModel;
/**
 *
 */
class CustomerService
{

  /**
   * 根据客户ID 客户状态更新
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [type]     $cusState [description]
   * @param    [type]     $cusId    [description]
   * @return   [type]               [description]
   */
  public function updateCusState($cusState,$cusId){
    $customer = CustomerModel::find($cusId);
    $customer->cus_state = $cusState;
    $res = $customer->save();
    return $res;
  }

  public function getCusNameById($cusId)
  {
    $res = CustomerModel::select('cus_name')->whereId($cusId)->first();
    return $res['cus_name'];
  }

  /**
   * 通过Company id 获取客户编号
   *
   * @Author   leezhua
   * @DateTime 2020-06-06
   * @param    [type]     $companyId [description]
   * @return   [type]                [description]
   */
  public function getCustomerNo($companyId){

    $res  = CompanyVariable::find($companyId);

    if(!$res){
      $res = $this->saveVariable($DA['company_id'],$DA['cus_prefix']);
      $no = $res['cus_no'];
    }else{
      $data['cus_no'] = $res['cus_no'] + 1;
      $res->cus_no    = $res['cus_no'] + 1;
      $res->save();
      $no = $res['cus_no'];
    }
    $customerNo = $res['cus_prefix'].$companyId.str_pad($no,5,0,STR_PAD_LEFT);
    return $customerNo;
  }


  /**
   * 客户编号前缀编辑
   * @Author   leezhua
   * @DateTime 2020-06-06
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function saveCusNo($DA){
    if (!isset($DA['company_id'])) {
      return false;
    }
    $customerVariables  = CompanyVariable::find($DA['company_id']);
    if(!$customerVariables){
      $customerVariables = $this->saveVariable($DA['company_id'],$DA['cus_prefix']);
    }else{
      $customerVariables->cus_prefix = $DA['cus_prefix'];
      $res = $customerVariables->save();
    }
    return $customerVariables;
  }

  // /** 退租模型 */
  // public function leaseBackModel(){
  //   $model = new CustomerLeasebackModel;
  //   return $model;
  // }

  /**
   * [保存跟进提醒]
   * @Author   leezhua
   * @DateTime 2020-06-27
   * @param    Array      $DA   [description]
   * @param    [Array]     $user [description]
   * @return   [type]           [description]
   */
  public function saveRemind($cusId,$remindDate,$user,$content=""){

    $remind = new CustomerRemind();
    $remind->company_id = $user['company_id'];
    $remind->cus_id = $cusId;

    $remind->cus_name = $this->getCusNameById($cusId);
    $remind->cus_remind_date = $remindDate;
    $remind->cus_remind_content = isset($DA['cus_remind_content']) ?$DA['cus_remind_content']:"跟进提醒";
    $remind->c_uid = $user['id'];
    $remind->remind_user = $user['realname'];
       // $remind->remark = isset($DA['remark']) ? $DA['remark'] :"";
    $res = $remind->save();
    return $res;
  }

  /**
   * 客户 跟进新增以及保存
   * @Author   leezhua
   * @DateTime 2020-07-05
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function saveFollow($DA,$user)
  {
    try {
      DB::transaction(function () use ($DA,$user){
        if (isset($DA['id']) && $DA['id'] > 0 ) {
          $follow = CustomerFollowModel::find($DA['id']);
        }else{
          $follow = new CustomerFollowModel;
          $follow->company_id = $user['company_id'];
          $follow->cus_id = $DA['cus_id'];
        }

        $follow->cus_follow_type = $DA['cus_follow_type'];
        $follow->cus_state = $DA['cus_state'];
        $follow->cus_follow_record = $DA['cus_follow_record'];
        $follow->cus_follow_time = $DA['cus_follow_time'];
        $follow->cus_contact_id = isset($DA['cus_contact_id'])?$DA['cus_contact_id']:0;
        $follow->cus_contact_user = isset($DA['cus_contact_user']) ? $DA['cus_contact_user']:"";
        $follow->cus_loss_reason = isset($DA['cus_loss_reason']) ?$DA['cus_loss_reason']:"" ;
        $follow->c_uid = $user['id'];
        $follow->follow_username = $user['realname'];
        // 第几次跟进
        $followTimes = CustomerFollowModel::where('cus_id',$DA['cus_id'])->count();
        Log::error($followTimes);
        $follow->times = $followTimes +1;
        $res = $follow->save();
        //更新客户状态
        $this->updateCusState($follow->cus_state, $follow->cus_id);
        if (isset($DA['remind_date'])) {
          $this->saveRemind($follow->cus_id,$DA['remind_date'],$user);
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

   //cus_follow_type 1 来访 2 电话，3微信  ，4QQ、5其他
  public function followStat($map,$cusIds){

    DB::enableQueryLog();
    $laifang = CustomerFollowModel::select(DB::Raw('count(*) / count(distinct cus_id) avg'))
    ->where($map)
    ->whereHas('customer' , function ($q){
      $q->where('cus_state','成交客户');
    })->where('cus_follow_type',1)
    ->where(function ($q) use ($cusIds){
      $cusIds && $q->whereIn('cus_id',$cusIds);
    })
    ->first();

    $avg_follow = CustomerFollowModel::select(DB::Raw('count(*) as total,count(*)/count(distinct(cus_id)) as count'))->where($map)
    ->where(function ($q) use ($cusIds){
      $cusIds && $q->whereIn('cus_id',$cusIds);
    })
    ->first();

    $max_follow = CustomerFollowModel::select(DB::Raw('count(*) as count,cus_id'))
    ->where($map)
    ->where(function ($q) use ($cusIds){
      $cusIds && $q->whereIn('cus_id',$cusIds);
    })
    ->groupBy('cus_id')
    ->orderBy('count','desc')->first();

    $laifang_cus = CustomerFollowModel::select(DB::Raw('count(*) as count,cus_id'))
    ->where($map)
    ->where(function ($q) use ($cusIds){
      $cusIds && $q->whereIn('cus_id',$cusIds);
    })
    ->where('cus_follow_type',1)
    ->groupBy('cus_id')
    ->havingRaw('count >= 2')->get()->toArray();

    $following_cus = CustomerFollowModel::select(DB::Raw('count(distinct(cus_id)) as total'))
    ->where($map)
    ->where(function ($q) use ($cusIds){
      $cusIds && $q->whereIn('cus_id',$cusIds);
    })
    ->whereHas('customer',function ($q) {
      $q->where('cus_type',1);
    })->first();

    return array( ['lable' =>'成交平均来访','value' => numFormat($laifang['avg'])],
                  ['lable' =>'平均跟进次数' ,'value' => round($avg_follow['count'])],
                  ['lable' =>'最大跟进次数' ,'value' => $max_follow['count']],
                  ['lable' =>'来访大于2次','value' => sizeof($laifang_cus)],
                  ['lable' =>'总计跟进','value' => $avg_follow['total']],
                  ['lable' =>'跟进中客户','value' => $following_cus['total']]
    );

  }



  /**
   * 创建客户日志
   * @param {Object} $data
  */
  function createCustomerLog($data){
      $model = new \App\Api\Models\Customer\CustomerLog;
      $model->customer_id = $data['customer_id'];
      $model->company_id = $data['company_id'];
      $model->content = $data['content'];
      $model->c_uid = $data['c_uid'];
      $model->c_username = $data['c_username'];
      $result = $model->save();
      return $result;
   }

  /** 保存客户编号 默认为CUS */
  private function saveVariable($companyId,$cus_prefix){
    $variable = new CompanyVariable();
    $variable->no =1;
    $variable->company_id = $companyId;
    $variable->cus_prefix = isset($cus_prefix) ? $cus_prefix :'CUS';
    $variable->save();
    return $variable;
  }
  // 1来访，2 电话，3微信  ，4QQ、5其他
  public function getFollowType($type){
    switch ($type) {
      case '1':
        return '来访';
        break;
      case '2':
        return '电话';
        break;
      case '3':
        return '微信';
        break;
      case '4':
        return 'QQ';
        break;
      case '5':
        return '其他';
        break;
    }
  }



}