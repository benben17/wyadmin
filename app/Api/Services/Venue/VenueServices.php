<?php

namespace App\Api\Services\Venue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Venue\Venue as VenueModel;
use App\Api\Models\Venue\VenueBook as VenueBookModel;
use App\Api\Models\Venue\VenueSettle as VenueSettleModel;
use Exception;

/**
 *
 * 场馆服务
 */
class VenueServices
{

    /** 直接返回venue模型 */
    public function venueModel()
    {
        $model = new VenueModel;
        return $model;
    }
    /** 直接返回venueBook模型 */
    public function venueBookModel()
    {
        $model = new VenueBookModel;
        return $model;
    }

    public function venueSettleModel()
    {
        $model = new VenueSettleModel;
        return $model;
    }
    /** 获取场馆信息 */
    public function getVenueById($Id)
    {
        $data = $this->venueModel()->with('project:id,proj_name')->find($Id);
        return $data;
    }

    // 获取场馆预定信息
    public function getVenueBook($venueId)
    {
        $data = $this->venueBookModel()
            ->where('venue_id', $venueId)->get();
        return $data;
    }

    /** 获取单条 book 信息 */
    public function getVenueBookById($Id)
    {
        $data = $this->venueBookModel()->find($Id);
        if ($data) {
            $venue  = $this->getVenueById($data['venue_id']);
            $data['venue_name'] = $venue['venue_name'];
        }
        return $data;
    }

    /**
     * 场馆保存
     *
     * @param [Array] $DA 场馆信息
     * @param [Array] $user 用户信息
     * @param integer $type
     * @return void
     */
    public function saveVenue($DA, $user, $type = 1)
    {
        if ($type == 1) {
            $venue = new VenueModel;
            $venue->company_id = $user['company_id'];
            $venue->c_uid = $user['id'];
        } else {
            $venue = VenueModel::find($DA['id']);
            $venue->u_uid = $user['id'];
        }
        $venue->proj_id         = $DA['proj_id'];
        $venue->proj_name       = $DA['proj_name'];
        $venue->venue_name      = $DA['venue_name'];
        $venue->venue_addr      = $DA['venue_addr'];
        $venue->venue_area      = $DA['venue_area'];
        $venue->venue_capacity  = $DA['venue_capacity'];
        $venue->venue_price     = isset($DA['venue_price']) ? $DA['venue_price'] : 0.00;
        $venue->venue_content   = isset($DA['venue_content']) ? $DA['venue_content'] : "";
        $venue->venue_facility  = isset($DA['venue_facility']) ? $DA['venue_facility'] : "";
        $venue->venue_pic       = isset($DA['venue_pic']) ? $DA['venue_pic'] : "";
        $venue->remark          = isset($DA['remark']) ? $DA['remark'] : "";
        $res = $venue->save();
        return $res;
    }

