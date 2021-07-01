<?php

namespace App\Api\Services;

use App\Api\Models\Common\Remark as remarkModel;
/**
 *
 * parent_type 1 房间 2  3 能耗
 */
class BseRemark
{

    /**
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    string     $value [description]
     * 保存联系人
     */
    public function save($DA,$user)
    {
        $remark = new remarkModel;
        $remark->parent_id = $DA['parent_id'];
        $remark->parent_type = $DA['parent_type'];
        $remark->company_id = $user['company_id'];
        $remark->remark = $DA['remark'];
        $remark->c_username = $user->realname ;
        $remark->c_uid = $user->id ;
        $res = $remark->save();
        if($res){
            return $remark;
        }else{
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
    public function getRemark($parentId = 0,$parentType=""){
        if($parentId == 0 || empty($parentType)){
            return "";
        }
        $map['parent_id'] = $parentId;
        if(!empty($contactType)){
            $map['parent_type'] = $parentType;
        }
        $contact = remarkModel::where($map)->get();
        return $contact;
    }
}



