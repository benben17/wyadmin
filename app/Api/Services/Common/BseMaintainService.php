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
            $maintain->parent_id         = $DA['parent_id'];
            $maintain->proj_id           = $DA['proj_id'];
            $maintain->parent_type       = $DA['parent_type'];
            $maintain->maintain_date     = $DA['maintain_date'];
            $maintain->maintain_user     = $DA['maintain_user'];
            $maintain->maintain_phone    = $DA['maintain_phone'] ?? "";
            $maintain->maintain_type     = $DA['maintain_type'];
            $maintain->maintain_record   = $DA['maintain_record'];
            $maintain->maintain_feedback = $DA['maintain_feedback'] ?? "";
            $maintain->c_uid           = $user['id'];
            $maintain->c_username      = $DA['c_username'] ?? $user['realname'];
            $maintain->maintain_depart = $DA['maintain_depart'] ?? "";
            $maintain->company_id      = $user['company_id'];
            $maintain->addr            = $DA['addr'] ?? "";
            $maintain->shop_name       = $DA['shop_name'] ?? "";
            $maintain->times           = $times + 1;
            $maintain->role_id         = $user['role_id'];
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
    public function update($DA, $user)
    {
        try {
            $maintain = $this->maintainModel()->findOrFail($DA['id']);
            $maintain->parent_id         = $DA['parent_id'];
            // $maintain->parent_type       = $DA['parent_type'];
            $maintain->maintain_date     = $DA['maintain_date'];
            $maintain->maintain_user     = $DA['maintain_user'];
            $maintain->maintain_phone    = isset($DA['maintain_phone']) ? $DA['maintain_phone'] : "";
            $maintain->maintain_type     = $DA['maintain_type'];
            $maintain->maintain_record   = $DA['maintain_record'];
            $maintain->maintain_feedback = $DA['maintain_feedback'] ?? "";
            $maintain->u_uid             = $user['id'];
            $maintain->c_username        = $user['realname'];
            $maintain->maintain_depart   = $DA['maintain_depart'] ??  $maintain->maintain_depart;
            $maintain->company_id        = $user['company_id'];
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
    private $nameFieldMap = [
        AppEnum::Channel      => 'channel_name as name',
        AppEnum::Supplier     => 'name as name',
        AppEnum::Relationship => 'name as name',
        AppEnum::Tenant       => 'name  as name',
    ];
    private function getModel($parentType)
    {
        $modelMap = [
            AppEnum::Channel      => ChannelModel::class,
            AppEnum::Supplier     => SupplierModel::class,
            AppEnum::Relationship => RelationsModel::class,
            AppEnum::Tenant       => TenantModel::class,
        ];

        return $modelMap[$parentType] ?? '';
    }

    public function getParentName($parentId, $parentType): string
    {
        $model = $this->getModel((string) $parentType);

        if (!$model) {
            return "";
        }
        $nameField = $this->nameFieldMap[(string)$parentType] ?? null;
        $res = $model::selectRaw($nameField)->find($parentId);
        return $res['name'] ?? "";
    }

    public function getParentId($parentName, $parentType): int
    {
        $model = $this->getModel($parentType);
        $nameField = $this->nameFieldMap[$parentType] ?? null;

        if (!$model || !$nameField) {
            return 0;
        }

        $res = $model::select('id')->where($nameField, $parentName)->first();
        return $res ? $res['id'] : 0;
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
