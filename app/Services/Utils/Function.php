<?php

use Illuminate\Support\Facades\Log;
use App\Api\Models\Company\BankAccount;

/**
 * 公用方法 获取用户公司ID
 * @param $uid 用户id
 */
function getCompanyId($uid): int
{
	if ($uid) {
		$result = \App\Models\User::select('company_id')->find($uid);
		// Log::error($uid . $result);
		return $result->company_id ?? 0;
	}
}
/**
 * 获取用户公司ID和公共id0 集合
 * @Author leezhua
 * @Date 2024-03-30
 * @param [type] $uid
 * @return array
 */
function getCompanyIds($uid): array
{
	$companyIds = getCompanyId($uid);
	if ($companyIds) {
		return array(0, $companyIds);
	}
	return array(0);
}

/**
 * 通过费用id 获取银行账户
 *
 * @Author leezhua
 * @DateTime 2024-03-21
 * @param [type] $feeId
 * @param [type] $projId
 *
 * @return integer
 */
function getBankIdByFeeType($feeId, $projId): int
{
	$errorMsg = "未找到【" . getFeeNameById($feeId)['fee_name'] . "】费用的银行账户";
	try {
		$bank = BankAccount::whereRaw("FIND_IN_SET(?, fee_type_id)", [$feeId])->where('proj_id', $projId)->first();
		if ($bank) {
			return $bank->id;
		} else {
			throw new \Exception($errorMsg);
		}
	} catch (\Exception $e) {
		Log::error($e->getMessage());
		throw new \Exception($errorMsg);
	}
}

// 获取公司配置变量信息
function getVariable($companyId, $key)
{
	$cacheKey = "company_variable:{$companyId}:{$key}";

	// Try to get the value from the cache
	$value = Cache::get($cacheKey);

	// If the value was not in the cache, retrieve it from the database
	if ($value === null) {
		$data = \App\Api\Models\Company\CompanyVariable::select($key)->find($companyId);
		$value = $data[$key];

		// Store the value in the cache for 1 hour
		Cache::put($cacheKey, $value, 60);
	}
	return $value;
}

// 获取公司配置
function companyConfig($id)
{
	$result = \App\Models\Company::select('config')->find($id);
	return json_decode($result->config ?? '', true);
}

/**
 * @Author   leezhua
 * @DateTime 2020-05-30
 * @param    [type]     $contacts  [联系人信息]
 * @param    [type]     $parent_id [父ID]
 * @param    [type]     $userinfo  [用户信息 需要有parent_type 1 渠道 2 客户 3 租户]
 * @return   [type]                []
 */

function formatContact($contacts, $parentId, $userInfo, $type = 1): array
{
	try {
		if (empty($contacts) || empty($parentId) || empty($userInfo)) {
			return false;
		}
		foreach ($contacts as $k => $v) {
			$data[$k]['created_at']    = nowTime();
			$data[$k]['u_uid']         = $userInfo['id'];
			$data[$k]['company_id']    = $userInfo['company_id'];
			$data[$k]['parent_id']     = $parentId;
			$data[$k]['contact_name']  = $v['contact_name'];
			$data[$k]['parent_type']   = $userInfo['parent_type'];
			$data[$k]['contact_role']  = isset($v['contact_role']) ? $v['contact_role'] : "";
			$data[$k]['contact_phone'] = isset($v['contact_phone']) ? $v['contact_phone'] : "";
			$data[$k]['is_default']    = isset($v['is_default']) ? $v['is_default'] : 0;
			$data[$k]['updated_at']    = nowTime();
		}
		return $data;
	} catch (\Exception $e) {
		Log::error($e->getMessage());
		throw new \Exception("联系人信息格式化失败" . $e->getMessage());
	}
}



/**
 * 数字转大写金额
 * @Author   leezhua
 * @DateTime 2020-06-11
 * @param    [type]     $num [description]
 * @return   [type]          [description]
 */
function amountToCny(float $number)
{
	$chineseNumberChars = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
	$chineseDigits = ['', '拾', '佰', '仟', '万', '拾万', '佰万', '仟万', '亿', '拾亿', '佰亿', '仟亿', '万亿'];
	$chineseDecimalUnits = ['角', '分'];

	$numberParts = explode('.', $number);
	$integerPart = $numberParts[0];
	$decimalPart = isset($numberParts[1]) ? $numberParts[1] : '';

	$chineseChars = '';

	// 处理整数部分
	if ($integerPart == 0) {
		$chineseChars .= $chineseNumberChars[0];
	} else {
		$integerPart = strrev(strval($integerPart)); // Reverse the integer part for processing
		$length = strlen($integerPart);

		for ($i = 0; $i < $length; $i++) {
			$digit = $integerPart[$i];
			$chineseChars .= ($i === 0 && $digit == 0) ? '' : $chineseNumberChars[$digit];
			$chineseChars .= ($digit == 0) ? '' : $chineseDigits[$i];
		}
	}

	// 处理小数部分
	if (!empty($decimalPart)) {
		$decimalChars = '';
		for ($j = 0; $j < strlen($decimalPart); $j++) {
			$decimalDigit = $decimalPart[$j];
			if ($decimalDigit != 0) {
				$decimalChars .= $chineseNumberChars[$decimalDigit] . $chineseDecimalUnits[$j];
			}
		}
		$chineseChars .= $decimalChars;
	} else {
		$chineseChars .= '整';
	}

	return $chineseChars;
}

