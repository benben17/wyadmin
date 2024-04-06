<?php

namespace App\Api\Services\Common;


use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Common\Attachment;
use Illuminate\Support\Facades\Storage;

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
    try {
      $files = $this->model()->select('file_path')->whereIn('id', str2Array($Ids))->get();
      foreach ($files as $file) {
        Storage::delete($file->file_path);
      }
    } catch (Exception $e) {
      Log::warning('oss删除附件失败' . $e->getMessage());
    }
    return $this->model()->destroy(str2Array($Ids));
  }
}