    /**
     * 场馆预定保存以及新增
     * @Author   leezhua
     * @DateTime 2020-07-06
     * @param    [type]     $DA   [description]
     * @param    [type]     $user [description]
     * @return   [type]           [description]
     */
    public function saveVenueBook($DA, $user)
    {
        if (isset($DA['id']) && $DA['id'] > 0) {
            $venueBook = $this->venueBookModel()->find($DA['id']);
            $venueBook->u_uid = $user['id'];
            // 场馆已取消或者已结算不允许更新
            if ($venueBook->state == 99 || $venueBook->state == 2) {
                return false;
            }
        } else {
            $venueBook = $this->venueBookModel();
            $venueBook->company_id = $user['company_id'];
            $venueBook->c_uid = $user['id'];
            $venueBook->c_username = $user['realname'];
        }
        $venueBook->venue_id       = $DA['venue_id'];
        $venueBook->activity_type  = $DA['activity_type'];
        $venueBook->activity_type_id  = $DA['activity_type_id'];
        $venueBook->start_date     = $DA['start_date'];
        $venueBook->end_date       = $DA['end_date'];
        $venueBook->is_deposit     = isset($DA['is_deposit']) ? $DA['is_deposit'] : 0;
        $venueBook->deposit_amount = isset($DA['deposit_amount']) ? $DA['deposit_amount'] : 0.00;
        $venueBook->person_num     = isset($DA['person_num']) ? $DA['person_num'] : "";
        $venueBook->period         = isset($DA['period']) ? $DA['period'] : 0;
        $venueBook->price          = isset($DA['price']) ? $DA['price'] : 0.00;
        $venueBook->cus_id         = isset($DA['cus_id']) ? $DA['cus_id'] : "";
        $venueBook->cus_name       = isset($DA['cus_name']) ? $DA['cus_name'] : "";
        $venueBook->contact_user   = isset($DA['contact_user']) ? $DA['contact_user'] : "";
        $venueBook->contact_phone  = isset($DA['contact_phone']) ? $DA['contact_phone'] : "";
        $venueBook->state          = isset($DA['state']) ? $DA['state'] : 1;
        $venueBook->remark         = isset($DA['remark']) ? $DA['remark'] : "";
        $venueBook->belong_uid     = isset($DA['belong_uid']) ? $DA['belong_uid'] : 0;
        $venueBook->belong_person  = isset($DA['belong_person']) ? $DA['belong_person'] : "";
        $res = $venueBook->save();
        return $res;
    }


    //** 场馆结算
    public function settleVenue($DA, $user)
    {
        try {

            DB::transaction(function () use ($DA, $user) {
                $venueBook = $this->venueBookModel()->find($DA['id']);
                if ($venueBook->state == 99 || $venueBook->state == 2) {
                    throw new Exception("结算失败,已经是结算状态，或者是取消状态");
                }
                $venueBook->state = 2; // 结算之后状态为3
                $venueBook->settle_amount   = $DA['settle_amount'];
                $venueBook->settle_date     = $DA['settle_date'];
                $venueBook->contract = isset($DA['contract']) ? $DA['contract'] : "";
                $venueBook->settle_username = isset($DA['settle_username']) ? $DA['settle_username'] : $user['realname'];
                $venueBook->pic = isset($DA['pic']) ? $DA['pic'] : "";
                $venueBook->remark = isset($DA['remark']) ? $DA['remark'] : "";
                $res = $venueBook->save();
                if (isset($DA['settle_bill']) && !empty($DA['settle_bill'])) {
                    $this->saveSettleBill($DA['settle_bill'], $venueBook['cus_id'], $venueBook['venue_id'], $venueBook['id']);
                }
            });
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new Exception("结算失败");
            return false;
        }
    }
    /**
     * 场馆结算时账单
     * @Author   leezhua
     * @DateTime 2020-07-10
     * @param    [type]     $DA      [description]
     * @param    [type]     $cusId   [description]
     * @param    [type]     $venueId [description]
     * @param    [type]     $bookId  [description]
     * @return   [type]              [description]
     */
    public function saveSettleBill($DA, $cusId, $venueId, $bookId)
    {
        Log::error(json_encode($DA));
        try {
            $settleModel = $this->venueSettleModel();
            foreach ($DA as $k => $v) {
                $data[$k]['cus_id']    = $cusId;
                $data[$k]['book_id']   = $bookId;
                $data[$k]['venue_id']  = $venueId;
                $data[$k]['fee_type']  = $v['fee_type'];
                $data[$k]['amount']    = $v['amount'];
                $data[$k]['remark']    = isset($v['remark']) ? $v['remark'] : "";
                $data[$k]['created_at'] = nowTime();
            }
            // Log::error()
            $res = $settleModel->addAll($data);
            return $res;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new Exception("场馆结算失败");
            return false;
        }
    }




    // 预定状态
    public function bookState($state)
    {
        switch ($state) {
            case '1':
                return '预定';
                break;

            case '2':
                return '已结算';
                break;
            case '99':
                return '已取消';
                break;
        }
    }
}