/** 保留两位小数 并格式化数据输出 */
function numFormat($num): float
{
	if (!$num || empty($num) || is_null($num) || $num === NULL || $num == 0) {
		return 0.00;
	}
	return sprintf("%.2f", round($num, 2));
	// return number_format($num, 2, ",", "");
}

/**
 * 通过开始日期获取几个月之后的日期 ，并减去一天（合同需要）
 * @Author leezhua
 * @Date 2024-04-14
 * @param mixed $ymd 
 * @param mixed $months 
 * @return string 
 */
function getEndNextYmd($ymd, $months)
{
	if (empty($ymd) || empty($months)) {
		return "";
	}
	$months = intval($months);
	$ymd = date("Y-m-d", strtotime("+" . $months . "months", strtotime($ymd)));
	return date("Y-m-d", strtotime("-1days", strtotime($ymd)));
}
/**
 * 格式化日期
 *
 * @Author leezhua
 * @DateTime 2021-07-11
 * @param String $date
 *
 * @return string
 */
function formatYmd(String $date): string
{
	return date('Y-m-d', strtotime($date));
}

/**
 * 获取下几个月的日期
 * @param mixed $ymd
 * @param mixed $months
 * @return string
 */
function getNextYmd($ymd, $months)
{
	$months = intval($months);
	return date('Y-m-d', strtotime("+" . $months . "months", strtotime($ymd)));
}

/**
 * 获取当前日期
 * @return string
 */
function nowYmd()
{
	$datetime = new \DateTime;
	return $datetime->format('Y-m-d');
}

/** 获取前几个月的日期 */
function getPreYmd($ymd, $months)
{
	return date("Y-m-d", strtotime('-' . $months . 'months', strtotime($ymd)));
}

/** 获取多天后的日期 */
function getNextYmdByDay($ymd, $days)
{
	$days = intval($days);
	$ymd = date("Y-m-d", strtotime("+" . $days . "days", strtotime($ymd)));
	return $ymd;
}
/**
 * 获取多少天后的日期
 * @param mixed $ymd
 * @param mixed $days
 * @return string
 */
function getYmdPlusDays($ymd, $days)
{
	$days = intval($days);
	$ymd = date("Y-m-d", strtotime("+" . $days . "days", strtotime($ymd)));
	return $ymd;
}
/** 获取多少天前的日期 */
function getPreYmdByDay($ymd, $days)
{
	$days = intval($days);
	$ymd = date("Y-m-d", strtotime('-' . $days . "days", strtotime($ymd)));
	return $ymd;
}

/** 获取当前日期并格式化输出 */
function nowTime()
{
	$datetime = new \DateTime;
	return $datetime->format('Y-m-d H:i:s');
}
/** unix时间戳转 日期格式 */
function unixToYmd($unixTime)
{
	$datetime = new \DateTime();
	$datetime->setTimestamp($unixTime);
	return $datetime->format('Y-m-d H:i:s');
}

/*获取2个日期之间的间隔 天*/
function diffDays($date1, $date2)
{
	if (empty($date1) || empty($date2)) {
		return 0;
	}
	$start_date = new DateTime($date1);
	$end_end = new DateTime($date2);
	$days = $start_date->diff($end_end)->days;
	return $days;
}
/**
 * 获取日期后几个月
 *
 * @param [type] $ymd 当前年月日
 * @param [type] $months 几个月之后
 * @return void
 */
function getNextMonth(String $ymd, $months)
{
	if (empty($ymd)) {
		return "";
	}
	$months = intval($months);
	return date('Y-m', strtotime("+" . $months . "months", strtotime($ymd)));
}

function getYmdPlusMonths(String $ymd, $months)
{
	if (empty($ymd)) {
		return "";
	}
	$months = intval($months);
	return date('Y-m-d', strtotime("+" . $months . "months", strtotime($ymd)));
}

/*date函数会给月和日补零，所以最终用unix时间戳来校验*/
function isDate($dateString)
{
	return strtotime(date('Y-m-d', strtotime($dateString))) === strtotime($dateString);
}

/**
 *
 * @Author leezhua
 * @Date 2024-03-29
 * @param [type] $style
 * @param [type] $date
 * @return void
 */
function dateFormat($style, $date)
{
	$date = new DateTime($date);
	return $date->format($style);
}



/**
 * 获取图片full地址
 *
 * @Author leezhua
 * @DateTime 2024-03-25
 * @param [type] $filePath
 *
 * @return string
 */
