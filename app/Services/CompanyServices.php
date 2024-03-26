<?php
namespace App\Services;
use App\Models\Company as CompanyModel;
use App\Api\Models\Project;
/**
 *
 */
class CompanyServices
{

  public function getCompanyById($Id)
  {
    $data  = CompanyModel::find($Id);
    return $data;
  }

  /**
   * 检查项目限制数量
   * @Author   leezhua
   * @DateTime 2021-06-01
   * @param    [type]     $Id [description]
   * @return   [type]         [description]
   */
  public function checkProjCount($Id)
  {
    $projCount = Project::where('is_vaild',1)->count();
    $companyInfo = $this->getCompanyById($Id);
    if ($projCount < $companyInfo['proj_count']) {
      return false;
    } else {
      return true;
    }
  }
  /**
   * 获取项目名称
   * @Author   leezhua
   * @DateTime 2021-06-01
   * @param    [type]     $projId [项目ID]
   * @return   [String]             [项目名称]
   */
  public function getProjName($projId){
    $proj = Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }

}