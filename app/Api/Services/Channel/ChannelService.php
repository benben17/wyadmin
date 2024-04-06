<?php

namespace App\Api\Services\Channel;

use Exception;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Common\DictServices;
use App\Api\Services\Contract\BillRuleService;
use App\Api\Models\Channel\Channel as ChannelModel;
use App\Api\Models\Channel\ChannelPolicy as PolicyModel;
use App\Api\Models\Channel\ChannelBrokerage as BrokerageModel;

/**
 *
 */
class ChannelService
{

  public function model()
  {
    return new ChannelModel;
  }

  public function policyModel()
  {
    return new PolicyModel;
  }
  public function brokerageModel()
  {
    return new BrokerageModel;
  }

  /** 保存渠道 */
  public function savePolicy($DA, $user)
  {
    if (isset($DA['id']) &&  $DA['id'] > 0) {
      $policy = PolicyModel::find($DA['id']);
      $policy->u_uid = $user->id;
    } else {
      $policy = new PolicyModel;
      $policy->company_id = $user->company_id;
      $policy->c_username = $user->realname;
      $policy->c_uid = $user->id;
    }
    $policy->name = $DA['name'];
    $policy->policy_type = $DA['policy_type'];
    $policy->month = $DA['month'];
    $policy->is_vaild = isset($DA['is_vaild']) ? $DA['is_vaild'] : 1;
    $policy->remark = isset($DA['remark']) ? $DA['remark'] : "";
    $res = $policy->save();
    return $res;
  }
  /** 判断政策是否存在 */
  public function policyExists($DA)
  {
    if (isset($DA['id']) &&  $DA['id'] > 0) {
      return PolicyModel::whereName($DA['name'])->where('id', '!=', $DA['id'])->exists();
    } else {
      return PolicyModel::whereName($DA['name'])->exists();
    }
  }

  /** 判断政策是否在使用 */
  public function policyIsUsed($DA)
  {
    if (isset($DA['id']) &&  $DA['id'] > 0) {
      return ChannelModel::where('policy_id', $DA['id'])->exists();
    } else {
      return false;
    }
  }

  /** 渠道租金更新 */
  public function saveBrokerage($DA, $companyId)
  {

    // 目前只有一种政策，按照固定的几个月租金进行处理

    /**  更新渠道的总佣金 */
    try {
      DB::transaction(function () use ($DA, $companyId) {
        $channel = ChannelModel::find($DA['channel_id']);
        $policy = PolicyModel::where('id', $channel['policy_id'])->where('is_vaild', 1)->first();
        if (!$channel) {
          // Log::info('渠道不存在：'.$DA['channel_id']);
          throw new Exception("渠道不存在");
        }
        if (!$policy) {
          // 不做处理直接返回成功
          throw new Exception("政策不存在");
        }
        $brokerageAmount = numFormat($DA['rental_month_amount'] * $policy['month']);
        $channel->brokerage_amount = $channel['brokerage_amount'] +  $brokerageAmount;
        $channel->save();

        $brokerage = new BrokerageModel;
        $brokerage->channel_id = $companyId;
        $brokerage->channel_id = $channel['id'];
        $brokerage->policy_name = $policy['name'];
        $brokerage->tenant_name = $DA['tenant_name'];
        $brokerage->tenant_id = $DA['id'];
        $brokerage->contract_id = $DA['contract_id'];
        // 计算本次佣金的金额
        $brokerage->brokerage_amount = $brokerageAmount;
        $brokerage->remark = $policy['name'] . $policy['remark'] . '|' . $policy['month'] . '个月租金';
        $brokerage->save();
      }, 2);
      return true;
    } catch (Exception $e) {
      throw new Exception("更新佣金失败" . $e->getMessage());
      log::error("佣金保存:" . $e->getMessage());
      return false;
    }
  }


