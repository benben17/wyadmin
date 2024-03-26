<?php
<<<<<<< HEAD
namespace App\Services;
use App\Models\Company as CompanyModel;
use App\Api\Models\Project;
=======

namespace App\Services;

use App\Models\Company as CompanyModel;
use App\Api\Models\Project;

>>>>>>> 7a1f975c4e970e19a897d9b5e27f84a33481477b
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
<<<<<<< HEAD
    $projCount = Project::where('is_vaild',1)->count();
    $companyInfo = $this->getCompanyById($Id);
    if ($projCount < $companyInfo['proj_count']) {
      return false;
    } else {
      return true;
    }
=======
    $projCount = Project::where('is_valid', 1)->count();
    $companyInfo = $this->getCompanyById($Id);
    return $projCount < $companyInfo['proj_count'] ? false : true;
>>>>>>> 7a1f975c4e970e19a897d9b5e27f84a33481477b
  }
  /**
   * 获取项目名称
   * @Author   leezhua
   * @DateTime 2021-06-01
   * @param    [type]     $projId [项目ID]
   * @return   [String]             [项目名称]
   */
<<<<<<< HEAD
  public function getProjName($projId){
    $proj = Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }

}
=======
  public function getProjName($projId)
  {
    $proj = Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }
}
>>>>>>> 7a1f975c4e970e19a897d9b5e27f84a33481477b
