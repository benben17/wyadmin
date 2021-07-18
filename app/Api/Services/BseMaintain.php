<?php

namespace App\Api\Services;

use Exception;
use Illuminate\Support\Facades\Log;

use App\Api\Models\Common\Maintain as MaintainModel;
use App\Api\Models\Channel\Channel as  ChannelModel;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Operation\Supplier as SupplierModel;
use App\Api\Models\Operation\PubRelations as RelationsModel;
use App\Enums\AppEnum;

/**
 *  维护。type  1 channel 2 客户 3 供应商 4 政府关系 5 租户
 */
class BseMaintain
{

    /**
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    string     $value [description]
     * 保存联系人
     */
    public function add($DA, $user)

    {
        $times = MaintainModel::where('parent_id', $DA['parent_id'])->count();
        try {
            $maintain = new MaintainModel;
            $maintain->parent_id    = $DA['parent_id'];
            $maintain->proj_id    = $DA['proj_id'];
            $maintain->parent_type   = $DA['parent_type'];
            $maintain->maintain_date  = $DA['maintain_date'];
            $maintain->maintain_user = $DA['maintain_user'];
            $maintain->maintain_type = $DA['maintain_type'];
            $maintain->maintain_record = $DA['maintain_record'];
            $maintain->maintain_feedback = isset($DA['maintain_feedback']) ? $DA['maintain_feedback'] : "";
            $maintain->c_uid = $user['id'];
            $maintain->c_username = isset($DA['c_username']) ? $DA['c_username'] : $user['realname'];
            $maintain->maintain_depart = isset($DA['maintain_depart']) ? $DA['maintain_depart'] : "";
            $maintain->company_id = $user['company_id'];
            $maintain->times = $times + 1;
            $maintain->role_id = $user['role_id'];
            $res = $maintain->save();
            return $maintain;
        } catch (Exception $e) {
            Log::error('保存维护记录失败' . $e->getMessage());
            return false;
        }
    }

    public function maintainModel()
    {
        $model =  new MaintainModel;
        return $model;
    }

    /**
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    [type]     $contactType [联系人类型]
     * @param    [type]     $data        [联系人信息]
     * @return   [type]                  [description]
     */
    public function edit($DA, $user)
    {
        try {
            $maintain = MaintainModel::find($DA['id']);
            $maintain->parent_id = $DA['parent_id'];
            $maintain->parent_type = $DA['parent_type'];
            $maintain->maintain_date  = $DA['maintain_date'];
            $maintain->maintain_user = $DA['maintain_user'];
            $maintain->maintain_type = $DA['maintain_type'];
            $maintain->maintain_record = $DA['maintain_record'];
            $maintain->maintain_feedback = isset($DA['maintain_feedback']) ? $DA['maintain_feedback'] : "";
            $maintain->u_uid = $user['id'];
            $maintain->c_username = isset($DA['c_username']) ? $DA['c_username'] : $user['realname'];;
            $maintain->maintain_depart = isset($DA['maintain_depart']) ? $DA['maintain_depart'] : "";
            $maintain->company_id = $user['company_id'];
            $res = $maintain->save();
            return $maintain;
        } catch (Exception $e) {
            Log::error('保存维护记录失败' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除维护记录 可以多条删除，但是只能删除本公司的
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    [type]     $contactId [父ID]
     * @return   [type]                [description]
     */
    public function delete($maintainIds)
    {
        $res = MaintainModel::whereIn('id', $maintainIds)->delete();
        return $res;
    }

    /**
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    integer    $parentId    [父ID ]
     * @param    string     $contactType [联系人类型]
     * @return   [type]                  [description]
     */
    public function getMaintain($parentId = 0, $parentType = 0)
    {
        if ($parentId != 0) {
            $map['parent_id'] = $parentId;
        }

        if (!empty($parent_type)) {
            $map['parent_type'] = $parentType;
        }
        $maintain = MaintainModel::where($map)->get();
        return $maintain;
    }

    /**
     * 获取名称
     *
     * @Author leezhua
     * @DateTime 2021-07-18
     * @param [type] $parentId
     * @param [type] $parentType
     *
     * @return String
     */
    public function getParentName($parentId, $parentType): String
    {
        Log::error("parant_id" . $parentId);
        if ($parentType == AppEnum::Channel) {
            $res = ChannelModel::select('channel_name as name')->find($parentId);
        } else if ($parentType == AppEnum::Supplier) {
            $res = SupplierModel::select('name as name')->find($parentId);
        } else if ($parentType == AppEnum::Relationship) {
            $res = RelationsModel::select('name as name')->find($parentId);
        } else if ($parentType == AppEnum::Tenant) {
            $res = TenantModel::select('name as name')->find($parentId);
        }
        if ($res) {
            return $res['name'];
        }
        return "";
    }

    /**
     * 根据维护id 和维护类型获取 维护信息
     * @Author   leezhua
     * @DateTime 2020-07-04
     * @param    [int]     $id         [description]
     * @param    [int]     $parentType [1，2，3，4，5]
     * @return   [type]                 [description]
     */
    public function showMaintain($id, $parentType)
    {
        $data = MaintainModel::find($id);
        Log::error("维护租户id" . $data['parent_id']);
        $data['name'] = $this->getParentName($data['parent_id'], $parentType);
        $data['maintain_type_label'] = getDictName($data['maintain_type']);
        if ($data) {
            return $data;
        } else {
            return "{}";
        }
    }
}
