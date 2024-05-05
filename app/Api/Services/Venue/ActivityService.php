<?php

namespace App\Api\Services\Venue;

use Exception;
use App\Api\Models\Venue\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Venue\ActivityType;

class ActivityService
{
  public function model()
  {
    return new Activity;
  }
  public function activityTypeModel()
  {
    return new ActivityType;
  }

  /**
   * 保存活动
   * @Author leezhua
   * @Date 2024-05-05
   * @param mixed $DA 
   * @param mixed $user 
   * @param int $type 
   * @return void 
   */
  public function saveActivity($DA, $user, $type = 1)
  {
    try {
      DB::transaction(function () use ($DA, $user, $type) {

        if ($type == 1) {
          $activity = $this->model();
          $activity->company_id = $user['company_id'];
          $activity->c_uid = $user['id'];
        } else {
          $activity = $this->model()->find($DA['id']);
          $activity->u_uid = $user['id'];
        }
        $activity->proj_id        = $DA['proj_id'];
        $activity->venue_id       = $DA['venue_id'];
        $activity->venue_name     = $DA['venue_name'];
        $activity->activity_title = $DA['activity_title'];
        $activity->activity_desc  = $DA['activity_desc'];
        $activity->activity_type  = $DA['activity_type'];
        $activity->status         = $DA['status'] ?? 1;
        $activity->start_date     = $DA['start_date'];
        $activity->end_date       = $DA['end_date'];
        $activity->is_valid       = $DA['is_valid'] ?? 1; // 1有效 2无效
        $activity->is_hot         = $DA['is_hot'] ?? 1;
        $activity->is_top         = $DA['is_top'] ?? 1;
        $res = $activity->save();

        foreach ($DA['activity_type'] as $typeDA) {
          $this->saveActivityType($typeDA, $user, $activity->id);
        }
      }, 2);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception('保存活动失败');
    }
  }


  /**
   * 保存活动类型
   * @Author leezhua
   * @Date 2024-05-05
   * @param array $DA 
   * @param array $user 
   * @param int $activityId 
   * @return void 
   */
  private function saveActivityType(array $DA, array $user, int $activityId)
  {
    try {
      $type = $this->activityTypeModel();
      $type->activity_id = $activityId;
      $type->name        = $DA['name'];
      $type->price       = $DA['price'];
      $type->is_free     = $DA['is_free'] ?? 1;    // 1免费 2收费
      $type->remark      = $DA['remark'] ?? "";
      $type->status      = $DA['status'] ?? 1;
      $type->c_uid       = $user['id'];
      $type->u_uid       = $user['id'];
      $type->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception('保存活动类型失败');
    }
  }
}
