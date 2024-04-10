<?php


namespace App\Api\Services\Business;

use Exception;
use App\Api\Models\Project;
use App\Models\Area as AreaModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectService
{
  public function projectModel()
  {
    return new Project;
  }


  /**
   * 设置项目账单信息
   * @Author leezhua
   * @Date 2024-04-10
   * @param mixed $project 
   * @param mixed $user 
   * @return true 
   * @throws Exception 
   */
  public function setProject($project, $user)
  {
    try {
      DB::transaction(function () use ($project, $user) {

        $billProjData = [
          'water_price'      => $project->water_price ?? 0.00,
          'electric_price'   => $project->electric_price ?? 0.00,
          'bill_title'       => $project->bill_title ?? "",
          'bill_instruction' => $project->bill_instruction ?? "",
          'operate_entity'   => $project->operate_entity ?? "",
          'bill_project'     => $project->bill_project ?? "",
          'u_uid'          => $user->id,
        ];
        $this->projectModel()->where('id', $project->id)->update($billProjData);
      }, 2);
      return true;
    } catch (\Exception $e) {
      Log::error("项目账单设置失败." . $e->getMessage());
      throw new Exception('项目账单设置失败!');
    }
  }

  public function formatProj($data)
  {
    $DA['proj_name'] = $data['proj_name'];
    $DA['proj_type'] = isset($data['proj_type']) ? $data['proj_type'] : "";
    $DA['proj_logo'] = isset($data['proj_logo']) ? $data['proj_logo'] : "";
    if (isset($data['proj_province_id']) && $data['proj_province_id'] > 0) {
      $res = AreaModel::find($data['proj_province_id']);
      $DA['proj_province'] = $res->name;
      $DA['proj_province_id'] = $data['proj_province_id'];
    }
    if (isset($data['proj_city_id']) && $data['proj_city_id'] > 0) {
      $res = AreaModel::find($data['proj_city_id']);
      $DA['proj_city'] = $res->name;
      $DA['proj_city_id'] = $data['proj_city_id'];
    }
    if (isset($data['proj_district_id']) && $data['proj_district_id'] > 0) {
      $res = AreaModel::find($data['proj_district_id']);
      $DA['proj_district'] = $res->name;
      $DA['proj_district_id'] = $data['proj_district_id'];
    }
    $DA['water_price']      = isset($data['water_price']) ? $data['water_price'] : "0.00";
    $DA['electric_price']   = isset($data['electric_price']) ? $data['electric_price'] : "0.00";
    $DA['proj_addr']        = isset($data['proj_addr']) ? $data['proj_addr'] : "";
    $DA['proj_occupy']      = isset($data['proj_occupy']) ? $data['proj_occupy'] : 0;
    $DA['proj_buildarea']   = isset($data['proj_buildarea']) ? $data['proj_buildarea'] : 0;
    $DA['proj_usablearea']  = isset($data['proj_usablearea']) ? $data['proj_usablearea'] : 0;
    $DA['proj_far']         = isset($data['proj_far']) ? $data['proj_far'] : "";
    $DA['proj_pic']         = isset($data['proj_pic']) ? $data['proj_pic'] : "";
    $DA['support']          = isset($data['support']) ? $data['support'] : "";
    $DA['advantage']        = isset($data['advantage']) ? $data['advantage'] : "";
    $DA['bill_instruction'] = isset($data['bill_instruction']) ? $data['bill_instruction'] : "";
    $DA['operate_entity']   = isset($data['operate_entity']) ? $data['operate_entity'] : "";
    $DA['is_valid']         = isset($data['is_valid']) ? $data['is_valid'] : 1;
    return $DA;
  }
}
