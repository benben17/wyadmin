<?php

namespace App\Api\Controllers\Business;

use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Channel\ChannelService;

class ChannelBrokerageController extends BaseController
{
  private $channelService;
  public function __construct()
  {
    parent::__construct();
    $this->channelService = new ChannelService;
  }
  /**
   * @OA\Post(
   *     path="/api/business/channel/brokerage/list",
   *     tags={"渠道"},
   *     summary="根据渠道id获取渠道佣金信息",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"channel_id,tenant_id"},
   *       @OA\Property(property="channel_id",type="int", description="渠道ID"),
   *       @OA\Property(property="tenant_id",type="int", description="租户ID"),
   *     ),
   *       example={"id": 11}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function list(Request $request)
  {
    $map = array();
    if ($request->channel_id) {
      $map['channel_id'] = $request->channel_id;
    }
    $subQuery = $this->channelService->brokerageModel()->where($map)
      ->where(function ($q) use ($request) {
        $request->tenant_id && $q->where('tenant_id', $request->tenant_id);
        $request->channel_id && $q->where('channel_id', $request->channel_id);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->whereHas('channel', function ($q) use ($request) {
        $request->channel_name && $q->where('channel_name', 'like', '%' . $request->channel_name . '%');
      })
      ->whereHas('tenant', function ($q) use ($request) {

        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        return $this->applyUserPermission($q, $request->depart_id, $this->user);
      });

    $data = $this->pageData($subQuery, $request);

    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/business/channel/brokerage/save",
   *     tags={"渠道"},
   *     summary="保存渠道佣金信息",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"channel_id","tenant_id"},
   *       @OA\Property(property="channel_id",type="int", description="渠道ID"),
   *       @OA\Property(property="tenant_id",type="int", description="租户ID"),
   *       @OA\Property(property="brokerage",type="int", description="佣金"),
   *       @OA\Property(property="remark",type="string", description="备注")
   *     ),
   *       example={"channel_id": 11, "tenant_id": 1, "brokerage": 10, "remark": "备注"}
   *      )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function save(Request $request)
  {
    $validator = \Validator::make($request->all(), [
      'channel_id' => 'required',
      'tenant_id' => 'required',
    ]);
    if ($validator->fails()) {
      return $this->error($validator->errors()->first());
    }
    $data = $request->all();
    $DA = array();
    $DA['brokerage']  = $data['brokerage'];
    $DA['remark']     = $data['remark'];
    $DA['channel_id'] = $data['channel_id'];
    $DA['tenant_id']  = $data['tenant_id'];

    $brokerage = $this->channelService->brokerageModel()->where('channel_id', $DA['channel_id'])
      ->where('tenant_id', $data['tenant_id'])
      ->first();
    if ($brokerage) {
      $brokerage->update($DA);
    } else {
      $brokerage = $this->channelService->brokerageModel()->create($DA);
    }
    return $this->success("保存成功");
  }
}
