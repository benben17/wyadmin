<?php

use App\Api\Models\Project;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 公用方法 获取用户公司ID
 * @param $uid 用户id
 */
function getCompanyId($uid)
{
    if ($uid) {
        $result = \App\Models\User::select('company_id')->find($uid);
        // Log::error($uid . $result);
        return $result->company_id;
    }
}
function getCompanyIds($uid)
{
    if (getCompanyId($uid)) {
        return array(0, getCompanyId($uid));
    }
    return array(0);
}

function getVariable($companyId, $key)
{

    $data =  \App\Api\Models\Company\CompanyVariable::select($key)->find($companyId);
    return $data[$key];
}

// 获取公司配置
function companyConfig($id)
{
    $result = \App\Models\Company::select('config')->find($id);
    return json_decode($result->config, true);
}

/**
 * @Author   leezhua
 * @DateTime 2020-05-30
 * @param    [type]     $contacts  [联系人信息]
 * @param    [type]     $parent_id [父ID]
 * @param    [type]     $userinfo  [用户信息 需要有parent_type 1 渠道 2 客户 3 租户]
 * @return   [type]                []
 */

function formatContact($contacts, $parentId, $userinfo, $type = 1): array
{
    if (empty($contacts) || empty($parentId) || empty($userinfo)) {
        return false;
    }
    foreach ($contacts as $k => $v) {
        $data[$k]['created_at']     = nowTime();
        $data[$k]['u_uid']          = $userinfo['id'];
        $data[$k]['company_id']     = $userinfo['company_id'];
        $data[$k]['parent_id']      = $parentId;
        $data[$k]['contact_name']   = $v['contact_name'];
        $data[$k]['parent_type']    = $userinfo['parent_type'];
        $data[$k]['contact_role']   = isset($v['contact_role']) ? $v['contact_role'] : "";
        $data[$k]['contact_phone']  = isset($v['contact_phone']) ? $v['contact_phone'] : "";
        $data[$k]['is_default']     = isset($v['is_default']) ? $v['is_default'] : 0;
        $data[$k]['updated_at']     = nowTime();
    }
    return $data;
}

/**
 * 获取合同编号
 *
 * @return void
 */
function getContractNo()
{
    $contractNo = date("ymdHis") . mt_rand(1000, 9999);
    return $contractNo;
}

/**
 * 获取合同编号
 *
 * @return void
 */