  /** 渠道租金更新 */
  public function updateBrokerage($ChannelId, $contractId, $tenant, $companyId): bool
  {

    $billRuleService = new BillRuleService;
    $contractRule = $billRuleService->model()->where('contract_id', $contractId)->where('fee_type', AppEnum::rentFeeType)->first();

    $channel = ChannelModel::find($ChannelId);

    if (!$channel) {
      // Log::info('渠道不存在：'.$DA['channel_id']);
      return true;
    }
    $policy = PolicyModel::where('id', $channel['policy_id'])->first();

    // 目前只有一种政策，按照固定的几个月租金进行处理
    $brokerageAmount = 0;
    if ($contractRule) {
      $brokerageAmount = numFormat($contractRule['month_amt'] * $tenant['brokerage']);
    }
    /**  更新渠道的总佣金 */
    try {
      $channel->brokerage_amount = $channel['brokerage_amount'] +  $brokerageAmount;
      $res = $channel->save();
      if ($res) {
        $brokerage = new BrokerageModel;
        $brokerage->company_id = $companyId;
        $brokerage->channel_id = $channel['id'];
        $brokerage->policy_name = isset($policy['name']) ? $policy['name'] : "";
        $brokerage->tenant_name = $tenant['name'];
        $brokerage->tenant_id = $tenant['id'];
        $brokerage->contract_id = $contractId;
        $brokerage->brokerage_amount = $brokerageAmount;
        $brokerage->remark = $policy['name'] . $policy['remark'] . '|' . $tenant['brokerage'] . '个月租金';
        $res = $brokerage->save();
      }
      return $res;
    } catch (Exception $e) {
      throw new Exception("更新佣金失败" . $e->getMessage());
      log::error("佣金保存:" . $e->getMessage());
      log::error($brokerage);
      return false;
    }
  }

  /**
   * list channel stat
   *
   * @Author leezhua
   * @DateTime 2024-03-29
   * @param [type] $subQuery
   * @param [type] $userId
   *
   * @return array
   */
  public function statChannel($subQuery, $userId): array
  {
    $statData = [];
    $dict = new DictServices;
    $channelTypes = $dict->getByKey(getCompanyIds($userId), 'channel_type');

    $channelStat = $subQuery
      ->selectRaw('group_concat(id) as Ids,count(id) as count,channel_type')
      ->groupBy('channel_type')->get();

    $channelCount = [
      'channel_type' => '渠道总计',
      'count' => 0,
      // 'cus_count' => 0
    ];

    foreach ($channelTypes as &$v) {
      $channelTypeName = $v['value'];
      $statData[] = [
        'channel_type' => $channelTypeName,
        'count' => 0
      ];
    }
    foreach ($statData as &$v1) {
      foreach ($channelStat as $val) {
        if ($v1['channel_type'] == $val['channel_type']) {
          $v1['count'] = $val['count'];
          $channelCount['count'] += $val['count'];
          break;
        }
      }
    }
    $statData[] = $channelCount;
    return $statData;
  }


  public function formatChannel($DA, $user, $type = 1)
  {
    if ($type == 1) {
      $BA['company_id'] = $user['company_id'];
      $BA['c_uid'] = $user['id'];
      $BA['is_valid'] = $DA['is_vaild'];
      $BA['created_at'] = nowTime();
    } else {
      $BA['u_uid'] = $user['id'];
      $BA['id'] = $DA['id'];
    }
    $BA['channel_name'] = $DA['channel_name'];
    if (isset($DA['channel_addr'])) {
      $BA['channel_addr'] = $DA['channel_addr'];
    }
    if (isset($DA['channel_type'])) {
      $BA['channel_type'] = $DA['channel_type'];
    }
    if (isset($DA['policy_id'])) {
      $BA['policy_id'] = $DA['policy_id'];
    }
    if (isset($DA['brokerage_amount'])) {
      $BA['brokerage_amount'] = $DA['brokerage_amount'];
    }

    if (isset($DA['remark'])) {
      $BA['remark'] = $DA['remark'];
    }
    $BA['proj_ids'] = isset($DA['proj_ids']) ? $DA['proj_ids'] : "";

    return $BA;
  }
}
