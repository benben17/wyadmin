<?php

namespace App\Api\Services\Common;

use Exception;
use Illuminate\Support\Facades\Log;

use App\Api\Models\Common\Contact as ContactModel;


/**
 *  维护。type  1 渠道 2 客户 3 租户 4 供应商 5 公共关系
 */
class ContactService
{

    public function contactModel()
    {
        $model =  new ContactModel;
        return $model;
    }

    /**
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    string     $value [description]
     * 保存联系人
     */
    public function saveContact($DA, $user)
    {
        try {
            $contact = new ContactModel;
            $contact->company_id    = $user['company_id'];
            $contact->c_uid         = $user['id'];
            $contact->parent_id     = $DA['parent_id'];
            $contact->parent_type   = $DA['parent_type'];
            $contact->contact_name  = $DA['contact_name'];
            $contact->contact_role  = isset($DA['contact_role']) ? $DA['contact_role'] : "";
            $contact->contact_phone = isset($DA['contact_phone']) ? $DA['contact_phone'] : "";
            $contact->is_default    = isset($DA['is_default']) ? $DA['is_default'] : 0;
            $res = $contact->save();
            return $res;
        } catch (Exception $e) {
            Log::error('联系人保存失败' . $e->getMessage());
            return false;
        }
    }

    /**
     * 保存多个联系人
     * @Author   leezhua
     * @DateTime 2020-07-26
     * @param    Array      $contacts [description]
     * @return   [type]               [description]
     */
    public function saveAll(array $contacts)
    {
        try {
            $contact = new ContactModel;
            return $contact->addAll($contacts);
        } catch (Exception $e) {
            Log::error('联系人保存失败' . $e->getMessage());
            return false;
        }
    }




    /**
     * 删除联系人
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    [type]     $contactId [父ID]
     * @return   [type]                [description]
     */
    public function delete(array $Ids)
    {
        try {
            return $this->contactModel()->whereIn('parent_id', $Ids)->delete();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new Exception("删除失败");
        }
    }

    /**
     * 通过id和type 获取联系人信息
     * @Author   leezhua
     * @DateTime 2020-07-26
     * @param    [type]     $parentId   [description]
     * @param    [type]     $parentType [description]
     * @return   [type]                 [description]
     */
    public function getContact($parentId, $parentType)
    {
        $map['parent_id'] = $parentId;
        $map['parent_type'] = $parentType;
        $contact = $this->contactModel()
            ->selectRaw('GROUP_CONCAT(contact_name) name ,GROUP_CONCAT(contact_phone) phone')
            ->where($map)->first();
        return $contact;
    }

    /**
     * 通过id和type 获取联系人信息
     * @Author   leezhua
     * @DateTime 2020-07-26
     * @param    [type]     $parentId   [description]
     * @param    [type]     $parentType [description]
     * @return   [type]                 [description]
     */
    public function getContacts($parentId, $parentType)
    {
        $map['parent_id'] = $parentId;
        $map['parent_type'] = $parentType;
        $contact = $this->contactModel()->where($map)->get();
        return $contact;
    }

    /**
     * 删除联系人
     * @Author   leezhua
     * @DateTime 2020-05-26
     * @param    [type]     $parentIds   [父ID]
     * @param    [type]     $parentType [父类型]
     * @return   [bool]                 [description]
     */
    public function delContact($parentIds, $parentType): bool
    {
        $map['parent_type'] = $parentType;
        return $this->contactModel()->whereIn('parent_id', $parentIds)->where($map)->delete();
    }
}
