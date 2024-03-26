<?php

namespace App\Api\Services\Common;

use App\Api\Models\Common\BseRemark;

/**
 *
 * parent_type 1 房间 2  3 能耗
 */
class BseRemarkService
{

    public function model()
    {
        return new BseRemark;
    }
    /**
     * 备注保存
     *
     * @Author leezhua
     * @DateTime 2024-03-25
     * @param [type] $DA
     * @param [type] $user
     *
     * @return void
     */
    public function save($DA, $user)
    {
        $remark = $this->model();
        $remark->parent_id = $DA['parent_id'];
        $remark->parent_type = $DA['parent_type'];
        $remark->company_id = $user['company_id'];
        $remark->add_date = $DA['add_date'] ?? nowTime();
        $remark->remark = $DA['remark'];
        $remark->c_username = $user->realname;
        $remark->c_uid = $user->id;
        $res = $remark->save();
        if ($res) {
            return $remark;
        } else {
            return false;
        }
    }

    /**
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    integer    $parentId    [父ID ]
     * @param    String     $contactType [联系人类型]
     * @return   [type]                  [description]
     */
    public function getRemark($parentId = 0, $parentType = "")
    {
        if ($parentId == 0 || empty($parentType)) {
            return "";
        }
        $map['parent_id'] = $parentId;
        if (!empty($contactType)) {
            $map['parent_type'] = $parentType;
        }
        return $this->model()->where($map)->get();
    }
}
