<?php

namespace App\Api\Services\Building;

use App\Enums\AppEnum;
use App\Api\Models\Building;
use App\Api\Models\BuildingRoom;
use App\Api\Models\BuildingFloor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\TenantRoom;
use App\Api\Services\Contract\ContractService;

class BuildingRoomService
{
  protected $buildingModel;
  protected $buildingRoomModel;
  protected $floorModel;
  protected $contractService;

  public function __construct()
  {
    $this->buildingModel = new Building;
    $this->buildingRoomModel = new BuildingRoom;
    $this->floorModel = new BuildingFloor;
    $this->contractService = new ContractService;
  }

  public function buildingRoomModel()
  {
    return $this->buildingRoomModel;
  }

  public function buildingModel()
  {
    return $this->buildingModel;
  }

  public function floorModel()
  {
    return $this->floorModel;
  }


  // 使用常量数组代替 switch 语句
  const IS_VALID_MAPPING = [
    '1' => '启用',
    '0' => '禁用',
  ];

  const STATUS_MAPPING = [
    '1' => '公开',
    '0' => '不公开',
  ];

  const ROOM_STATE_MAPPING = [
    '1' => '可招商',
    '0' => '在租',
    '2' => '自持',
  ];

  /**
   * 格式化房源list返回数据
   *
   * @param $data
   * @return array
   */
  public function formatRoomData($data): array
  {
    // 使用 eager loading 提前加载关联数据，减少数据库查询次数
    $data->load('building', 'floor');

    foreach ($data as $k => &$v) {
      // 使用 exists 子查询优化查询效率
      $v['view_num'] = TenantRoom::where('room_id', $v['id'])->exists() ? 1 : 0;

      // 使用常量数组获取状态值
      $v['IsValid']   = self::IS_VALID_MAPPING[$v['is_valid']] ?? '';
      $v['Status']    = self::STATUS_MAPPING[$v['channel_state']] ?? '';
      $v['roomState'] = self::ROOM_STATE_MAPPING[$v['room_state']] ?? '';

      // 简化数据赋值
      $v['proj_name']   = $v['building']->proj_name;
      $v['build_no']    = $v['building']->build_no;
      $v['floor_no']    = $v['floor']->floor_no;
      $v['tenant_name'] = $this->contractService->getTenantNameFromRoomId($v['id']);

      // 使用 collect() 处理图片列表
      $v['pic_list_full'] = collect($v['pic_list'] ?? [])
        ->map(function ($val) {
          return getOssUrl($val);
        })
        ->toArray();
    }

    return $data;
  }

  /**
   * 通过项目 楼的信息查询管理面积信息
   *
   * @param $query
   * @param array $buildIds
   * @return array
   */
  public function areaStat($query, array $buildIds = []): array
  {
    // 使用 when() 方法简化条件判断
    $room = $query->select(DB::Raw('
                IFNULL(SUM(room_area), 0) AS total_area,
                IFNULL(SUM(CASE WHEN room_state = 1 THEN room_area ELSE 0 END), 0) AS free_area,
                IFNULL(SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END), 0) AS total_room,
                IFNULL(SUM(CASE WHEN room_state = 1 THEN 1 ELSE 0 END), 0) AS free_room
            '))
      ->whereIn('room_state', [0, 1])
      ->first()
      ->toArray();

    // 使用三元运算符简化 rentalRate 计算
    $rentalRate = $room['free_area'] == 0 ? 100.00 : numFormat($room['free_area'] / $room['total_area'] * 100);

    $price = $this->contractService->contractAvgPrice();

    return [
      ['title' => '可租面积', 'value' => $room['total_area'] . '㎡'],
      ['title' => '可招租面积', 'value' => $room['free_area'] . '㎡'],
      ['title' => '总房间数', 'value' => $room['total_room']],
      ['title' => '可招租房间数', 'value' => $room['free_room']],
      ['title' => '当前空置率', 'value' => $rentalRate . '%'],
      ['title' => '平均单价', 'value' => $price . '元/㎡·天'],
    ];
  }

  // 格式化房间数据
  public function formatRoom($DA, $user, $type = 1): array
  {
    $BA = $type == 1 ? ['c_uid' => $user['id']] : ['id' => $DA['id'], 'u_uid' => $user['id']];

    // 使用 array_merge 减少代码量
    return array_merge($BA, [
      'room_type'         => 1,
      'company_id'        => $user['company_id'],
      'proj_id'           => $DA['proj_id'],
      'build_id'          => $DA['build_id'],
      'floor_id'          => $DA['floor_id'],
      'room_no'           => $DA['room_no'],
      'room_state'        => $DA['room_state'],
      'room_measure_area' => $DA['room_measure_area'] ?? 0,
      'room_trim_state'   => $DA['room_trim_state'] ?? "",
      'room_price'        => $DA['room_price'] ?? 0.00,
      'price_type'        => $DA['price_type'] ?? 1,
      'room_tags'         => $DA['room_tags'] ?? "",
      'channel_state'     => $DA['channel_state'] ?? 0,
      'room_area'         => $DA['room_area'] ?? 0,
      'rentable_date'     => $DA['rentable_date'] ?? "",
      'remark'            => $DA['remark'] ?? "",
      'pics'              => $DA['pics'] ?? "",
      'detail'            => $DA['detail'] ?? "",
    ]);
  }
}
