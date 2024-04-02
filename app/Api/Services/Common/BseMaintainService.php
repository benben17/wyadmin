<?php

namespace App\Api\Services\Common;

use Exception;
use App\Enums\AppEnum;

use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Channel\Channel as  ChannelModel;
use App\Api\Models\Common\Maintain as MaintainModel;
use App\Api\Models\Operation\Supplier as SupplierModel;
use App\Api\Models\Operation\PubRelations as RelationsModel;

/**
 *  维护。type  1 channel 2 客户 3 供应商 4 政府关系 5 租户
 */
class BseMaintainService
{

    public function maintainModel(): MaintainModel
    {
        return new MaintainModel;
    }

    /**
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    string     $value [description]
     * 保存联系人
     */
    public function add($DA, $user)

    {
        $times = $this->maintainModel()->where('parent_id', $DA['parent_id'])->count();
        try {
            $maintain = $this->maintainModel();
            $maintain->parent_id    = $DA['parent_id'];
            $maintain->proj_id    = $DA['proj_id'];
            $maintain->parent_type   = $DA['parent_type'];
            $maintain->maintain_date  = $DA['maintain_date'];
            $maintain->maintain_user = $DA['maintain_user'];
            $maintain->maintain_phone = isset($DA['maintain_phone']) ? $DA['maintain_phone'] : "";
            $maintain->maintain_type = $DA['maintain_type'];
            $maintain->maintain_record = $DA['maintain_record'];
            $maintain->maintain_feedback = isset($DA['maintain_feedback']) ? $DA['maintain_feedback'] : "";
            $maintain->c_uid = $user['id'];
            $maintain->c_username = isset($DA['c_username']) ? $DA['c_username'] : $user['realname'];
            $maintain->maintain_depart = isset($DA['maintain_depart']) ? $DA['maintain_depart'] : "";
            $maintain->company_id = $user['company_id'];
            $maintain->times = $times + 1;
            $maintain->role_id = $user['role_id'];
            $maintain->save();
            return $maintain;
        } catch (Exception $e) {
            Log::error('保存维护记录失败:' . $e->getMessage());
            return false;
        }
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
            $maintain = $this->maintainModel()->find($DA['id']);
            $maintain->parent_id = $DA['parent_id'];
            $maintain->parent_type = $DA['parent_type'];
            $maintain->maintain_date  = $DA['maintain_date'];
            $maintain->maintain_user = $DA['maintain_user'];
            $maintain->maintain_phone = isset($DA['maintain_phone']) ? $DA['maintain_phone'] : "";
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
     * @return   bool                [description]
     */
    public function delete($maintainIds): bool
    {
        return $this->maintainModel()->whereIn('id', $maintainIds)->delete();
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
        return $this->maintainModel()->where($map)->get();
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
        // Log::error("parant_id" . $parentId . "parentType" . $parentType);
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
     * @param    int     $id         [description]
     * @param    int     $parentType [1，2，3，4，5]
     * @return   [type]                 [description]
     */
    public function showMaintain($id, $parentType)
    {
        $data = $this->maintainModel()->find($id);
        Log::error("维护租户id" . $data['parent_id']);
        $data['name'] = $this->getParentName($data['parent_id'], $parentType);
        $data['maintain_type_label'] = getDictName($data['maintain_type']);
        if ($data) {
            return $data;
        } else {
            return "{}";
        }
    }

    /**
     * 删除维护记录
     * @Author   leezhua
     * @DateTime 2020-07-04
     * @param    array      $parentIds  [description]
     * @param    int        $parentType [description]
     * @return   [type]                 [description]
     */
    public function delMaintain(array $parentIds, int $parentType): bool
    {
        return $this->maintainModel()->whereIn('parent_id', $parentIds)
            ->where('parent_type', $parentType)->delete();
    }
}
