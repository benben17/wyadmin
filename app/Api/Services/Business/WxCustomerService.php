<?php



namespace App\Api\Services\Business;

use App\Api\Models\Tenant\Follow;

class WxCustomerService
{

  public function followModel()
  {
    return new Follow;
  }

  /**
   * 
   * @Author leezhua
   * @Date 2024-04-04
   * @param mixed $tenant 
   * @param mixed $map 
   * @return array 
   */
  public function getFirstData($tenant, $map)
  {
    return [
      'followCount' => $tenant->where($map)->whereHas('follow')->count(),
      'lossCount' => $tenant->where($map)->whereYear('created_at', date('Y'))->where('state', '流失客户')->count(),
      'dealCount' => $tenant->where($map)->whereHas('contract')->count(),
    ];
  }

  /**
   * @Desc: 获取客户数据
   * @Author leezhua
   * @Date 2024-04-04
   * @param [type] $tenant
   * @param [type] $map
   * @param [type] $today
   * @return void
   */
  public function getCusData($tenant, $map, $today)
  {
    $selectRaw = 'COUNT(CASE WHEN DATE(created_at) = CURRENT_DATE() THEN 1 END) as today_count,
                  COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as week_count,
                  COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as month_count';
    $dateRange = $this->getDateRange($today);
    $cusCount = $tenant->where($map)
      ->selectRaw($selectRaw, $dateRange)
      ->first();

    return [
      'today' => $cusCount->today_count,
      'week' => $cusCount->week_count,
      'month' => $cusCount->month_count,
    ];
  }

  public function getFollowData($request, $today, $uid)
  {
    $selectRaw = 'COUNT(CASE WHEN DATE(created_at) = CURRENT_DATE() THEN 1 END) as today_count,
                  COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as week_count,
                  COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as month_count';
    $dateRange = $this->getDateRange($today);
    $followCounts = $this->followModel()
      ->where(['proj_id' => $request->proj_id, 'c_uid' => $uid])
      ->selectRaw($selectRaw, $dateRange)
      ->first();

    return [
      'today' => $followCounts->today_count,
      'week' => $followCounts->week_count,
      'month' => $followCounts->month_count,
    ];
  }

  public function getUnfollowData($tenant, $map, $today)
  {
    $subQuery = $tenant->where($map);

    return [
      'three' => $this->getUnfollowCount($subQuery, getPreYmdByDay($today, 3), $today->format('Y-m-d')),
      'week' => $this->getUnfollowCount($subQuery, getPreYmdByDay($today, 7), $today->format('Y-m-d')),
      'month' => $this->getUnfollowCount($subQuery, getPreYmd($today, 1), $today->format('Y-m-d')),
    ];
  }

  public function getUnfollowCount($subQuery, $start, $end)
  {
    return $subQuery
      ->whereDoesntHave('follow', function ($q) use ($start, $end) {
        $q->whereBetween('created_at', [$start, $end]);
      })->count();
  }

  public function getDateRange($today)
  {
    return [
      $today->startOfWeek()->format('Y-m-d'),
      $today->endOfWeek()->format('Y-m-d'),
      $today->startOfMonth()->format('Y-m-d'),
      $today->endOfMonth()->format('Y-m-d'),
    ];
  }
}
