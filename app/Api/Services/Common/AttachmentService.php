<?php

namespace App\Api\Services\Common;


use Exception;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Common\Attachment;

/**
 * 附件服务
 */
class AttachmentService
{

  public function model()
  {
    return new Attachment;
  }

  public function save($DA, $user)
  {
    $attachment = $this->model();
    $attachment->company_id  = $user['company_id'];
    $attachment->parent_id   = $DA['parent_id'];
    $attachment->parent_type = $DA['parent_type'];
    $attachment->atta_type   = $DA['atta_type'] ?? "";
    $attachment->name        = isset($DA['name']) ? $DA['name'] : "";
    $attachment->file_path   = $DA['file_path'];
    $attachment->c_username  = $user['realname'];
    $attachment->c_uid       = $user['id'];
    return $attachment->save();
  }

  /**
   * [获取附件信息列表]
   * @Author   leezhua
   * @DateTime 2020-06-25
   * @param    [int]     $parent_id   [ 父id]
   * @param    [int]     $parent_type [父类型]
   * @return   [list]                  [结果集合]
   */
  public function getAttarch($parent_id, $parent_type)
  {

    $map['parent_id'] = $parent_id;
    $map['parent_type'] = $parent_type;
    DB::enableQueryLog();
    $data = $this->model()->where($map)->get();
    return $data;
  }

  /**
   * 删除附件
   * @Author   leezhua
   * @DateTime 2020-06-25
   * @param    [type]     $id [description]
   * @return   [type]         [description]
   */
  public function delete($Ids)
  {
    return $this->model()->destroy(str2Array($Ids));
  }
}
