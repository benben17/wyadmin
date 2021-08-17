<?php

namespace App\Api\Services\Operation;

use App\Api\Models\Operation\PubRelations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Common\ContactService;
use App\Enums\AppEnum;
use Exception;

/**
 *  公共关系管理
 */
class RelationService
{
  public function model()
  {
    return  new PubRelations;
  }

  /** 保存或者更新 */
  public function save($DA, $user, $type = 1)
  {
    try {
      DB::transaction(function () use ($DA, $user, $type) {
        if (isset($DA['id']) && $DA['id'] > 0) {
          $relation = $this->model()->find($DA['id']);
          if (!$relation) {
            $relation = $this->model();
            $relation->c_username = $user['realname'];
          }
        } else {
          $relation = $this->model();
          $relation->c_uid = $user['id'];
          $relation->c_username = $user['realname'];
        }
        $relation->company_id   = $user['company_id'];
        $relation->proj_id      = $DA['proj_id'];
        $relation->name         = isset($DA['name']) ? $DA['name'] : "";
        $relation->department   = isset($DA['department']) ? $DA['department'] : "";
        $relation->job_position = isset($DA['job_position']) ? $DA['job_position'] : "";
        $relation->address      = isset($DA['address']) ? $DA['address'] : "";
        $relation->is_vaild     = isset($DA['is_vaild']) ? $DA['is_vaild'] : 1;
        $relation->remark       = isset($DA['remark']) ? $DA['remark'] : "";
        $relation->save();
        if ($DA['contacts']) {
          $contact = new ContactService;
          // 更新供应商的时候删除所有的联系人
          if ($type == 2) {
            $contact->delete($relation->id);
          }
          $user['parent_type'] = AppEnum::Relationship;
          $contacts = formatContact($DA['contacts'], $relation->id, $user);
          if ($contacts) {
            $contact->saveAll($contacts);
          }
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }
}