function getOssUrl($filePath): string
{
	if (empty($filePath)) {
		return "";
	}
	return "https://" . config('filesystems.disks.oss.bucket') . '.'
		. config('filesystems.disks.oss.endpoint') . "/" . $filePath;
}

/**
 * @Desc: 字段模糊查询
 * @Author leezhua
 * @Date 2024-03-30
 * @param string $column
 * @return string
 */
function columnLike(string $column): string
{
	return "%" . $column . "%";
}

/**
 * @Desc: 字符串转数组
 * @Author leezhua
 * @Date 2024-04-09
 * @param mixed $str 
 * @param string $tag 
 * @return array 
 */
function str2Array($str, $tag = ','): array
{
	if (is_array($str)) {
		return $str;
	}
	return !empty($str) ? explode($tag, $str) : [];
}

/** 获取UUID */
function uuid($prefix = '')
{
	$chars = md5(uniqid(mt_rand(), true));
	$uuid = substr($chars, 0, 8) . '-';
	$uuid .= substr($chars, 8, 4) . '-';
	$uuid .= substr($chars, 12, 4) . '-';
	$uuid .= substr($chars, 16, 4) . '-';
	$uuid .= substr($chars, 20, 12);
	return $prefix . $uuid;
}


// 核销流水号
function getChargeVerifyNo()
{
	$no = date('ymdHis', strtotime(nowTime()));
	return 'VF-' . $no . mt_rand(10, 99);
}
/** 通过id获取值 */
function getDictName($dictId)
{
	if (empty($dictId) || !$dictId) {
		return "";
	}
	$dictKey = 'dict_value' . $dictId;
	return Cache::remember($dictKey, 120, function () use ($dictId) {
		return \App\Api\Models\Company\CompanyDict::where('id', $dictId)->value('dict_value') ?? "";
	});
}

/** 获取费用名称 */

function getFeeNameById($feeId)
{
	return \App\Api\Models\Company\FeeType::find($feeId);
}
/** 获取用户信息 */
function getUserByUid($uid)
{
	return \App\Models\User::find($uid);
}
/** 获取项目信息 */
function getProjById($projId)
{
	return \App\Api\Models\Project::find($projId);
}

function getProjNameById($projId)
{
	$proj = \App\Api\Models\Project::find($projId);
	return $proj->proj_name ?? "";
}

/**
 * 获取租户名称
 * @Author leezhua
 * @DateTime 2021-08-21
 * @param [type] $tenantId
 * @return string
 */
function getTenantNameById($tenantId)
{
	if (!$tenantId || empty($tenantId)) {
		return "公区";
	}
	// 使用缓存键 'tenant_name_' . $tenantId
	$cacheKey = 'tenant_name_' . $tenantId;
	// 尝试从缓存中获取租户名称
	$tenantName = Cache::get($cacheKey);

	// 如果缓存中没有租户名称，则从数据库中获取并存入缓存
	if (!$tenantName) {
		$tenant = \App\Api\Models\Tenant\Tenant::select('name')->find($tenantId);
		$tenantName = $tenant['name'] ?? "";
		// 将租户名称存入缓存，缓存时间为 60 分钟
		Cache::put($cacheKey, $tenantName, 60 * 4);
	}

	return $tenantName;
}
/**
 * 通过UID获取部门ID
 *
 * @Author leezhua
 * @DateTime 2021-08-21
 * @param [type] $uid
 *
 * @return void
 */
function getDepartIdByUid($uid)
{
	$user = \App\Models\User::find($uid);
	return $user->depart_id;
}

/**
 * 获取部门信息
 *
 * @Author leezhua
 * @DateTime 2021-09-24
 */
function getDepartById($departId)
{
	$depart = \App\Models\Depart::find($departId);
	return $depart;
}

function getProjIdByName($projName)
{
	$project = App\Api\Models\Project::select('id')->where('name', $projName)->first();
	return $project['id'] ?? 0;
}

/**
 * 获取部门以及子部门id，返回数组
 *
 * @Author leezhua
 * @DateTime 2021-08-21
 * @param [type] $parentIds
 * @param array $idArr
 *
 * @return array
 */
function getDepartIds($parentIds, $idArr): array
{
	$departs = \App\Models\Depart::whereIn('parent_id', $parentIds)->pluck('id')->toArray();

	if (empty($departs)) {
		return $idArr;
	}
	$idArr = array_merge($idArr, $departs);
	return getDepartIds($departs, $idArr);
}

/**
 * 日期对比 第一个日期是否小于第二个日期
 *
 * @Author leezhua
 * @DateTime 2024-03-23
 * @param string $dateString1
 * @param string $dateString2
 *
 * @return boolean
 */
function compareTime(string $dateString1, string $dateString2): bool
{
	return strtotime($dateString1) > strtotime($dateString2);
}

// 根据月份获取当前月份的开始日期和结束日期返回数组
function getMonthRange($yearMonth): array
{

	$startDate = "$yearMonth-01";
	$endDate = date('Y-m-t', strtotime($startDate));
	// Return an array with both start and end dates
	return [$startDate, $endDate];
}
