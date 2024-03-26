<?php
namespace App\Api\Services\Operation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Models\Operation\Parking as ParkingModel;
use App\Api\Services\Common\MessageService;
/**
 *  车位管理
 */
class ParkingService
{
  public function parkingModel()
  {
    $model = new ParkingModel;
    return $model;
  }

  /** 保修工单开单 */
  public function saveParking($DA,$user){
    try {
      if (isset($DA['id']) && $DA['id']>0) {
        $parking = $this->parkingModel()->find($DA['id']);
        if (!$parking) {
          $parking = $this->parkingModel();
        }
        $parking->u_uid = $user['id'];
      }else{
        $parking = $this->parkingModel();
        $parking->c_uid = $user['id'];
      }
      $parking->company_id = $user['company_id'];
      $parking->proj_id       = $DA['proj_id'];
      $parking->tenant_id     = isset($DA['tenant_id'])?$DA['tenant_id']:0;
      $parking->tenant_name   = isset($DA['tenant_name'])?$DA['tenant_name']:"";
      $parking->parking_name  = isset($DA['parking_name'])? $DA['parking_name']:"";
      $parking->lot_no        = isset($DA['lot_no'])?$DA['lot_no']:"";
      $parking->rent_start    = isset($DA['rent_start'])?$DA['rent_start']:"";
      $parking->rent_end      = isset($DA['rent_end'])?$DA['rent_end']:"";
      $parking->rent_type     = isset($DA['rent_type'])?$DA['rent_type']:"";
      $parking->month_price   = isset($DA['month_price'])?$DA['month_price']:"";
      $parking->renter_name   = isset($DA['renter_name'])?$DA['renter_name']:"";
      $parking->renter_phone  = isset($DA['renter_phone'])?$DA['renter_phone']:"";
      $parking->car_no        = isset($DA['car_no'])?$DA['car_no']:"";
      $parking->charge_date   = isset($DA['charge_date'])?$DA['charge_date']:"";
      $parking->rent_month    = isset($DA['rent_month'])?$DA['rent_month']:"";
      $parking->amount        = isset($DA['amount'])?$DA['amount']:"";
      $parking->remark        = isset($DA['remark'])?$DA['remark']:"";
      $res = $parking->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
    return $res;
  }



}
