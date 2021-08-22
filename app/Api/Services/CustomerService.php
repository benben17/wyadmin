<?php

namespace App\Api\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\Follow;
use App\Api\Models\Tenant\Remind;
use App\Api\Models\Tenant\Tenant;
use App\Api\Models\Tenant\TenantLog;
use App\Api\Services\Company\VariableService;

/**
 *
 */
class CustomerService
{

  public function tenantModel()
  {
    $model = new Tenant;
    return $model;
  }

  public function followModel()
  {
    $model = new Follow;
    return $model;
  }
  public function remindModel()
  {
    $model = new Remind;
    return $model;
  }
  public function tenantLogModel()
  {
    $model = new TenantLog;
    return $model;
  }
  /**
   * 根据客户ID 客户状态更新
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [type]     $cusState [description]
   * @param    [type]     $cusId    [description]
   * @return   [type]               [description]
   */


  public function getCusNameById($cusId)
  {
    $res = $this->tenantModel()->select('name')->whereId($cusId)->first();
    return $res['name'];
  }

  public function getTenantNo($companyId)
  {
    $var = new VariableService;
    return $var->getTenantNo($companyId);
  }

  /**
   * [保存跟进提醒]
   * @Author   leezhua
   * @DateTime 2020-06-27
   * @param    Array      $DA   [description]
   * @param    [Array]     $user [description]
   * @return   [type]           [description]
   */
  public function saveRemind($tenantId, $remindDate, $user, $content = "")
  {

    $remind = new Remind;
    $remind->company_id = $user['company_id'];
    $remind->tenant_id = $tenantId;

    $remind->tenant_name = $this->getCusNameById($tenantId);
    $remind->depart_id = getDepartIdByUid($user['id']);
    $remind->remind_date = $remindDate;
    $remind->remind_content = isset($DA['remind_content']) ? $DA['remind_content'] : "跟进提醒";
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
  public function saveFollow($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        if (isset($DA['id']) && $DA['id'] > 0) {
          $follow = Follow::find($DA['id']);
        } else {
          $follow = new Follow;
          $follow->company_id = $user['company_id'];
          $follow->tenant_id = $DA['tenant_id'];
        }
        $follow->depart_id = getDepartIdByUid($user['id']);
        $follow->proj_id = $DA['proj_id'];
        $follow->follow_type = $DA['follow_type'];
        $follow->state = $DA['state'];
        $follow->follow_record = $DA['follow_record'];
        $follow->follow_time = $DA['follow_time'];
        $follow->contact_id = isset($DA['contact_id']) ? $DA['contact_id'] : 0;
        $follow->contact_user = isset($DA['contact_user']) ? $DA['contact_user'] : "";
        $follow->loss_reason = isset($DA['loss_reason']) ? $DA['loss_reason'] : "";
        $follow->c_uid = $user['id'];
        $follow->follow_username = $user['realname'];
        if (isset($DA['next_date']) && $DA['next_date']) {
          $follow->next_date = $DA['next_date'];
          $this->saveRemind($follow->tenant_id, $DA['next_date'], $user);
        }
        // 第几次跟进
        $followTimes = Follow::where('tenant_id', $DA['tenant_id'])->count();
        Log::error($followTimes);
        $follow->times = $followTimes + 1;
        $res = $follow->save();
        //更新客户状态
        $this->tenantModel()->whereId($follow->tenant_id)->update(['state' => $follow->state]);
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  //cus_follow_type 1 来访 2 电话，3微信  ，4QQ、5其他
  public function followStat($map, $tenantIds)
  {

    DB::enableQueryLog();
    $laifang = Follow::select(DB::Raw('count(*) / count(distinct tenant_id) avg'))
      ->where($map)
      ->whereHas('customer', function ($q) {
        $q->where('state', '成交客户');
      })->where('follow_type', 1)
      ->where(function ($q) use ($tenantIds) {
        $tenantIds && $q->whereIn('tenant_id', $tenantIds);
      })
      ->first();

    $avg_follow = Follow::select(DB::Raw('count(*) as total,count(*)/count(distinct(tenant_id)) as count'))->where($map)
      ->where(function ($q) use ($tenantIds) {
        $tenantIds && $q->whereIn('tenant_id', $tenantIds);
      })
      ->first();

    $max_follow = Follow::select(DB::Raw('count(*) as count,tenant_id'))
      ->where($map)
      ->where(function ($q) use ($tenantIds) {
        $tenantIds && $q->whereIn('tenant_id', $tenantIds);
      })
      ->groupBy('tenant_id')
      ->orderBy('count', 'desc')->first();

    $laifang_cus = $this->followModel()->select(DB::Raw('count(*) as count,tenant_id'))
      ->where($map)
      ->where(function ($q) use ($tenantIds) {
        $tenantIds && $q->whereIn('tenant_id', $tenantIds);
      })
      ->where('follow_type', 1)
      ->groupBy('tenant_id')
      ->havingRaw('count >= 2')->get()->toArray();

    $following_cus = $this->followModel()->select(DB::Raw('count(distinct(tenant_id)) as total'))
      ->where($map)
      ->where(function ($q) use ($tenantIds) {
        $tenantIds && $q->whereIn('tenant_id', $tenantIds);
      })
      ->whereHas('tenant', function ($q) {
        $q->where('type', 1);
      })->first();

    return array(
      ['lable' => '成交平均来访', 'value' => numFormat($laifang['avg'])],
      ['lable' => '平均跟进次数', 'value' => round($avg_follow['count'])],
      ['lable' => '最大跟进次数', 'value' => $max_follow['count']],
      ['lable' => '来访大于2次', 'value' => sizeof($laifang_cus)],
      ['lable' => '总计跟进', 'value' => $avg_follow['total']],
      ['lable' => '跟进中客户', 'value' => $following_cus['total']]
    );
  }



  /**
   * 创建客户日志
   * @param {Object} $data
   */
  function createTenantLog($data)
  {
    $model = new TenantLog;
    $model->customer_id = $data['tenant_id'];
    $model->company_id = $data['company_id'];
    $model->content = $data['content'];
    $model->c_uid = $data['c_uid'];
    $model->c_username = $data['c_username'];
    $result = $model->save();
    return $result;
  }


  public function saveTenant($DA, $user, $type = 1)
  {
    try {
      if ($type == 1) {
        $tenant = $this->tenantModel();
        $tenant->tenant_no = $this->getTenantNo($user['company_id']);
        $tenant->c_uid = $user->id;
        $tenant->state = $DA['state'];
        $tenant->company_id = $user['company_id'];
        $tenant->type = 1;
      } else {
        $tenant = $this->tenantModel()->find($DA['id']);
        $tenant->u_uid = $user->uid;
      }
      $tenant->name = $DA['name'];
      $tenant->depart_id = getDepartIdByUid($user['id']);
      $tenant->source_type = isset($DA['source_type']) ? $DA['source_type'] : "";
      $tenant->room_type = isset($DA['room_type']) ? $DA['room_type'] : 1;
      $tenant->proj_id = isset($DA['proj_id']) ? $DA['proj_id'] : 0;
      $tenant->proj_name = isset($DA['proj_name']) ? $DA['proj_name'] : "";
      $tenant->industry = isset($DA['industry']) ? $DA['industry'] : "";
      $tenant->level = isset($DA['level']) ? $DA['level'] : "";
      $tenant->nature = isset($DA['nature']) ? $DA['nature'] : "";
      $tenant->worker_num = isset($DA['worker_num']) ? $DA['worker_num'] : 0;
      $tenant->visit_date = isset($DA['visit_date']) ? $DA['visit_date'] : "";
      $tenant->addr = isset($DA['addr']) ? $DA['addr'] : "";
      $tenant->belong_uid = isset($DA['belong_uid']) ? $DA['belong_uid'] : 0;
      $tenant->belong_person = isset($DA['belong_person']) ? $DA['belong_person'] : "";
      $tenant->channel_id = isset($DA['channel_id']) ? $DA['channel_id'] : 0;
      $tenant->channel_name = isset($DA['channel_name']) ? $DA['channel_name'] : "";
      $tenant->channel_contact = isset($DA['channel_contact']) ? $DA['channel_contact'] : "";
      $tenant->channel_contact_phone = isset($DA['channel_contact_phone']) ? $DA['channel_contact_phone'] : "";
      $tenant->rate = isset($DA['rate']) ? $DA['rate'] : "";
      $tenant->deal_rate = isset($DA['deal_rate']) ? $DA['deal_rate'] : 0;
      $tenant->tags = isset($DA['tags']) ? $DA['tags'] : "";
      $tenant->remark = isset($DA['remark']) ? $DA['remark'] : "";
      $tenant->save();
      Log::error("type" . $DA['type']);
      return $tenant;
    } catch (Exception $th) {
      Log::error($th->getMessage() . "客户保存失败");
      throw $th;
      return false;
    }
  }

  public function saveTenantLog($DA, $user)
  {
    try {
      $cusLog = $this->tenantLogModel();
      $cusLog->content    = $DA['content'];
      $cusLog->tenant_id  = $DA['tenant_id'];
      $cusLog->c_uid      = $user->id;
      $cusLog->c_username = $user->realname;
      $cusLog->company_id = $user->company_id;
      return $cusLog->save();
    } catch (Exception $th) {
      Log::error("保存客户log失败:" . $th->getMessage());
      throw $th;
      return false;
    }
  }
}
