<?php

namespace App\Api\Services\Business;

use Exception;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Business\CusClue;

/**
 * 客户线索管理
 */
class CusClueService
{
	public function model()
	{
		return new CusClue;
	}


	/**
	 * 保存
	 *
	 * @Author leezhua
	 * @DateTime 2021-08-21
	 * @param [type] $request
	 * @param [type] $map
	 *
	 * @return void
	 */
	public function save($DA, $user)
	{
		if (isset($DA['id']) && $DA['id'] > 0) {
			$cusClue = $this->model()->find($DA['id']);
			$cusClue->u_uid = $user->id;
		} else {
			$cusClue = $this->model();
			$cusClue->company_id = $user->company_id;
			$cusClue->c_uid = $user->id;
		}
		$cusClue->proj_id = isset($DA['proj_id']) ? $DA['proj_id'] : 0;
		$cusClue->tenant_id = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
		$cusClue->name = isset($DA['name']) ? $DA['name'] : "";
		$cusClue->clue_type = $DA['clue_type'];
		$cusClue->clue_time = isset($DA['clue_time']) ? $DA['clue_time'] : nowTime();
		$cusClue->phone = isset($DA['phone']) ? $DA['phone'] : "";
		$cusClue->demand_area = isset($DA['demand_area']) ? $DA['demand_area'] : "";
		$cusClue->status = isset($DA['status']) ? $DA['status'] : 1;
		$cusClue->remark = isset($DA['remark']) ? $DA['remark'] : "";
		$res = $cusClue->save();
		return $res;
	}

	/**
	 * 更新线索状态
	 *
	 * @Author leezhua
	 * @DateTime 2021-08-21
	 * @param [type] $status
	 * @param [type] $clueId
	 * @param [type] $tenantId
	 *
	 * @return void
	 */
	public function changeStatus($clueId, $status = 0, $tenantId = 0)
	{
		try {

			if ($tenantId > 0) {
				$data['tenant_id'] = $tenantId;
				$data['change_time'] = nowYmd();
				$data['status'] = 2;
			}
			if ($status > 0) {
				$data['status'] = $status;
			}

			return $this->model()->where('id', $clueId)->update($data);
		} catch (Exception $e) {
			Log::error("线索更新失败" . $e);
			throw new Exception("线索更新失败" . $e);
			return false;
		}
	}

	/**
	 * List 列表 表头统计
	 *
	 * @Author leezhua
	 * @DateTime 2021-08-23
	 * @param [type] $request
	 * @param [type] $map
	 *
	 * @return void
	 */
	public function clueStat($request, $map)
	{

		$stat = $this->model()->where($map)
			->selectRaw('count(*) clue_total,
                  ifnull(sum(case when status = 2 then 1 end),0) customer_count,
                  ifnull(sum(case when status = 3 then 1 end),0) invalid_count')
			->where(function ($q) use ($request) {
				$request->start_time && $q->where('clue_time', '>=', $request->start_time);
				$request->end_time && $q->where('clue_time', '<=', $request->end_time);
				$request->clue_type && $q->where('clue_type', $request->clue_type);
			})
			->withCount(['cusFollow as visit_count' => function ($q) {
				$q->selectRaw("ifnull(count(distinct(tenant_id)),0) count");
				$q->where('follow_type', AppEnum::followVisit);
			}])
			->first();

		if ($stat['visit_count'] == 0) {
			$visitRate = '0.00%';
		} else {
			$visitRate = numFormat($stat['visit_count'] / $stat['clue_total'] * 100) . "%";
		}
		if ($stat['customer_count'] == 0) {
			$cusRate = '0.00%';
		} else {
			$cusRate = numFormat($stat['customer_count'] / $stat['clue_total'] * 100) . "%";
		}
		$clueStat = array(
			['label' => "线索总数", 'value' => $stat['clue_total']],
			['label' => "转客户", 'value' => $stat['customer_count']],
			['label' => "来访数", 'value' => $stat['visit_count']],
			['label' => "无效数", 'value' => $stat['invalid_count']],
			['label' => "转客比例", 'value' => $cusRate],
			['label' => "来访比例", 'value' => $visitRate],
		);
		return $clueStat;
	}
}
