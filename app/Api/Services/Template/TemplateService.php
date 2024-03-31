<?php

namespace App\Api\Services\Template;

use Exception;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Company\BankAccount;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Api\Services\Bill\TenantBillService;

use App\Api\Services\Contract\BillRuleService;
use PhpOffice\PhpWord\Writer\Html as WriteHtml;
use App\Api\Models\Company\Template as TemplateModel;

/**
 *合同模版生成
 */

class TemplateService
{

    /**
     * 生成合同电子版
     * @Author   leezhua
     * @DateTime 2020-06-14
     * @param    [type]     $parm     [模版文件、保存文件]
     * @param    [type]     $contract [合同信息]
     * @return   [bool]               [true or false]
     */
    public function exportContract($param, $contract)
    {
        $ruleService = new BillRuleService;
        $rentalRule = $ruleService->model()->where('contract_id', $contract['id'])->where('fee_type', 101)->first();
        $managerRule = $ruleService->model()->where('contract_id', $contract['id'])->where('fee_type', 102)->first();
        $data['租户名称'] = $contract['name'];
        $data['合同编号'] = $contract['contract_no'];
        if ($rentalRule) {
            $data['租金单价'] = $rentalRule['unit_price'] . "/" . $rentalRule['unit_price_label'];
            $data['租金月金额'] = $rentalRule['month_amt'];
            $data['租金月金额大写'] = amountToCny(numFormat($rentalRule['month_amt']));
            $data['付费方式'] = $rentalRule['pay_method'];
            $data['提前几个月付款'] = $contract['ahead_pay_month'];
        }

        $data['签约年月日'] = dateFormat("Y年m月d日", $contract['sign_date']);
        if ($managerRule) {
            $data['管理费单价'] = $managerRule['unit_price'] . "/" . $rentalRule['unit_price_label'];
            $data['管理费月金额'] = $managerRule['month_amt'];
            $data['管理费月金额大写'] = amountToCny(numFormat($managerRule['month_amt']));
        }

        $data['租赁开始年月日'] = dateFormat("Y年m月d日", $contract['start_date']);
        $data['租赁结束年月日'] = dateFormat("Y年m月d日", $contract['end_date']);
        $data['押金收款年月日'] = dateFormat("Y年m月d日", $contract['sign_date']);
        $data['租赁周期月'] = $contract['lease_term'];
        $data['租赁周期年'] = numFormat($contract['lease_term'] / 12);
        $data['租户法人'] = $contract['customer_legal_person'];
        $data['租户签约人'] = $contract['customer_sign_person'];
        $data['计租面积'] = $contract['sign_area'];
        $rentalDeposit = $ruleService->model()->where('contract_id', $contract['id'])->where('fee_type', 107)->first();

        $data['租赁押金金额'] = isset($rentalDeposit['amount']) ? $rentalDeposit['amount'] : 0.00;
        $managerDeposit = $ruleService->model()->where('contract_id', $contract['id'])->where('fee_type', 106)->first();
        $data['管理押金金额'] = isset($managerDeposit['amount']) ? $managerDeposit['amount'] : 0.00;

        $data['管理费收款账户'] = $contract['manager_account_name'];
        $data['管理费收款账户银行'] = $contract['manager_bank_name'];
        $data['管理费收款账户名称'] = $contract['manager_account_number'];
        $data['租金收款账户名称'] = $contract['rental_account_name'];
        $data['租金收款账户银行'] = $contract['rental_bank_name'];
        $data['租金收款账户'] = $contract['rental_account_number'];
        $data['租赁用途'] = $contract['rental_usage'];
        $rooms = "";
        if ($contract['contract_room']) {
            foreach ($contract['contract_room'] as $k => $v) {
                $buildInfo = $v['build_no'];
                $floorInfo = $v['floor_no'];
                $rooms .= $v['room_no'];
            }
            $data['房源楼栋'] = $buildInfo;
            $data['楼层房号'] = $floorInfo . "-" . $rooms;
        }

        if ($contract['free_list']) {
            $num  = 0;
            $freePeriod = "";
            foreach ($contract['free_list'] as $k => $v) {
                $num += $v['free_num'];
                $freePeriod .= '自' . dateFormat("Y年m月d日", $v['start_date']) . '起至' . dateFormat("Y年m月d日", $v['start_date']) . '结束；';
            }
            if ($contract['free_type'] == 1) {
                $data['免租类型'] = '月';
                $data['免租金额'] = numFormat($num * $contract['rental_month_amount']);
            } else {
                $data['免租类型'] = '天';
                $data['免租金额'] = numFormat($num * $contract['rental_price']);
            }
            $data['租赁免租期'] = $num;
            $data['免租时间段'] = $freePeriod;
        }
        try {
            $template = new TemplateProcessor($param['templateFile']);
            $template->setValues($data);

            if (!is_dir($param['savePath'])) {
                mkdir($param['savePath'], 0755, true);
            }
            $saveFile = $param['savePath'] . $param['fileName'];
            $template->saveAs($saveFile);

            return true;
        } catch (Exception $e) {
            Log::error('模版转换' . $e->getMessage());
            return false;
        }
    }

