<?php

namespace App\Api\Services\Venue;

use Exception;

use App\Api\Models\Venue\ActivityReg;

/**
 *
 * 场馆活动报名
 */
class ActivityRegService
{

    /** 直接返回venue模型 */
    public function model()
    {
        return new ActivityReg;
    }



    /**
     * 活动报名
     *
     * @param [Array] $DA 报名信息
     * @param [Array] $user 用户信息
     * @param integer $type
     * @return void
     */
    public function saveActivityReg($DA, $user, $type = 1)
    {
        if ($type == 1) {
            $activityReg = $this->model();
            $activityReg->company_id = $user['company_id'];
            $activityReg->c_uid = $user['id'];
        } else {
            $activityReg = $this->model()->find($DA['id']);
            $activityReg->u_uid = $user['id'];
        }
        $activity  = new ActivityService();
        $activity  = $activity->model()->find($DA['activity_id']);
        $activityReg->activity_id      = $DA['activity_id'];
        $activityReg->proj_id          = $activity->proj_id;
        $activityReg->venue_id         = $activity->venue_id;
        $activityReg->venue_name       = $activity->venue_name;
        $activityReg->activity_title   = $activity->activity_title;
        $activityReg->user_id          = $user['id'];
        $activityReg->user_name        = $user['name'];
        $activityReg->user_phone       = $user['phone'];
        $activityReg->reg_time         = $DA['reg_time'] ?? nowTime();
        $activityReg->status           = $DA['status'] ?? 1; // 报名状态 1-报名成功 0-报名失败
        $res = $activityReg->save();
        return $res;
    }
}
