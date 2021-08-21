<?php

use Illuminate\Support\Facades\Log;


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
 * 数字转大写金额
 * @Author   leezhua
 * @DateTime 2020-06-11
 * @param    [type]     $num [description]
 * @return   [type]          [description]
 */
function amountToCny(float $num)
{
    $d = array('零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
    $e = array('元', '拾', '佰', '仟', '万', '拾万', '佰万', '仟万', '亿', '拾亿', '佰亿', '仟亿', '万亿');
    $p = array('分', '角');
    $zheng = '整'; //追加"整"字
    $final = array(); //结果
    $inwan = 0; //是否有万
    $inyi = 0; //是否有亿
    $len_pointdigit = 0; //小数点后长度
    $y = 0;
    if ($c = strpos($num, '.')) {    //有小数点,$c为小数点前有几位数
        $len_pointdigit = strlen($num) - strpos($num, '.') - 1; // 判断小数点后有几位数
        if ($c > 13) { //简单的错误处理
            echo "数额太大,已经超出万亿.";
            die();
        } elseif ($len_pointdigit > 2) {   //$len_pointdigit小数点后有几位
            echo "小数点后只支持2位.";
            die();
        }
    } else {    //无小数点
        $c = strlen($num);
        $zheng = '整';
    }
    for ($i = 0; $i < $c; $i++) {  //处理整数部分
        $bit_num = substr($num, $i, 1); //逐字读取 左->右
        if ($bit_num != 0 || substr($num, $i + 1, 1) != 0) //当前是零 下一位还是零的话 就不显示
            @$low2chinses = $low2chinses . $d[$bit_num];
        if ($bit_num || $i == $c - 1)
            @$low2chinses = $low2chinses . $e[$c - $i - 1];
    }
    for ($j = $len_pointdigit; $j >= 1; $j--) {  //处理小数部分
        $point_num = substr($num, strlen($num) - $j, 1); //逐字读取 左->右
        if ($point_num != 0)
            @$low2chinses = $low2chinses . $d[$point_num] . $p[$j - 1];
        //  if(substr($num, strlen($num)-2, 1)==0 && substr($num, strlen($num)-1, 1)==0) //小数点后两位都是0
    }
    $chinses = str_split($low2chinses, 2); //字符串转换成数组
    //print_r($chinses);
    for ($x = sizeof($chinses) - 1; $x >= 0; $x--) {     //过滤无效的信息
        if ($inwan == 0 && $chinses[$x] == $e[4]) {    //过滤重复的"万"
            $final[$y++] = $chinses[$x];
            $inwan = 1;
        }
        if ($inyi == 0 && $chinses[$x] == $e[8]) {     //过滤重复的"亿"
            $final[$y++] = $chinses[$x];
            $inyi = 1;
            $inwan = 0;
        }
        if ($chinses[$x] != $e[4] && $chinses[$x] != $e[8]) //进行整理,将最后的值赋予$final数组
            $final[$y++] = $chinses[$x];
    }
    $newstring = (array_reverse($final)); //$final为倒数组，$newstring为正常可以使用的数组
    $nstring = join($newstring); //数组变成字符串
    if (substr($num, -2, 1) == 0 && substr($num, -1) <> 0) {    //判断原金额角位为0 ? 分位不为0 ?
        $nstring = substr($nstring, 0, (strlen($nstring) - 4)) . "零" . substr($nstring, -4, 4); //这样加一个零字
    }
    $fen = "分";
    $fj = substr_count($nstring, $fen); //如果没有查到分这个字
    return $nstring = ($fj == 0) ? $nstring . $zheng : $nstring; //就将"整"加到后面
}

/** 保留两位小数 并格式化数据输出 */
function numFormat($num)
{
    if (!$num || empty($num) || is_null($num) || $num === NULL) {
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
    $no = date('YmdHis', strtotime(nowTime()));

    return  'AC-' . $no . mt_rand(1000, 9999);
}

/** 通过id获取值 */
function getDictName($dictId)
{
    $res = \App\Api\Models\Company\CompanyDict::select('dict_value')->find($dictId);
    if ($res) {
        return $res['dict_value'];
    } else {
        return "";
    }
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

function getTenantNameById($tenantId)
{
    if (!$tenantId || empty($tenantId)) {
        return "公区";
    }
    $tenant = \App\Api\Models\Tenant\Tenant::select('name')->find($tenantId);
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