    // 获取模版信息
    /**
     * 根据公司模版ID获取模版信息
     * @Author   leezhua
     * @DateTime 2020-07-05
     * @param    [type]     $id [description]
     * @return   [type]         [description]
     */
    public function getTemplate($id)
    {
        $template = TemplateModel::find($id);
        return $template;
    }

    /**
     * 模版保存
     * @Author   leezhua
     * @DateTime 2020-07-05
     * @param    [type]     $DA   [description]
     * @param    [type]     $user [description]
     * @return   [type]           [description]
     */
    public function saveTemplate($DA, $user)
    {

        if (isset($DA['id']) && $DA['id'] > 0) {
            $template = TemplateModel::find($DA['id']);
            $template['u_uid'] = $user->id;
        } else {
            $template = new TemplateModel;
            $template->c_uid = $user->id;
            $template->company_id = $user->company_id;
            $template->c_username = $user->realname;
            $template->type = $DA['type'];
        }
        $template->name = $DA['name'];
        $template->file_name = isset($DA['file_name']) ? $DA['file_name'] : "";
        $template->file_path = $DA['file_path'];

        $template->remark = isset($DA['remark']) ? $DA['remark'] : "";
        $res = $template->save();
        return $res;
    }

    /**
     * word 账单
     *
     * @Author leezhua
     * @DateTime 2023-12-01
     * @param [type] $param
     * @param array $feeTypes
     * @param [type] $bill
     * @param [type] $bankId
     *
     * @return bool
     */
    public function createBillToWord($param, array $feeTypes, $bill, $bankId): bool
    {

        try {
            $billService  = new TenantBillService;
            $bills = $billService->billDetailModel()
                ->selectRaw('tenant_name,bill_id,fee_type,amount,charge_date,bill_date,remark,receive_amount,discount_amount')
                ->where('bill_id', $bill['id'])
                ->whereIn('fee_type', $feeTypes)
                ->where('status', 0)->get()->toArray();
            $bank = BankAccount::find($bankId);
            $bankData["收款账户名"] = $bank['account_name'];
            $bankData["收款银行"] = $bank['bank_name'];
            $bankData["收款账户"] = $bank['account_number'];
            $bankData["银行支行"] = $bank['bank_branch'];
            $totalAmt = 0.00;

            foreach ($bills as $k => &$v) {
                $v['序号'] = $k + 1;
                $v['账单日期'] = $bill['charge_date'];
                $v['费用金额'] = numFormat($v['amount'] - $v['receive_amount'] - $v['discount_amount']);
                $v['费用类型'] = $v['fee_type_label'];
                $v['账期'] = $v['bill_date'];
                $v['备注'] = $v['remark'];
                $totalAmt += numFormat($v['amount'] - $v['receive_amount'] - $v['discount_amount']);
            }

            $total['总金额'] = $totalAmt;

            $template = new TemplateProcessor($param['templateFile']);
            $template->cloneRowAndSetValues('num', $bills);
            $template->setValues($bankData);

            if (!is_dir($param['savePath'])) {
                mkdir($param['savePath'], 0755, true);
            }
            $saveFile = $param['savePath'] . $param['fileName'];
            $template->saveAs($saveFile);

            return true;
        } catch (Exception $e) {
            Log::error('模版转换' . $e->getMessage());
            return false;
        }
    }
}
