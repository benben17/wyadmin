<?php
namespace App\Api\Services\Common;


use Exception;
use App\Api\Models\Common\Attachment as AttachmentModel;

/**
 * 附件服务
 */
class AttachmentService
{

  public function save($DA,$user)
  {
    $attarch = new AttachmentModel;
    $attarch->company_id  = $user['company_id'];
    $attarch->parent_id   = $DA['parent_id'];
    $attarch->parent_type = $DA['parent_type'];
    $attarch->name        = isset($DA['name']) ? $DA['name'] :"";
    $attarch->file_path   = $DA['file_path'];
    $attarch->c_username  = $user['realname'];
    $attarch->c_uid       = $user['id'];
    $res = $attarch->save();
    return $res;
  }

  /**
   * [获取附件信息列表]
   * @Author   leezhua
   * @DateTime 2020-06-25
   * @param    [int]     $parent_id   [ 父id]
   * @param    [int]     $parent_type [父类型]
   * @return   [list]                  [结果集合]
   */
  public function getAttarch($parent_id,$parent_type){

    $map['parent_id'] = $parent_id;
    $map['parent_type'] = $parent_type;
    $data = AttachmentModel::where($map)->get();
    if ($data) {
      $data = $data->toArray();
      foreach ($data as $k => &$v) {
        $v['full_path'] = getOssUrl($v['file_path']);
      }
    }
    return $data;
  }

  /**
   * 删除附件
   * @Author   leezhua
   * @DateTime 2020-06-25
   * @param    [type]     $id [description]
   * @return   [type]         [description]
   */
  public function delete($Ids){
    $res = AttachmentModel::whereIn('id',$Ids)->delete();
    return $res;
  }

  /** 通过附件ID集合获取所有的附件路径信息 */
  public function getFilePath($ids)
  {
    $ids = str2Array($ids);
    $res  = AttachmentModel::whereIn($ids)
    ->select(DB::Raw('group_concat(file_path) file_path'))->first();
    if ($res) {
      $filePath = str2Array($res['file_path']);
      foreach ($filePath as $k => &$v) {
        $v = getOssUrl($v);
      }
      return $res;
    }else{
    return false;
    }
  }





}