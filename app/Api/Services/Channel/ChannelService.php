<?php

namespace App\Api\Services\Channel;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Channel\Channel as ChannelModel;
use App\Api\Models\Channel\ChannelPolicy as PolicyModel;
use App\Api\Models\Channel\ChannelBrokerage as BrokerageModel;
use App\Api\Models\Tenant\Tenant;
use App\Api\Services\Common\DictServices;
use App\Api\Services\Contract\BillRuleService;
use App\Enums\AppEnum;
use Exception;

/**
 *
 */
class ChannelService
{

  public function model()
  {
    return new ChannelModel;
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
    if ($res) {
      return $policy;
    } else {
      return false;
    }
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
      $isUsed = ChannelModel::where('policy_id', $DA['id'])->exists();
    } else {
      $isUsed = false;
    }
    return $isUsed;
  }

  public function policyModel()
  {
    $policy =  new PolicyModel;
    return $policy;
  }

  /** 渠道租金更新 */
  public function saveBrokerage($DA, $companyId)
  {
    $channel = ChannelModel::find($DA['channel_id']);
    $policy = PolicyModel::where('id', $channel['policy_id'])->where('is_vaild', 1)->first();
    if (!$channel) {
      // Log::info('渠道不存在：'.$DA['channel_id']);
      return true;
    }
    if (!$policy) {
      // 不做处理直接返回成功
      return true;
    }
    // 目前只有一种政策，按照固定的几个月租金进行处理
    $brokerageAmount = numFormat($DA['rental_month_amount'] * $policy['month']);
    /**  更新渠道的总佣金 */
    try {

      $channel->brokerage_amount = $channel['brokerage_amount'] +  $brokerageAmount;
      $res = $channel->save();
      if ($res) {
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
        $res = $brokerage->save();
      }
      return $res;
    } catch (Exception $e) {
      throw new Exception("更新佣金失败" . $e->getMessage());
      log::error("佣金保存:" . $e->getMessage());
      log::error($brokerage);
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
      'cus_count' => 0
    ];

    foreach ($channelTypes as $v) {
      $channelTypeName = $v['value'];
      $found = false;

      foreach ($channelStat as $val) {
        if ($channelTypeName == $val['channel_type']) {
          $statData[] = [
            'channel_type' => $channelTypeName,
            'count' => $val['count'],
            'cus_count' => Tenant::whereIn('channel_id', str2Array($val['Ids']))->count()
          ];
          $channelCount['count'] += $val['count'];
          $channelCount['cus_count'] += end($statData)['cus_count']; // Last element added to statData
          $found = true;
          break;
        }
      }

      if (!$found) {
        // If channel type not found in $channelStat, add with count 0
        $statData[] = [
          'channel_type' => $channelTypeName,
          'count' => 0,
          'cus_count' => 0
        ];
      }
    }

    // Add total count row
    $statData[] = $channelCount;
    return $statData;
  }
}