function getTradeNo()
{
    return date("ymdHis") . mt_rand(1000, 9999);
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
function numFormat($num)
{
    if (!$num || empty($num) || is_null($num) || $num === NULL || $num == 0) {
        return 0.00;
    }
    return  sprintf("%.2f", round($num, 2));
    // return number_format($num, 2, ",", "");
}

/** 通过开始日期获取几个月之后的日期 ，并减去一天（合同需要） */
function getEndNextYmd($ymd, $months)
{
    $days = intval($months);
    $ymd =  date("Y-m-d", strtotime("+" . $months . "months", strtotime($ymd)));
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

function getNextYmd($ymd, $months)
{
    $months = intval($months);
    return date('Y-m-d', strtotime("+" . $months . "months", strtotime($ymd)));
}

function nowYmd()
{
    $datetime = new \DateTime;
    return $datetime->format('Y-m-d');
}

/** 获取前几个月的日期 */
function getPreYmd($ymd, $months)
{
    return  date("Y-m-d", strtotime('-' . $months . 'months', strtotime($ymd)));
}

/** 获取多天后的日期 */
function getNextYmdByDay($ymd, $days)
{
    $days = intval($days);
    $ymd =  date("Y-m-d", strtotime("+" . $days . "days", strtotime($ymd)));
    return $ymd;
}
function getYmdPlusDays($ymd, $days)
{
    $days = intval($days);
    $ymd =  date("Y-m-d", strtotime("+" . $days . "days", strtotime($ymd)));
    return $ymd;
}
/** 获取多少天前的日期 */
function getPreYmdByDay($ymd, $days)
{
    $days = intval($days);
    $ymd =  date("Y-m-d", strtotime('-' . $days . "days", strtotime($ymd)));
    return $ymd;
}

/** 获取当前日期并格式化输出 */
function nowTime()
{
    $datetime = new \DateTime;
    return $datetime->format('Y-m-d H:i:s');
}
/** unix时间戳转 日期格式 */
function unixToYmd($unixtime)
{
    $datetime = new \DateTime();
    $datetime->setTimestamp($unixtime);
    return $datetime->format('Y-m-d H:i:s');
}

/*获取2个日期之间的间隔 天*/
function diffDays($date1, $date2)
{
    $start_date = new DateTime($date1);
    $end_end    = new DateTime($date2);
    $days = $start_date->diff($end_end)->days;
    return $days + 1;
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
    $months = intval($months);
    return date('Y-m', strtotime("+" . $months . "months", strtotime($ymd)));
}

function getYmdPlusMonths(String $ymd, $months)
{
    $months = intval($months);
    return date('Y-m-d', strtotime("+" . $months . "months", strtotime($ymd)));
}

/*date函数会给月和日补零，所以最终用unix时间戳来校验*/
function isDate($dateString)
{
    return strtotime(date('Y-m-d', strtotime($dateString))) === strtotime($dateString);
}

function dateFormat($style, $date)
{
    $date = new DateTime($date);
    return $date->format($style);
}

function getMonthNum($date1, $date2, $tags = '-')
{
    $date1 = explode($tags, $date1);
    $date2 = explode($tags, $date2);
    return abs($date1[0] - $date2[0]) * 12 + abs($date1[1] - $date2[1]);
}

// 获取图片full 地址
//
function getOssUrl($filePath)
{
    return "https://" . config('filesystems.disks.oss.bucket') . '.' . config('filesystems.disks.oss.endpoint') . "/" . $filePath;
}
function str2Array($str, $tag = ',')
{
    $arr = array();
    if (!is_array($str) && !empty($str)) {
        $arr = explode($tag, $str);
    }
    return $arr;
}

/** 获取UUID */
function uuid($prefix = '')
{
    $chars = md5(uniqid(mt_rand(), true));
    $uuid  = substr($chars, 0, 8) . '-';
    $uuid .= substr($chars, 8, 4) . '-';
    $uuid .= substr($chars, 12, 4) . '-';
    $uuid .= substr($chars, 16, 4) . '-';
    $uuid .= substr($chars, 20, 12);
    return $prefix . $uuid;
}

/**
 * 生成6位流水号
 *
 * @Author leezhua
 * @DateTime 2021-07-14
 *
 * @return void
 */
function getFlowNo()
{
    $no = date('ymdHis', strtotime(nowTime()));
    return  'AC' . $no . mt_rand(10, 99);
}

/** 通过id获取值 */
function getDictName($dictId)
{
    $dictValue = \App\Api\Models\Company\CompanyDict::where('id', $dictId)->value('dict_value');
    return $dictValue ?? "";
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
    return  \App\Api\Models\Project::find($projId);
}

function getProjNameById($projId)
{
    $proj =   \App\Api\Models\Project::find($projId);
    return $proj->proj_name ?? "";
}

function getTenantNameById($tenantId)
{
    if (!$tenantId || empty($tenantId)) {
        return "公区";
    }
    $tenant = \App\Api\Models\Tenant\Tenant::select('name')->find($tenantId);
    if (!$tenant) {
        return "";
    }
    return $tenant['name'];
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
    $project = App\Api\Models\Project::select('id')->where('name', $projName)->fisrt();
    return $project ? $project['id'] : "";
}

/**
 * 获取部门以及子部门id，返回数组
 *
 * @Author leezhua
 * @DateTime 2021-08-21
 * @param [type] $parentIds
 * @param array $arr
 *
 * @return void
 */
function getDepartIds($parentIds, $arr): array
{
    $departs = \App\Models\Depart::selectRaw("GROUP_CONCAT(id) ids")
        ->wherein('parent_id', $parentIds)->first();
    if (empty($departs['ids'])) {
        return $arr;
    }

    $depart_ids = str2Array($departs['ids']);
    foreach ($depart_ids as $k => $v) {
        array_push($arr, (int) $v);
    }
    return getDepartIds($depart_ids, $arr);
}


function compareTime(string $dateString1, string $dateString2): bool
{
    $date1 = new DateTime($dateString1);
    $date2 = new DateTime($dateString2);

    return $date1 > $date2;
}
