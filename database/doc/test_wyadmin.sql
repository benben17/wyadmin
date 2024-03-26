/*
 Navicat Premium Data Transfer

 Source Server         : 120.53.119.141
 Source Server Type    : MySQL
 Source Server Version : 50730
 Source Host           : 120.53.119.141:3340
 Source Schema         : test_wyadmin

 Target Server Type    : MySQL
 Target Server Version : 50730
 File Encoding         : 65001

 Date: 11/06/2023 10:36:29
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for wy_admin_config
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_config`;
CREATE TABLE `wy_admin_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `description` varchar(512) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wy_admin_config_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_menu
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_menu`;
CREATE TABLE `wy_admin_menu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `order` int(11) NOT NULL DEFAULT '0',
  `title` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `uri` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `permission` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_operation_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_operation_log`;
CREATE TABLE `wy_admin_operation_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `path` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `input` text COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_operation_log_user_id_index` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2446 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_permissions
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_permissions`;
CREATE TABLE `wy_admin_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `http_method` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `http_path` text COLLATE utf8_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_permissions_name_unique` (`name`),
  UNIQUE KEY `admin_permissions_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_role_menu
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_role_menu`;
CREATE TABLE `wy_admin_role_menu` (
  `role_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_role_menu_role_id_menu_id_index` (`role_id`,`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_role_permissions
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_role_permissions`;
CREATE TABLE `wy_admin_role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_role_permissions_role_id_permission_id_index` (`role_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_role_users
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_role_users`;
CREATE TABLE `wy_admin_role_users` (
  `role_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_role_users_role_id_user_id_index` (`role_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_roles
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_roles`;
CREATE TABLE `wy_admin_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_roles_name_unique` (`name`),
  UNIQUE KEY `admin_roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_user_permissions
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_user_permissions`;
CREATE TABLE `wy_admin_user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_user_permissions_user_id_permission_id_index` (`user_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_admin_users
-- ----------------------------
DROP TABLE IF EXISTS `wy_admin_users`;
CREATE TABLE `wy_admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(190) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_users_username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_bse_aliconfig
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_aliconfig`;
CREATE TABLE `wy_bse_aliconfig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0',
  `alitype` varchar(16) NOT NULL DEFAULT 'oss' COMMENT 'oss or sms',
  `appid` varchar(128) NOT NULL DEFAULT '' COMMENT 'appid',
  `keysecret` varchar(128) NOT NULL DEFAULT '' COMMENT 'KeySecret',
  `endpoint` varchar(256) NOT NULL DEFAULT '' COMMENT 'endpoint',
  `bucket` varchar(128) NOT NULL DEFAULT '' COMMENT 'oss bucket',
  `domain` varchar(128) NOT NULL COMMENT 'domin',
  `region` varchar(64) NOT NULL,
  `templatecode` varchar(64) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alitype` (`alitype`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_bank_account
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_bank_account`;
CREATE TABLE `wy_bse_bank_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL COMMENT '公司ID',
  `account_name` varchar(64) NOT NULL COMMENT '账户名',
  `account_number` varchar(32) NOT NULL COMMENT '账户',
  `bank_name` varchar(32) NOT NULL DEFAULT '' COMMENT '开户行',
  `bank_addr` varchar(128) DEFAULT '' COMMENT '开户行地址',
  `is_vaild` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否启用 1enable 0disable',
  `remark` varchar(512) NOT NULL DEFAULT '' COMMENT '备注',
  `bank_branch` varchar(128) DEFAULT NULL COMMENT '银行支行',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_building
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_building`;
CREATE TABLE `wy_bse_building` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `proj_name` varchar(64) DEFAULT NULL,
  `build_type` varchar(16) DEFAULT NULL COMMENT '平房，楼房',
  `build_no` varchar(64) NOT NULL DEFAULT '' COMMENT '楼宇楼号',
  `build_certificate` varchar(64) DEFAULT NULL COMMENT '房产证号',
  `build_block` varchar(64) DEFAULT NULL COMMENT '幢',
  `floor_height` varchar(32) DEFAULT NULL COMMENT '层高度',
  `build_area` varchar(10) DEFAULT NULL COMMENT '管理面积',
  `build_usage` varchar(32) DEFAULT NULL COMMENT '用途',
  `build_date` date DEFAULT NULL COMMENT '建成时间',
  `remark` varchar(128) NOT NULL DEFAULT '' COMMENT '备注',
  `is_vaild` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1启用，2禁用',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_build_no` (`company_id`,`build_no`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_building_floor
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_building_floor`;
CREATE TABLE `wy_bse_building_floor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `build_id` varchar(32) NOT NULL DEFAULT '' COMMENT '楼号ID',
  `floor_no` varchar(32) NOT NULL DEFAULT '' COMMENT '楼层号',
  `remark` varchar(128) NOT NULL DEFAULT '' COMMENT '备注',
  `is_vaild` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1启用，2禁用',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`build_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_building_room
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_building_room`;
CREATE TABLE `wy_bse_building_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `build_id` int(11) NOT NULL DEFAULT '0' COMMENT '楼宇楼号',
  `floor_id` int(11) NOT NULL DEFAULT '0' COMMENT '楼宇楼号',
  `room_no` varchar(32) NOT NULL COMMENT '楼宇房间号',
  `room_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '房间类型1:房间2工位',
  `room_state` tinyint(4) DEFAULT '1' COMMENT '1:空闲，0在租',
  `room_measure_area` varchar(10) DEFAULT NULL COMMENT '面积',
  `room_trim_state` varchar(10) DEFAULT NULL COMMENT '装修状态',
  `room_price` decimal(11,2) DEFAULT '0.00' COMMENT '单价',
  `price_type` int(11) DEFAULT '1' COMMENT '1:元/天 2 元/月 ',
  `room_tags` varchar(128) DEFAULT NULL COMMENT '房间标签',
  `station_no` varchar(32) DEFAULT '' COMMENT '工位编号',
  `channel_state` tinyint(4) DEFAULT '0' COMMENT '1:渠道可查看，0渠道不可查看',
  `room_area` decimal(11,2) DEFAULT '0.00' COMMENT '面积',
  `rentable_date` date DEFAULT NULL COMMENT '可租日期',
  `remark` varchar(512) NOT NULL DEFAULT '' COMMENT '备注',
  `pics` varchar(1024) DEFAULT NULL COMMENT '房源图片',
  `detail` text COMMENT '房源详细',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '0禁用1 启用',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`build_id`),
  KEY `company_id_2` (`company_id`,`room_state`),
  KEY `company_id_3` (`company_id`,`channel_state`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_business_info
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_business_info`;
CREATE TABLE `wy_bse_business_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skyeye_id` bigint(20) DEFAULT '0',
  `name` varchar(128) DEFAULT NULL COMMENT '企业名称',
  `historyNames` varchar(256) DEFAULT '' COMMENT '历史名',
  `regStatus` varchar(32) DEFAULT '' COMMENT '注册状态',
  `cancelDate` date DEFAULT NULL COMMENT '注销时间',
  `regCapital` varchar(32) DEFAULT '' COMMENT '注册资金',
  `staffNumRange` varchar(32) DEFAULT '' COMMENT '员工数',
  `industry` varchar(32) DEFAULT '' COMMENT '行业',
  `bondNum` varchar(32) DEFAULT '' COMMENT '股票代码',
  `type` tinyint(4) DEFAULT '1' COMMENT '法人类型，1 人 2 公司',
  `bondName` varchar(32) DEFAULT '' COMMENT '股票名称',
  `revokeDate` date DEFAULT NULL COMMENT '吊销日期',
  `legalPersonName` varchar(32) DEFAULT '' COMMENT '法人名称',
  `revokeReason` varchar(32) DEFAULT '' COMMENT '吊销原因',
  `regNumber` varchar(64) DEFAULT '' COMMENT '注册号',
  `property3` varchar(256) DEFAULT '' COMMENT '英文名',
  `creditCode` varchar(128) DEFAULT '' COMMENT '统一社会信用代码',
  `usedBondName` varchar(256) DEFAULT '' COMMENT '股票曾用名',
  `fromTime` date DEFAULT NULL COMMENT '经营开始时间',
  `approvedTime` date DEFAULT NULL COMMENT ' 核准时间',
  `socialStaffNum` int(11) DEFAULT '0' COMMENT '参保人数',
  `alias` varchar(256) DEFAULT '' COMMENT '简称',
  `companyOrgType` varchar(256) DEFAULT '' COMMENT '企业类型',
  `actualCapitalCurrency` varchar(32) DEFAULT '' COMMENT '实收注册资本币种',
  `orgNumber` varchar(256) DEFAULT '' COMMENT '组织机构代码',
  `cancelReason` varchar(256) DEFAULT '' COMMENT '注销原因',
  `toTime` date DEFAULT NULL COMMENT '经营结束时间',
  `actualCapital` varchar(32) DEFAULT '' COMMENT '实收注册资金',
  `estiblishTime` date DEFAULT NULL COMMENT '成立时间',
  `regInstitute` varchar(128) DEFAULT '' COMMENT '登记机关',
  `businessScope` varchar(8190) DEFAULT NULL COMMENT '经营范围',
  `taxNumber` varchar(32) DEFAULT '' COMMENT '纳税人识别号',
  `regLocation` varchar(1024) DEFAULT '' COMMENT '注册地址',
  `regCapitalCurrency` varchar(32) DEFAULT '' COMMENT '注册币种',
  `tags` varchar(256) DEFAULT '' COMMENT '标签',
  `bondType` varchar(16) DEFAULT '' COMMENT '股票类型',
  `percentileScore` varchar(16) DEFAULT '' COMMENT '企业评分',
  `isMicroEnt` tinyint(4) DEFAULT '3' COMMENT '是否是小微企业 0不是 1是',
  `base` varchar(16) DEFAULT '' COMMENT '省份简称',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_companyName` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_channel
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_channel`;
CREATE TABLE `wy_bse_channel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '系统ID',
  `proj_ids` varchar(128) NOT NULL DEFAULT '' COMMENT '项目ID',
  `channel_name` varchar(32) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `channel_addr` varchar(256) DEFAULT NULL COMMENT '渠道地址',
  `policy_id` int(11) DEFAULT '0' COMMENT '渠道政策ID',
  `brokerage_amount` decimal(10,2) DEFAULT '0.00' COMMENT '佣金金额总金额',
  `remark` varchar(512) DEFAULT '',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '1启用0 禁用',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  `channel_type` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道类型',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`channel_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_channel_brokerage_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_channel_brokerage_log`;
CREATE TABLE `wy_bse_channel_brokerage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT '0',
  `channel_id` int(11) NOT NULL DEFAULT '0' COMMENT '渠道ID',
  `policy_name` varchar(64) NOT NULL DEFAULT '0' COMMENT '政策',
  `tenant_name` varchar(64) DEFAULT NULL COMMENT '获取佣金的客户名称',
  `tenant_id` int(11) DEFAULT '0' COMMENT '获取佣金的客户ID',
  `contract_id` int(11) NOT NULL DEFAULT '0',
  `brokerage_amount` decimal(11,2) DEFAULT '0.00' COMMENT '佣金金额',
  `remark` varchar(512) NOT NULL DEFAULT '' COMMENT '备注',
  `depart_id` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `channel_id` (`channel_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_channel_policy
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_channel_policy`;
CREATE TABLE `wy_bse_channel_policy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道政策名称',
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `policy_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '佣金方式1 固定 2 阶梯',
  `month` decimal(6,1) DEFAULT '0.0' COMMENT '月数',
  `is_vaild` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否启用 1enable 2disable',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `c_username` varchar(64) NOT NULL DEFAULT '' COMMENT '创建用户名称',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(512) NOT NULL DEFAULT '' COMMENT '备注',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_charge_bill
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_charge_bill`;
CREATE TABLE `wy_bse_charge_bill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) NOT NULL DEFAULT '0',
  `flow_no` varchar(32) DEFAULT '' COMMENT '流水号',
  `type` int(11) DEFAULT '1' COMMENT '1 收 2 支出',
  `bank_id` int(11) DEFAULT '0' COMMENT '银行id',
  `tenant_id` int(11) NOT NULL COMMENT '租户id',
  `tenant_name` varchar(128) NOT NULL COMMENT '租户名称',
  `amount` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '预付金额',
  `verify_amount` decimal(11,2) DEFAULT '0.00' COMMENT '核销金额',
  `unverify_amount` decimal(11,2) DEFAULT '0.00' COMMENT '核销金额',
  `charge_date` date DEFAULT NULL COMMENT '充值日期',
  `fee_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '预付款类型',
  `audit_status` tinyint(4) DEFAULT '1' COMMENT '审核状态 1 未审核 2 已审核 3 拒绝',
  `audit_uid` int(11) DEFAULT '0' COMMENT '审核人ID',
  `audit_user` varchar(32) DEFAULT '' COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `remark` varchar(1024) DEFAULT NULL COMMENT '备注',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0 未核销 1 已核销',
  `invoice_no` varchar(64) DEFAULT '' COMMENT '发票no',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_charge_bill_record
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_charge_bill_record`;
CREATE TABLE `wy_bse_charge_bill_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) DEFAULT NULL COMMENT '项目id',
  `flow_no` varchar(32) DEFAULT '' COMMENT '流水号',
  `verify_date` date DEFAULT NULL COMMENT '核销日期',
  `charge_id` int(11) NOT NULL DEFAULT '0' COMMENT '预付款ID',
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1收入、2支出',
  `fee_type` int(11) DEFAULT '0' COMMENT '费用类型',
  `amount` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '支付金额',
  `bill_detail_id` int(11) NOT NULL DEFAULT '0' COMMENT '账单详细记录ID',
  `remark` varchar(1024) DEFAULT NULL COMMENT '备注',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `c_username` varchar(64) DEFAULT '0' COMMENT '操作人',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `charge_id` (`charge_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_common_attachment
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_common_attachment`;
CREATE TABLE `wy_bse_common_attachment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT '0',
  `parent_id` int(11) NOT NULL COMMENT '父ID',
  `parent_type` tinyint(4) NOT NULL COMMENT 'parent_type  1 channel 2 租户 3 合同 3 供应商 4 政府关系',
  `name` varchar(64) DEFAULT '' COMMENT '附件名称',
  `file_path` varchar(512) NOT NULL COMMENT '地址',
  `c_username` varchar(16) DEFAULT '',
  `c_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`,`parent_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_common_remark
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_common_remark`;
CREATE TABLE `wy_bse_common_remark` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `parent_type` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `remark` varchar(512) NOT NULL COMMENT '备注内容',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `c_username` varchar(64) NOT NULL DEFAULT '' COMMENT '创建人',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_company_variable
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_company_variable`;
CREATE TABLE `wy_bse_company_variable` (
  `company_id` int(11) NOT NULL,
  `tenant_prefix` varchar(16) DEFAULT '' COMMENT '租户编号前缀',
  `tenant_no` bigint(20) unsigned NOT NULL DEFAULT '0',
  `cus_prefix` varchar(4) DEFAULT 'CUS' COMMENT '客户编号前缀',
  `contract_due_remind` smallint(6) DEFAULT '30' COMMENT '合同到期提醒时间，单位天',
  `msg_revoke_time` smallint(6) DEFAULT '30' COMMENT '发送消息允许撤回时间 单位分钟',
  `contract_prefix` varchar(8) DEFAULT 'HT' COMMENT '合同编号前缀',
  `year_days` int(11) DEFAULT '365' COMMENT '账单计算一年实际天数',
  `c_uid` int(11) DEFAULT '0',
  `u_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contacts
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contacts`;
CREATE TABLE `wy_bse_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '系统ID',
  `parent_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '类型 1 渠道 2 客户 3 租户 4供应商 5 公共关系',
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父ID',
  `contact_name` varchar(32) NOT NULL COMMENT '渠道联系人',
  `contact_phone` varchar(20) DEFAULT '' COMMENT '渠道联系电话',
  `contact_role` varchar(32) DEFAULT '' COMMENT '联系人角色',
  `is_default` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否默认联系人',
  `contact_openid` varchar(32) DEFAULT NULL COMMENT '联系人微信openid',
  `remark` varchar(256) DEFAULT '' COMMENT '备注',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`parent_id`),
  KEY `company_id_2` (`company_id`,`parent_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2092 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contract
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract`;
CREATE TABLE `wy_bse_contract` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '租赁期限（月）',
  `contract_no` varchar(64) NOT NULL DEFAULT '' COMMENT '合同编号',
  `contract_type` int(1) NOT NULL DEFAULT '0' COMMENT '合同类型1 新签 2 续签',
  `rental_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '签约单价',
  `rental_price_type` tinyint(4) DEFAULT '1' COMMENT '1:天 2 月',
  `rental_month_amount` decimal(10,2) DEFAULT NULL COMMENT '月租金金额',
  `management_price` decimal(10,0) DEFAULT '0' COMMENT '管理费单价',
  `management_month_amount` decimal(10,2) DEFAULT NULL COMMENT '月管理费金额',
  `violate_rate` smallint(4) NOT NULL DEFAULT '0' COMMENT '违约金比例',
  `sign_date` date NOT NULL COMMENT '签约日期',
  `pay_method` smallint(6) NOT NULL DEFAULT '0' COMMENT '支付周期',
  `contract_state` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0草稿1待审核2正式',
  `start_date` date NOT NULL COMMENT '起始日期',
  `lease_term` smallint(6) NOT NULL DEFAULT '0' COMMENT '租赁期限（月）',
  `end_date` date NOT NULL COMMENT '结束日期',
  `belong_person` varchar(64) NOT NULL DEFAULT '' COMMENT '跟进人',
  `belong_uid` int(11) NOT NULL DEFAULT '0' COMMENT '跟进人ID',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `tenant_name` varchar(64) NOT NULL COMMENT '租户名称',
  `industry` varchar(64) DEFAULT NULL COMMENT '行业',
  `tenant_legal_person` varchar(64) DEFAULT '0' COMMENT '法人',
  `tenant_sign_person` varchar(64) DEFAULT '0' COMMENT '客户签约人',
  `sign_area` varchar(64) NOT NULL DEFAULT '' COMMENT '签约面积',
  `rental_deposit_amount` decimal(10,2) DEFAULT '0.00' COMMENT '租赁押金金额',
  `rental_deposit_month` smallint(6) DEFAULT NULL COMMENT '几个月租金作为押金',
  `increase_year` smallint(6) DEFAULT '0' COMMENT '第几年开始递增',
  `increase_date` date DEFAULT NULL COMMENT '递增时间',
  `increase_rate` smallint(6) DEFAULT '0' COMMENT '递增率，单价递增',
  `bill_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1正常账单免租期算账单里面，2 遇见免租期帐期后延',
  `ahead_pay_month` smallint(6) DEFAULT '0' COMMENT '提前几个月收款',
  `manager_bank_name` varchar(64) DEFAULT NULL COMMENT '管理费银行',
  `manager_bank_id` smallint(6) DEFAULT '0' COMMENT '租金账户ID',
  `manager_deposit_month` tinyint(4) DEFAULT '0' COMMENT '几个月管理费作为管理押金',
  `manager_deposit_amount` decimal(10,2) DEFAULT '0.00' COMMENT '管理费押金金额',
  `manager_account_name` varchar(64) DEFAULT NULL COMMENT '管理费账户',
  `manager_account_number` varchar(64) DEFAULT NULL COMMENT '管理费账户',
  `rental_account_name` varchar(32) DEFAULT '' COMMENT '租金账户名',
  `rental_account_number` varchar(64) DEFAULT NULL COMMENT '租金账户',
  `rental_bank_name` varchar(64) DEFAULT NULL COMMENT '租金账户名',
  `rental_bank_id` smallint(6) DEFAULT '0' COMMENT '租金账户ID',
  `increase_show` tinyint(4) DEFAULT '0' COMMENT '递增',
  `manager_show` tinyint(4) DEFAULT '0' COMMENT '管理',
  `rental_usage` varchar(16) DEFAULT '' COMMENT '租赁用途',
  `leaseback_date` date DEFAULT NULL COMMENT '退租时间',
  `free_type` tinyint(4) DEFAULT '1' COMMENT '免租类型 0 没有免租 1 按月 2按天',
  `room_type` tinyint(4) DEFAULT '1' COMMENT '房源编号1 房源2 工位 ',
  `share_type` int(11) DEFAULT '0' COMMENT '分摊方式 1 比例 2 固定金额 3 面积',
  `c_uid` int(11) DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id_2` (`company_id`,`tenant_id`),
  KEY `idx_signdate` (`company_id`,`sign_date`),
  KEY `idx_enddate` (`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contract_bill
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract_bill`;
CREATE TABLE `wy_bse_contract_bill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目id',
  `contract_id` int(11) NOT NULL COMMENT '合同id',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户id',
  `type` varchar(32) NOT NULL COMMENT '费用类型 1费用2 押金',
  `fee_type` int(11) DEFAULT NULL COMMENT '费用名称',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '费用金额',
  `charge_date` date NOT NULL COMMENT '收费日期',
  `start_date` date NOT NULL COMMENT '费用开始日期',
  `end_date` date NOT NULL COMMENT '费用结束日期',
  `price` varchar(16) DEFAULT '0.00' COMMENT '单价',
  `unit_price_label` varchar(128) DEFAULT NULL COMMENT '单价label',
  `bill_date` varchar(128) DEFAULT '' COMMENT '账单起始日期',
  `is_sync` tinyint(4) DEFAULT '0' COMMENT '是否已收 1 同步到账单 0 未同步到账单',
  `charge_amount` decimal(11,2) DEFAULT '0.00' COMMENT '已收',
  `remark` varchar(512) DEFAULT '' COMMENT '费用备注',
  `c_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=440 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contract_bill_rule
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract_bill_rule`;
CREATE TABLE `wy_bse_contract_bill_rule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `contract_id` bigint(20) unsigned NOT NULL,
  `fee_type` int(11) NOT NULL DEFAULT '1' COMMENT '费用类型',
  `unit_price` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `unit_price_label` varchar(32) DEFAULT NULL COMMENT '单价标示',
  `price_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1日单价、2月单价,3，年单价',
  `start_date` date DEFAULT NULL COMMENT '开始时间',
  `end_date` date DEFAULT NULL COMMENT '结束时间',
  `area_num` decimal(8,2) DEFAULT '0.00' COMMENT '面积',
  `pay_method` tinyint(4) NOT NULL COMMENT '支付周期，月',
  `month_amt` decimal(12,2) DEFAULT NULL COMMENT '月租金',
  `amount` decimal(12,2) DEFAULT NULL COMMENT '金额',
  `bill_day` varchar(12) NOT NULL DEFAULT '' COMMENT '账单日',
  `bill_type` int(11) NOT NULL DEFAULT '1' COMMENT '1正常账单，2 自然月',
  `type` int(11) DEFAULT NULL COMMENT '对应fee_type 表type',
  `charge_date` date DEFAULT NULL COMMENT '收款日期',
  `violate_rate` decimal(10,2) DEFAULT '0.00' COMMENT '滞纳金比例,千分之',
  `ahead_pay_month` int(11) NOT NULL DEFAULT '0' COMMENT '提前几个月收取',
  `remark` varchar(512) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `c_uid` int(11) DEFAULT NULL,
  `u_uid` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenid` (`tenant_id`),
  KEY `idx_conid` (`contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contract_free_period
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract_free_period`;
CREATE TABLE `wy_bse_contract_free_period` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `contract_id` int(11) NOT NULL DEFAULT '0' COMMENT '合同ID',
  `start_date` date DEFAULT NULL COMMENT '免租开始日期',
  `end_date` date DEFAULT NULL COMMENT '免租结束日期',
  `free_num` varchar(6) NOT NULL COMMENT '免租天数或者月数',
  `is_vaild` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否已处理',
  `remark` varchar(256) DEFAULT '' COMMENT 'beizhu',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_cusId` (`tenant_id`),
  KEY `idx_contractId` (`contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='免租期限';

-- ----------------------------
-- Table structure for wy_bse_contract_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract_log`;
CREATE TABLE `wy_bse_contract_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1:作废 2 审核',
  `contract_id` int(11) NOT NULL DEFAULT '0' COMMENT '合同ID',
  `title` varchar(32) DEFAULT '' COMMENT '日志标题',
  `contract_state` tinyint(4) DEFAULT '0' COMMENT '合同状态-1 ：作废 0 草稿1 待审核 2 正式合同',
  `audit_state` tinyint(4) DEFAULT '0' COMMENT '审核状态1 通过0 未通过',
  `remark` varchar(2048) NOT NULL DEFAULT '' COMMENT '备注',
  `c_username` varchar(16) NOT NULL DEFAULT '' COMMENT '操作用户',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '操作人ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contract_parm
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract_parm`;
CREATE TABLE `wy_bse_contract_parm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parm_type` varchar(32) NOT NULL DEFAULT '' COMMENT '变量类型',
  `parm_name` varchar(32) NOT NULL DEFAULT '' COMMENT '变量名称',
  `remark` varchar(256) DEFAULT '' COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `parm_name` (`parm_name`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contract_room
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract_room`;
CREATE TABLE `wy_bse_contract_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL DEFAULT '0' COMMENT '合同id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目',
  `proj_name` varchar(64) DEFAULT '' COMMENT '项目',
  `build_id` int(11) NOT NULL DEFAULT '0' COMMENT '楼号ID',
  `build_no` varchar(32) DEFAULT '' COMMENT '楼号',
  `floor_id` int(11) NOT NULL DEFAULT '0' COMMENT '层ID',
  `floor_no` varchar(64) DEFAULT '' COMMENT '层号',
  `room_id` int(11) NOT NULL DEFAULT '0' COMMENT '房间ID',
  `room_no` varchar(64) DEFAULT '' COMMENT '房间',
  `room_area` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '房间面积',
  `room_type` tinyint(4) DEFAULT '1' COMMENT '房源编号1 房源2 工位',
  `station_no` varchar(64) DEFAULT NULL COMMENT '工位编号',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`),
  KEY `idx_projId_build_id` (`proj_id`,`build_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_contract_template
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_contract_template`;
CREATE TABLE `wy_bse_contract_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `name` varchar(64) NOT NULL COMMENT '合同模版名称',
  `file_name` varchar(128) DEFAULT '' COMMENT '合同模版文件名',
  `file_path` varchar(512) NOT NULL COMMENT '合同模版路径',
  `remark` varchar(256) DEFAULT '' COMMENT '备注',
  `c_username` varchar(32) NOT NULL DEFAULT '' COMMENT '添加人',
  `c_uid` int(11) DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_customer_clue
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_customer_clue`;
CREATE TABLE `wy_bse_customer_clue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '系统ID',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `tenant_id` bigint(20) DEFAULT '0' COMMENT '客户id',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT '客户姓名',
  `phone` varchar(32) NOT NULL DEFAULT '' COMMENT '客户电话',
  `clue_time` datetime DEFAULT NULL COMMENT '来电时间',
  `demand_area` varchar(32) NOT NULL DEFAULT '' COMMENT '需求面积',
  `clue_type` int(11) NOT NULL COMMENT '来源类型',
  `remark` varchar(512) DEFAULT '',
  `invalid_reason` varchar(256) DEFAULT '' COMMENT '放弃原因',
  `change_time` date DEFAULT NULL COMMENT '放弃时间',
  `invalid_time` date DEFAULT NULL COMMENT '放弃时间',
  `status` int(11) DEFAULT '1' COMMENT '状态 1 默认 2 转客户 3 弃用',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_depart
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_depart`;
CREATE TABLE `wy_bse_depart` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL COMMENT '公司id',
  `parent_id` bigint(20) unsigned NOT NULL COMMENT '父级主键id',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '部门名称',
  `seq` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '排序号',
  `remark` varchar(512) DEFAULT '',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '1启用0 禁用',
  `c_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '修改人',
  `u_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COMMENT='部门架构表';

-- ----------------------------
-- Table structure for wy_bse_dict
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_dict`;
CREATE TABLE `wy_bse_dict` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `dict_key` varchar(16) NOT NULL COMMENT '字典key',
  `dict_value` varchar(32) NOT NULL COMMENT '字典值',
  `label` smallint(6) DEFAULT NULL COMMENT '标示',
  `is_vaild` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1启用2禁用',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `orderby` smallint(6) NOT NULL DEFAULT '0' COMMENT '排序',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`dict_key`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_dict_type
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_dict_type`;
CREATE TABLE `wy_bse_dict_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_edit` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1可编辑0 不可编辑',
  `dict_type` varchar(64) NOT NULL DEFAULT '' COMMENT '字典类型',
  `dict_value` varchar(64) NOT NULL DEFAULT '' COMMENT '类型名称',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_type_value` (`dict_type`,`dict_value`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_energy_price
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_energy_price`;
CREATE TABLE `wy_bse_energy_price` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) NOT NULL DEFAULT '0',
  `water_price` decimal(12,0) NOT NULL COMMENT '水费单价',
  `electric_price` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '电费单价',
  `c_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_equipment
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_equipment`;
CREATE TABLE `wy_bse_equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `system_name` varchar(255) DEFAULT NULL COMMENT '系统',
  `position` varchar(255) DEFAULT NULL COMMENT '位置',
  `major` varchar(255) DEFAULT NULL COMMENT '主责专业',
  `device_name` varchar(255) DEFAULT NULL COMMENT '名称',
  `model` varchar(256) DEFAULT NULL COMMENT '设备型号参数',
  `quantity` int(11) DEFAULT NULL COMMENT '设备数量',
  `unit` varchar(32) DEFAULT NULL COMMENT '单位',
  `maintain_period` smallint(6) DEFAULT '0' COMMENT '1每周 2 2周3 每月 4 每季度 5 半年 6 每年',
  `maintain_content` varchar(4096) DEFAULT NULL COMMENT '维保内容',
  `maintain_times` int(11) DEFAULT NULL COMMENT '每年维保次数',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `year` varchar(16) NOT NULL COMMENT '年份计划',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`proj_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_equipment_maintain
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_equipment_maintain`;
CREATE TABLE `wy_bse_equipment_maintain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `device_name` varchar(500) DEFAULT NULL COMMENT '设备名',
  `model` varchar(50) DEFAULT NULL COMMENT '类型',
  `major` varchar(50) DEFAULT NULL COMMENT '专业',
  `position` varchar(128) DEFAULT NULL COMMENT '位置',
  `maintain_period` varchar(128) DEFAULT NULL COMMENT '维护周期',
  `equipment_id` int(11) NOT NULL DEFAULT '0' COMMENT '设备ID',
  `equipment_type` varchar(128) DEFAULT NULL COMMENT '设备类型',
  `maintain_content` varchar(512) DEFAULT NULL COMMENT '维护内容',
  `maintain_date` date DEFAULT NULL COMMENT '维护时间',
  `maintain_person` varchar(30) DEFAULT NULL COMMENT '维护人',
  `quantity` int(11) DEFAULT NULL COMMENT '维护数量',
  `maintain_type` varchar(128) DEFAULT NULL COMMENT '维护类型',
  `pic` varchar(1024) DEFAULT NULL COMMENT '维护图片',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  `c_username` varchar(128) NOT NULL COMMENT '维护人',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_fee_type
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_fee_type`;
CREATE TABLE `wy_bse_fee_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `fee_name` varchar(32) NOT NULL DEFAULT '' COMMENT '费用名称',
  `type` tinyint(4) DEFAULT '1' COMMENT '费用类型 1 费用 2 押金',
  `bank_id` int(11) DEFAULT '0' COMMENT '收款银行id',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '是否启用1启用0 禁用',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_inspection
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_inspection`;
CREATE TABLE `wy_bse_inspection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `name` varchar(500) DEFAULT NULL COMMENT '名称',
  `type` int(11) NOT NULL DEFAULT '1' COMMENT '巡检类型',
  `major` tinyint(4) DEFAULT '1' COMMENT '专业',
  `qr_code` varchar(500) DEFAULT NULL COMMENT '二维码',
  `rfid_id` varchar(128) DEFAULT NULL COMMENT 'rfid标签id',
  `device_name` varchar(500) DEFAULT NULL COMMENT '设备名称',
  `position` varchar(500) DEFAULT NULL COMMENT '位置',
  `check_cycle` int(11) DEFAULT NULL COMMENT '巡检周期',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `status` smallint(6) DEFAULT '1' COMMENT '1 正常 0 异常',
  `times` smallint(6) DEFAULT '0' COMMENT '每年总次数',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_inspection_record
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_inspection_record`;
CREATE TABLE `wy_bse_inspection_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `inspection_id` int(11) NOT NULL DEFAULT '0' COMMENT '维护ID',
  `is_unusual` int(11) NOT NULL DEFAULT '0' COMMENT '1正常0 异常',
  `record` varchar(512) DEFAULT '' COMMENT '巡检内容',
  `pic` varchar(500) DEFAULT NULL COMMENT '维护图片',
  `c_username` varchar(500) DEFAULT NULL COMMENT '创建人',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_invoice_record
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_invoice_record`;
CREATE TABLE `wy_bse_invoice_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `tenant_id` int(11) DEFAULT '0',
  `invoice_no` varchar(64) DEFAULT '' COMMENT '发票NO',
  `status` int(64) DEFAULT '0' COMMENT '0未开 1已开',
  `tax_rate` varchar(64) DEFAULT '0' COMMENT '税点',
  `amount` decimal(11,2) DEFAULT '0.00' COMMENT '发票金额',
  `invoice_date` date DEFAULT NULL COMMENT '开票日期',
  `open_person` varchar(32) DEFAULT '' COMMENT '开票人',
  `bill_detail_id` varchar(256) DEFAULT '' COMMENT '账单ids',
  `invoice_type` varchar(32) DEFAULT '' COMMENT '发票类型',
  `c_uid` int(11) DEFAULT '1' COMMENT '创建用户ID',
  `u_uid` int(11) DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  `title` varchar(128) DEFAULT NULL,
  `bank_name` varchar(128) NOT NULL DEFAULT '' COMMENT '开户行',
  `account_name` varchar(128) NOT NULL DEFAULT '' COMMENT '账户名',
  `tax_number` varchar(64) NOT NULL DEFAULT '' COMMENT '纳税人识别号',
  `addr` varchar(256) DEFAULT '' COMMENT '开票地址',
  `tel_number` varchar(32) DEFAULT '' COMMENT '电话',
  PRIMARY KEY (`id`),
  KEY `idx_com_invoice_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_maintain
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_maintain`;
CREATE TABLE `wy_bse_maintain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '系统ID',
  `proj_id` int(11) DEFAULT '0' COMMENT '项目id',
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父ID',
  `parent_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1 channel 2 客户 3 供应商 4 政府关系 5 租户',
  `maintain_date` date DEFAULT NULL COMMENT '维护时间',
  `maintain_user` varchar(64) DEFAULT NULL COMMENT '维护人',
  `maintain_phone` varchar(64) DEFAULT '' COMMENT '维护人电话',
  `maintain_type` varchar(32) NOT NULL COMMENT '维护类型',
  `maintain_record` varchar(1024) NOT NULL COMMENT '维护记录',
  `maintain_feedback` varchar(1024) DEFAULT NULL COMMENT '反馈',
  `maintain_depart` varchar(64) DEFAULT NULL COMMENT '维护部门',
  `c_username` varchar(128) DEFAULT NULL COMMENT '维护用户',
  `times` int(11) DEFAULT '1' COMMENT '第几次接触',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `role_id` int(11) DEFAULT '0' COMMENT '角色id',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `channel_id` (`parent_id`,`parent_type`),
  KEY `idx_maintainDate` (`company_id`,`maintain_date`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_maintain_equipment
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_maintain_equipment`;
CREATE TABLE `wy_bse_maintain_equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `equipment_id` int(11) DEFAULT '0' COMMENT '设备Id',
  `equipment_type` varchar(50) DEFAULT NULL COMMENT '工程/秩序',
  `device_name` varchar(255) DEFAULT NULL COMMENT '设备名称',
  `model` varchar(512) DEFAULT NULL COMMENT '设备型号/参数',
  `major` varchar(128) DEFAULT NULL COMMENT '专业',
  `position` varchar(255) DEFAULT NULL COMMENT '位置',
  `maintain_period` varchar(255) DEFAULT NULL COMMENT '维保周期',
  `maintain_content` varchar(8000) DEFAULT NULL COMMENT '维保内容',
  `maintain_date` date DEFAULT NULL COMMENT '维保时间',
  `maintain_person` varchar(64) DEFAULT NULL COMMENT '维护人',
  `quantity` int(11) DEFAULT NULL COMMENT '数量',
  `maintain_type` varchar(20) DEFAULT NULL COMMENT '维护类型1 部分维保2 全部维保',
  `c_username` varchar(64) DEFAULT NULL COMMENT '录入人',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`proj_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_meter
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_meter`;
CREATE TABLE `wy_bse_meter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `tenant_id` int(11) DEFAULT '0' COMMENT '租户ID',
  `build_id` int(11) DEFAULT '0' COMMENT '楼号',
  `room_id` int(11) DEFAULT '0' COMMENT '房间号',
  `floor_id` int(11) DEFAULT '0' COMMENT '楼号',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `parent_id` int(11) DEFAULT '0' COMMENT '物理表ID,0 物理表',
  `meter_no` varchar(32) DEFAULT NULL COMMENT '表编号',
  `qrcode_path` varchar(256) DEFAULT NULL COMMENT '二维码地址',
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '类型：1水表,2  电表,',
  `build_no` varchar(32) DEFAULT NULL COMMENT '楼号',
  `floor_no` varchar(16) DEFAULT NULL COMMENT '层号',
  `room_no` varchar(32) DEFAULT NULL COMMENT '房间号',
  `position` varchar(128) DEFAULT NULL COMMENT '物理位置',
  `multiple` smallint(6) DEFAULT '1' COMMENT '倍数',
  `is_vaild` tinyint(2) NOT NULL DEFAULT '1' COMMENT '状态：1 有效；0弃用',
  `price` double(10,4) DEFAULT NULL COMMENT '单价',
  `master_slave` varchar(8) DEFAULT '1' COMMENT '1总表 2字表',
  `detail` varchar(200) DEFAULT NULL COMMENT '备注',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqe_no_type` (`type`,`meter_no`),
  KEY `idx_room_id` (`company_id`,`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_meter_history
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_meter_history`;
CREATE TABLE `wy_bse_meter_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `meter_no` varchar(32) DEFAULT NULL COMMENT '表编号',
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '类型：1 电表,2 水表',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID, 0公区',
  `build_no` varchar(32) NOT NULL DEFAULT '' COMMENT '楼号',
  `floor_no` varchar(16) NOT NULL DEFAULT '' COMMENT '层号',
  `room_no` varchar(32) NOT NULL DEFAULT '' COMMENT '房间号',
  `position` varchar(128) DEFAULT NULL COMMENT '物理位置',
  `multiple` smallint(6) DEFAULT '1' COMMENT '倍数',
  `is_vaild` varchar(2) DEFAULT NULL COMMENT '状态：1 有效；0弃用',
  `master_slave` varchar(8) DEFAULT NULL COMMENT '总表 字表 master|slave',
  `meter_type` tinyint(4) DEFAULT NULL COMMENT '表类型 1 实际表 2虚拟表',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`meter_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_meter_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_meter_log`;
CREATE TABLE `wy_bse_meter_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meter_id` int(11) NOT NULL DEFAULT '0' COMMENT '表id',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID, 0公区',
  `tenant_name` varchar(128) DEFAULT NULL COMMENT '客户名字',
  `meter_value` int(11) DEFAULT '0' COMMENT '表数',
  `remark` varchar(128) DEFAULT NULL COMMENT '备注',
  `c_username` varchar(32) DEFAULT NULL COMMENT '写入用户ID',
  `c_uid` int(11) DEFAULT '0' COMMENT '用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`meter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_meter_record
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_meter_record`;
CREATE TABLE `wy_bse_meter_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0',
  `meter_id` int(11) NOT NULL DEFAULT '0' COMMENT '表ID',
  `tenant_id` int(11) DEFAULT '0' COMMENT '租户ID, 为空是公区',
  `tenant_name` varchar(128) DEFAULT NULL COMMENT '客户名称',
  `pre_value` decimal(10,2) DEFAULT '0.00' COMMENT '上次读数',
  `pre_used_value` decimal(10,2) DEFAULT '0.00' COMMENT '上月用量',
  `pre_date` date DEFAULT NULL COMMENT '上次抄表月份',
  `meter_value` decimal(10,2) DEFAULT '0.00' COMMENT '表读数',
  `used_value` decimal(10,2) DEFAULT '0.00' COMMENT '使用量',
  `record_date` date NOT NULL COMMENT '抄表月份',
  `pic` varchar(1024) DEFAULT NULL COMMENT '抄表图片',
  `audit_user` varchar(32) DEFAULT NULL COMMENT '核准人',
  `audit_status` int(11) DEFAULT '0' COMMENT '审核状态',
  `remark` varchar(512) DEFAULT NULL COMMENT '备注',
  `c_username` varchar(32) DEFAULT NULL COMMENT '抄表人',
  `status` int(11) DEFAULT '0' COMMENT '1初始化 0 不是',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建人',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8 COMMENT='抄表记录';

-- ----------------------------
-- Table structure for wy_bse_parking_lot
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_parking_lot`;
CREATE TABLE `wy_bse_parking_lot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `parking_name` varchar(128) DEFAULT NULL COMMENT '停车场名称',
  `lot_no` varchar(255) DEFAULT NULL COMMENT '车位编号',
  `tenant_id` int(11) DEFAULT NULL COMMENT '租户ID',
  `tenant_name` varchar(255) DEFAULT NULL COMMENT '商（租）户名称',
  `rent_start` date DEFAULT NULL COMMENT '起租时间',
  `rent_end` date DEFAULT NULL COMMENT '退租时间',
  `rent_type` varchar(50) DEFAULT NULL COMMENT '租赁类型 新租/续租',
  `month_price` decimal(10,2) DEFAULT NULL COMMENT '月费率',
  `renter_name` varchar(255) DEFAULT NULL COMMENT '车主姓名',
  `renter_phone` varchar(50) DEFAULT NULL COMMENT '联系电话',
  `car_no` varchar(256) DEFAULT NULL COMMENT '车牌号',
  `charge_date` date DEFAULT NULL COMMENT '缴费日期',
  `rent_month` smallint(11) DEFAULT NULL COMMENT '租赁时长/月',
  `amount` decimal(10,2) DEFAULT NULL COMMENT '金额',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`proj_id`),
  KEY `company_id_2` (`company_id`,`tenant_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='车位';

-- ----------------------------
-- Table structure for wy_bse_project
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_project`;
CREATE TABLE `wy_bse_project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_type` varchar(32) NOT NULL DEFAULT '' COMMENT '创意园区,办公园区',
  `proj_name` varchar(32) NOT NULL DEFAULT '' COMMENT '项目名称',
  `proj_logo` varchar(128) DEFAULT NULL COMMENT '项目LOGO',
  `proj_province_id` int(11) DEFAULT '0' COMMENT '项目省份',
  `proj_province` varchar(64) DEFAULT NULL COMMENT '省份',
  `proj_city_id` int(11) DEFAULT NULL COMMENT '项目城市',
  `proj_city` varchar(64) DEFAULT NULL COMMENT '城市',
  `proj_district_id` int(11) DEFAULT NULL COMMENT '项目区',
  `proj_district` varchar(64) DEFAULT NULL COMMENT '区域',
  `proj_addr` varchar(256) DEFAULT NULL COMMENT '项目地址',
  `proj_occupy` int(11) DEFAULT '0' COMMENT '项目占地面积',
  `proj_buildarea` int(11) DEFAULT '0' COMMENT '项目建筑面积',
  `proj_usablearea` int(11) DEFAULT '0' COMMENT '项目使用面积',
  `proj_far` varchar(16) DEFAULT NULL COMMENT '项目容积率',
  `proj_pic` varchar(1024) DEFAULT NULL COMMENT '项目图片',
  `water_price` decimal(8,4) DEFAULT '0.0000' COMMENT '水单价',
  `electric_price` decimal(8,4) DEFAULT '0.0000' COMMENT '电单价',
  `is_vaild` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1启用，0禁用',
  `support` varchar(512) DEFAULT NULL COMMENT '项目配套',
  `advantage` varchar(512) DEFAULT NULL COMMENT '项目配套',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`proj_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_project_policy
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_project_policy`;
CREATE TABLE `wy_bse_project_policy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '系统ID',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `proj_join_policy` text NOT NULL COMMENT '招商政策',
  `remark` varchar(512) NOT NULL DEFAULT '' COMMENT '备注',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`proj_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_relations
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_relations`;
CREATE TABLE `wy_bse_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proj_id` int(11) DEFAULT '0',
  `company_id` int(11) DEFAULT '0',
  `department` varchar(255) DEFAULT NULL COMMENT '部门',
  `name` varchar(50) DEFAULT NULL COMMENT '姓名',
  `job_position` varchar(50) DEFAULT NULL COMMENT '职位',
  `address` varchar(1000) DEFAULT NULL COMMENT '办公地址',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `is_vaild` varchar(2) DEFAULT NULL COMMENT '状态：1 有效；0 无效',
  `c_uid` int(11) DEFAULT '0' COMMENT '创建人',
  `c_username` varchar(64) DEFAULT '' COMMENT '创建人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_sequence
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_sequence`;
CREATE TABLE `wy_bse_sequence` (
  `company_id` int(11) NOT NULL,
  `no` bigint(20) unsigned NOT NULL DEFAULT '1',
  `no_prefix` varchar(4) DEFAULT NULL COMMENT '客户编号前缀',
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_skyeye_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_skyeye_log`;
CREATE TABLE `wy_bse_skyeye_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `search_name` varchar(128) NOT NULL DEFAULT '' COMMENT '查询公司名',
  `search_result` text COMMENT '查询结果',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '查询人',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_supplier
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_supplier`;
CREATE TABLE `wy_bse_supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) DEFAULT '0' COMMENT '项目',
  `name` varchar(64) DEFAULT NULL COMMENT '供应商名称',
  `supplier_type` varchar(64) DEFAULT '' COMMENT '供应商类别',
  `major` varchar(64) DEFAULT NULL COMMENT '专业',
  `department` varchar(64) DEFAULT NULL COMMENT '维护部门',
  `service_content` varchar(4096) DEFAULT NULL COMMENT '服务内容',
  `contract_info` varchar(2048) DEFAULT NULL COMMENT '合同信息',
  `main_business` varchar(1000) DEFAULT NULL COMMENT '主营业务',
  `business_license` varchar(5) DEFAULT NULL COMMENT '营业执照副本',
  `operation_license` varchar(5) DEFAULT NULL COMMENT '生产经营许可证',
  `do_project` varchar(1024) DEFAULT NULL COMMENT '在郎园做过的项目/工程',
  `bank_name` varchar(64) DEFAULT '' COMMENT '银行账户',
  `account_number` varchar(64) DEFAULT '' COMMENT '银行账户号',
  `credit_code` varchar(64) DEFAULT '' COMMENT '银行账户号',
  `register_address` varchar(16) DEFAULT '' COMMENT '维护部门',
  `register_capital` varchar(16) DEFAULT '' COMMENT '维护部门',
  `maintain_depart` varchar(128) DEFAULT '' COMMENT '维护部门',
  `remark` varchar(512) DEFAULT NULL COMMENT '备注',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '状态：1 有效；4 删除',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `c_username` int(11) DEFAULT NULL COMMENT '创建人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_template
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_template`;
CREATE TABLE `wy_bse_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1合同 2 账单',
  `name` varchar(64) NOT NULL COMMENT '合同模版名称',
  `file_name` varchar(128) DEFAULT '' COMMENT '合同模版文件名',
  `file_path` varchar(512) NOT NULL COMMENT '合同模版路径',
  `remark` varchar(256) DEFAULT '' COMMENT '备注',
  `c_username` varchar(32) NOT NULL DEFAULT '' COMMENT '添加人',
  `c_uid` int(11) DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_template_parm
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_template_parm`;
CREATE TABLE `wy_bse_template_parm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1合同 2 账单',
  `parm_type` varchar(32) NOT NULL DEFAULT '' COMMENT '变量类型',
  `parm_name` varchar(32) NOT NULL DEFAULT '' COMMENT '变量名称',
  `remark` varchar(256) DEFAULT '' COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `parm_name` (`parm_name`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant`;
CREATE TABLE `wy_bse_tenant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `proj_name` varchar(128) DEFAULT NULL COMMENT '项目名称',
  `type` tinyint(4) DEFAULT '1' COMMENT '1 客户 2 租户 3 退租',
  `room_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '需求类型1 房源 2 工位 3 场馆',
  `parent_id` int(11) DEFAULT '0' COMMENT '父ID0表示租户 其他代表分摊租户',
  `tenant_no` varchar(128) NOT NULL DEFAULT '' COMMENT '租户编号',
  `name` varchar(128) NOT NULL COMMENT '租户名称',
  `business_id` int(11) DEFAULT '0' COMMENT '工商信息id',
  `checkin_date` date DEFAULT NULL COMMENT '入住时间',
  `industry` varchar(64) DEFAULT '' COMMENT '行业',
  `level` varchar(32) DEFAULT '' COMMENT '租户级别',
  `on_rent` int(11) NOT NULL DEFAULT '1' COMMENT '是否在租1是2 退租',
  `nature` varchar(32) DEFAULT '' COMMENT '单位性质：国企、私企、外资、其他',
  `worker_num` varchar(8) DEFAULT '' COMMENT '员工人数',
  `addr` varchar(128) DEFAULT NULL COMMENT '地址',
  `state` varchar(32) NOT NULL COMMENT '来电、来访、意向、确认、签约、流失',
  `visit_date` date DEFAULT NULL COMMENT '初次来访时间',
  `belong_uid` int(11) DEFAULT '0' COMMENT '跟进人ID',
  `distribute_uid` int(11) DEFAULT '0' COMMENT '分配人',
  `belong_person` varchar(16) DEFAULT NULL COMMENT '跟进人姓名',
  `channel_id` int(11) DEFAULT '0' COMMENT '渠道ID',
  `channel_name` varchar(32) DEFAULT '' COMMENT '渠道名称',
  `channel_contact` varchar(32) DEFAULT '' COMMENT '渠道联系人',
  `channel_contact_phone` varchar(32) DEFAULT '' COMMENT '渠道联系电话',
  `rate` varchar(32) DEFAULT NULL COMMENT '客户评价',
  `deal_rate` smallint(6) DEFAULT '0' COMMENT '客户成交率',
  `tags` varchar(32) DEFAULT NULL COMMENT '客户标签',
  `source_type` int(11) DEFAULT '0' COMMENT '客户来源类型',
  `remark` varchar(1024) DEFAULT '' COMMENT '备注',
  `status` tinyint(4) DEFAULT '1' COMMENT '租户状态',
  `brokerage` decimal(10,1) DEFAULT '0.0' COMMENT '佣金系数',
  `c_uid` int(11) DEFAULT '0' COMMENT '新增用户ID',
  `u_uid` int(11) DEFAULT '0' COMMENT '更新用户ID',
  `depart_id` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`company_id`,`name`),
  KEY `idx_com_projid` (`company_id`,`proj_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_bill
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_bill`;
CREATE TABLE `wy_bse_tenant_bill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) DEFAULT '0' COMMENT '项目iD',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `contract_id` int(11) DEFAULT '0' COMMENT '合同id',
  `tenant_name` varchar(128) NOT NULL COMMENT '租户名称',
  `amount` decimal(11,2) DEFAULT '0.00' COMMENT '金额',
  `late_amount` decimal(11,2) DEFAULT '0.00' COMMENT '滞纳金',
  `charge_date` date DEFAULT NULL COMMENT '账单月份',
  `receive_date` date DEFAULT NULL COMMENT '收款时间',
  `receive_amount` decimal(10,0) DEFAULT '0' COMMENT '收款金额',
  `bank_id` int(11) NOT NULL DEFAULT '0' COMMENT '租金收款账号ID',
  `audit_user` varchar(32) DEFAULT NULL COMMENT '审核人',
  `status` int(11) DEFAULT '0' COMMENT '是否结清1 已结清 0 未结清',
  `audit_uid` int(32) DEFAULT '0' COMMENT '审核人ID',
  `remark` varchar(128) DEFAULT '' COMMENT '备注',
  `c_uid` int(11) DEFAULT '0',
  `u_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `bill_no` varchar(64) DEFAULT NULL COMMENT '账单编号',
  `bill_title` varchar(128) DEFAULT NULL COMMENT '账单名称',
  `is_print` tinyint(4) DEFAULT '0' COMMENT '0未打印 1 已打印',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_bill_detail
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_bill_detail`;
CREATE TABLE `wy_bse_tenant_bill_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) DEFAULT '0' COMMENT '项目id',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `tenant_name` varchar(128) DEFAULT '' COMMENT '租户名称',
  `contract_id` int(11) DEFAULT '0' COMMENT '合同ID',
  `bill_id` int(11) NOT NULL DEFAULT '0' COMMENT '表头id',
  `bill_type` int(11) DEFAULT '1' COMMENT '1收款 2 付款',
  `charge_date` date DEFAULT NULL COMMENT '应收',
  `type` tinyint(4) DEFAULT '1' COMMENT '随feeType 表type 1 费用 2 押金',
  `fee_type` varchar(16) NOT NULL COMMENT '类型 随 feetype 表 id',
  `amount` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '金额',
  `create_type` int(11) DEFAULT '1' COMMENT '1 自动生成 2 收工添加',
  `discount_amount` decimal(11,2) DEFAULT '0.00' COMMENT '优惠金额',
  `receive_date` date DEFAULT NULL COMMENT '收款日期',
  `receive_amount` decimal(11,2) DEFAULT '0.00' COMMENT '收款金额',
  `bill_date` varchar(256) DEFAULT NULL COMMENT '收款期间',
  `invoice_id` int(11) DEFAULT '0' COMMENT '发票ID',
  `bank_id` int(11) DEFAULT '0' COMMENT '收款账户id',
  `remark` varchar(256) DEFAULT NULL COMMENT '备注',
  `status` int(11) DEFAULT '0' COMMENT '0 未结清1 结清',
  `c_uid` int(11) DEFAULT '0',
  `u_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`tenant_id`),
  KEY `cus_id` (`tenant_id`,`fee_type`),
  KEY `idx_contract_id` (`company_id`,`bill_id`)
) ENGINE=InnoDB AUTO_INCREMENT=375 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_bill_detail_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_bill_detail_log`;
CREATE TABLE `wy_bse_tenant_bill_detail_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `bill_detail_id` int(11) NOT NULL DEFAULT '0' COMMENT '费用id',
  `amount` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '金额',
  `edit_amount` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '金额',
  `discount_amount` decimal(11,2) DEFAULT '0.00' COMMENT '优惠金额',
  `edit_discount_amount` decimal(11,2) DEFAULT '0.00' COMMENT '优惠金额',
  `edit_reason` varchar(256) DEFAULT '' COMMENT '修改原因',
  `edit_user` varchar(128) DEFAULT '' COMMENT '修改用户',
  `c_uid` int(11) DEFAULT '0',
  `u_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cus_id` (`company_id`,`bill_detail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_extra
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_extra`;
CREATE TABLE `wy_bse_tenant_extra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `demand_area` varchar(16) DEFAULT '0' COMMENT '需求面积',
  `trim_state` varchar(32) DEFAULT NULL COMMENT '装修需求',
  `recommend_room_id` varchar(64) DEFAULT '' COMMENT '推荐产品ID',
  `recommend_room` varchar(256) DEFAULT '' COMMENT '推荐房源信息',
  `purpose_room` varchar(128) DEFAULT NULL COMMENT '意向房间',
  `purpose_price` varchar(8) DEFAULT NULL COMMENT '意向单价',
  `purpose_term_lease` varchar(8) DEFAULT NULL COMMENT '意向租赁时间',
  `purpose_free_time` varchar(8) DEFAULT NULL COMMENT '意向免租时间',
  `current_proj` varchar(128) DEFAULT '' COMMENT '当前项目',
  `current_addr` varchar(256) DEFAULT '' COMMENT '当前办公地址',
  `current_area` varchar(16) DEFAULT '' COMMENT '当前面积',
  `current_price` varchar(16) DEFAULT '' COMMENT '当前单价',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `cus_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_follow
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_follow`;
CREATE TABLE `wy_bse_tenant_follow` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) DEFAULT '0' COMMENT '项目ID',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `follow_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '跟进类型：1来访，2 电话，3微信  ，4QQ、5其他',
  `state` varchar(16) NOT NULL COMMENT '1:来电、2来访、3意向、4确认、5签约、6流失',
  `follow_record` varchar(1024) DEFAULT NULL COMMENT '跟进记录',
  `follow_username` varchar(32) DEFAULT NULL COMMENT '跟进用户',
  `follow_time` date DEFAULT NULL COMMENT '跟进时间',
  `contact_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户联系人ID',
  `contact_user` varchar(50) NOT NULL COMMENT '客户联系人',
  `contact_phone` varchar(12) DEFAULT '' COMMENT '联系人电话',
  `loss_reason` varchar(64) DEFAULT NULL COMMENT '流失原因',
  `times` smallint(6) DEFAULT '0' COMMENT '第几次跟进',
  `visit_times` int(11) DEFAULT '0' COMMENT '来访次数',
  `next_date` date DEFAULT NULL COMMENT '下次跟进时间',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `depart_id` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `cus_id` (`tenant_id`),
  KEY `idx_createdata` (`company_id`,`proj_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_invoice
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_invoice`;
CREATE TABLE `wy_bse_tenant_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `title` varchar(128) DEFAULT NULL,
  `bank_name` varchar(128) NOT NULL DEFAULT '' COMMENT '开户行',
  `account_name` varchar(128) NOT NULL DEFAULT '' COMMENT '账户名',
  `tax_number` varchar(64) NOT NULL DEFAULT '' COMMENT '纳税人识别号',
  `addr` varchar(256) DEFAULT '' COMMENT '开票地址',
  `tel_number` varchar(32) DEFAULT '' COMMENT '电话',
  `invoice_type` varchar(32) DEFAULT '' COMMENT '发票类型',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cus_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_leaseback
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_leaseback`;
CREATE TABLE `wy_bse_tenant_leaseback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `contract_id` int(11) DEFAULT '0' COMMENT '合同id ',
  `tenant_name` varchar(128) DEFAULT NULL COMMENT '租户名称',
  `leaseback_date` date NOT NULL COMMENT '退租日期',
  `regaddr_change_date` date DEFAULT NULL COMMENT '注册地址变更',
  `leaseback_reason` varchar(128) NOT NULL COMMENT '退租原因',
  `type` tinyint(4) DEFAULT '1' COMMENT '1正常退租，2提前退租',
  `is_settle` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否结算 1 已结算 2 未结算',
  `company_id` int(11) DEFAULT '0' COMMENT '公司id',
  `proj_id` decimal(10,0) NOT NULL DEFAULT '0' COMMENT '项目id',
  `remark` varchar(512) DEFAULT '' COMMENT '备注',
  `c_uid` int(11) DEFAULT '0',
  `u_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_id` (`tenant_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_log`;
CREATE TABLE `wy_bse_tenant_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT '0' COMMENT '企业ID',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `content` varchar(512) NOT NULL DEFAULT '' COMMENT '备注',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `c_username` varchar(20) NOT NULL DEFAULT '' COMMENT '创建用户真实姓名',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_refund_record
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_refund_record`;
CREATE TABLE `wy_bse_tenant_refund_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) DEFAULT '0' COMMENT '项目id',
  `bill_detail_id` int(11) NOT NULL DEFAULT '0' COMMENT '费用单ID',
  `charge_id` int(11) NOT NULL DEFAULT '0' COMMENT '收支记录ID',
  `amount` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '退款金额',
  `refund_date` date DEFAULT NULL COMMENT '退款日期',
  `bank_id` int(11) DEFAULT '0' COMMENT '退款账户id',
  `remark` varchar(256) DEFAULT NULL COMMENT '备注',
  `status` int(11) DEFAULT '0',
  `c_user` varchar(256) DEFAULT NULL COMMENT '操作人',
  `c_uid` int(11) DEFAULT '0',
  `u_uid` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_remind
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_remind`;
CREATE TABLE `wy_bse_tenant_remind` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `tenant_name` varchar(64) NOT NULL COMMENT '客户名称',
  `remind_date` datetime DEFAULT NULL COMMENT '提醒跟进时间',
  `remind_content` varchar(256) NOT NULL COMMENT '提醒内容',
  `remind_user` varchar(32) DEFAULT NULL COMMENT '提醒人',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `u_uid` int(11) NOT NULL DEFAULT '0' COMMENT '更新用户ID',
  `depart_id` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `cus_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_room
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_room`;
CREATE TABLE `wy_bse_tenant_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目',
  `proj_name` varchar(64) DEFAULT '' COMMENT '项目',
  `build_id` int(11) NOT NULL DEFAULT '0' COMMENT '楼号ID',
  `build_no` varchar(32) DEFAULT '' COMMENT '楼号',
  `floor_id` int(11) NOT NULL DEFAULT '0' COMMENT '层ID',
  `floor_no` varchar(64) DEFAULT '' COMMENT '层号',
  `room_id` int(11) NOT NULL DEFAULT '0' COMMENT '房间ID',
  `room_no` varchar(64) DEFAULT '' COMMENT '房间',
  `room_area` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '房间面积',
  `room_type` tinyint(4) DEFAULT '1' COMMENT '房源编号1 房源2 工位',
  `station_no` varchar(64) DEFAULT NULL COMMENT '工位编号',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_roomId` (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_tenant_share_rule
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_tenant_share_rule`;
CREATE TABLE `wy_bse_tenant_share_rule` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) NOT NULL DEFAULT '0',
  `tenant_id` bigint(20) unsigned NOT NULL,
  `contract_id` bigint(20) unsigned NOT NULL,
  `bill_rule_id` int(11) NOT NULL COMMENT '合同费用规则id',
  `fee_type` int(11) NOT NULL COMMENT '分摊费用类型',
  `share_type` int(11) NOT NULL DEFAULT '1' COMMENT '分摊方式1比例 2 固定金额',
  `share_num` decimal(11,2) DEFAULT NULL COMMENT '分摊数额',
  `remark` varchar(256) DEFAULT '' COMMENT '备注',
  `c_uid` int(11) DEFAULT NULL,
  `u_uid` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_ten_contract` (`company_id`,`tenant_id`,`contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_user_profile
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_user_profile`;
CREATE TABLE `wy_bse_user_profile` (
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `default_proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户默认选择的项目ID',
  `page_rows` smallint(6) NOT NULL DEFAULT '15' COMMENT '用户每页显示行数',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_venue
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_venue`;
CREATE TABLE `wy_bse_venue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `proj_name` varchar(128) DEFAULT NULL COMMENT '项目名称',
  `venue_name` varchar(32) NOT NULL DEFAULT '' COMMENT '名称',
  `venue_addr` varchar(256) DEFAULT NULL COMMENT '地址',
  `venue_area` varchar(16) DEFAULT NULL COMMENT '面积',
  `venue_capacity` int(11) DEFAULT '0' COMMENT '容纳人数',
  `venue_price` varchar(16) DEFAULT NULL COMMENT '参考价格 天',
  `venue_content` varchar(4096) DEFAULT NULL COMMENT '内容',
  `venue_facility` varchar(4096) DEFAULT NULL COMMENT '场馆设施',
  `venue_pic` varchar(2048) DEFAULT NULL COMMENT '场馆图片地址',
  `remark` varchar(1024) DEFAULT NULL COMMENT '备注',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '0禁用1启用',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`venue_name`),
  KEY `idx_projId` (`company_id`,`proj_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_venue_book
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_venue_book`;
CREATE TABLE `wy_bse_venue_book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `venue_id` int(11) NOT NULL DEFAULT '0' COMMENT '场馆ID',
  `activity_type` varchar(32) NOT NULL DEFAULT '' COMMENT '活动类型',
  `activity_type_id` tinyint(4) NOT NULL DEFAULT '0' COMMENT '活动类型ID',
  `start_date` date DEFAULT NULL COMMENT '预定时间',
  `end_date` date DEFAULT NULL COMMENT '预定时间',
  `person_num` varchar(10) DEFAULT NULL COMMENT '预计活动人数',
  `period` tinyint(4) NOT NULL DEFAULT '0' COMMENT '预定时长天为单位',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '预定单价',
  `is_deposit` tinyint(4) DEFAULT '0' COMMENT '1缴纳 0 没有缴纳',
  `deposit_amount` decimal(11,2) DEFAULT '0.00' COMMENT '押金金额',
  `cus_id` int(11) DEFAULT '0' COMMENT '预定客户ID',
  `cus_name` varchar(128) DEFAULT NULL COMMENT '预定公司',
  `contact_user` varchar(32) DEFAULT NULL COMMENT '预定人',
  `contact_phone` varchar(32) DEFAULT '' COMMENT '预定人电话',
  `state` tinyint(4) DEFAULT '1' COMMENT '预定状态1预定中2已确定 3已结算，99 取消预定',
  `cancel_reason` varchar(512) DEFAULT NULL COMMENT '预定取消原因',
  `cancel_date` date DEFAULT NULL COMMENT '取消时间',
  `settle_amount` decimal(10,2) DEFAULT NULL COMMENT '结算金额',
  `settle_date` date DEFAULT NULL COMMENT '结算日期',
  `contract` varchar(128) DEFAULT NULL COMMENT '合同名称',
  `settle_username` varchar(16) DEFAULT '' COMMENT '结算人',
  `pic` varchar(1024) DEFAULT NULL COMMENT '活动图片',
  `remark` varchar(512) DEFAULT '' COMMENT '备注',
  `belong_uid` int(11) DEFAULT '0' COMMENT '归属人id',
  `belong_person` varchar(32) DEFAULT NULL COMMENT '归属人',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `c_username` varchar(64) NOT NULL DEFAULT '' COMMENT '插入人',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`,`activity_type`),
  KEY `start_date` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_venue_settle
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_venue_settle`;
CREATE TABLE `wy_bse_venue_settle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cus_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户id',
  `venue_id` int(11) NOT NULL DEFAULT '0' COMMENT '场馆ID',
  `book_id` int(11) NOT NULL DEFAULT '0' COMMENT '场馆预定ID',
  `fee_type` varchar(32) NOT NULL DEFAULT '' COMMENT '费用类型',
  `amount` decimal(11,2) DEFAULT '0.00' COMMENT '费用金额',
  `remark` varchar(512) DEFAULT '' COMMENT '备注',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cus_id` (`cus_id`),
  KEY `venue_id` (`venue_id`),
  KEY `book_id` (`book_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_workorder
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_workorder`;
CREATE TABLE `wy_bse_workorder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(64) DEFAULT NULL COMMENT '单号',
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `proj_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目id',
  `open_time` datetime DEFAULT NULL COMMENT '开单日期',
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `tenant_name` varchar(128) DEFAULT NULL COMMENT '商（租）户名称',
  `building_floor_room` varchar(256) DEFAULT NULL COMMENT '楼号',
  `build_floor_room_id` varchar(64) DEFAULT '0' COMMENT '房间ID ',
  `position` varchar(128) DEFAULT NULL COMMENT '维修区域',
  `open_person` varchar(32) DEFAULT NULL COMMENT '报修人',
  `open_phone` varchar(32) DEFAULT NULL COMMENT '报修人手机号',
  `repair_goods` varchar(256) DEFAULT NULL COMMENT '报修物品',
  `repair_content` varchar(2048) DEFAULT NULL COMMENT '报修内容',
  `pic` varchar(1024) DEFAULT NULL COMMENT '报修图片',
  `order_time` datetime DEFAULT NULL COMMENT '接单时间',
  `order_person` varchar(32) DEFAULT NULL COMMENT '接单人',
  `process_result` varchar(1024) DEFAULT NULL COMMENT '处理结果',
  `maintain_pic` varchar(1024) DEFAULT NULL COMMENT '维修图片',
  `return_time` datetime DEFAULT NULL COMMENT '返单时间',
  `time_used` varchar(8) DEFAULT NULL COMMENT '所用时长小时',
  `charge_amount` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '收费金额',
  `engineering_type` varchar(50) DEFAULT NULL COMMENT '工程专业分类',
  `order_source` varchar(50) DEFAULT NULL COMMENT '单据来源；微信小程序 微信 电话',
  `remark` varchar(512) DEFAULT NULL COMMENT '备注',
  `maintain_person` varchar(128) DEFAULT '' COMMENT '维修人',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态： 1开单 2 接单  3 完成 4 关闭',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '状态：1 有效；0撤单',
  `is_notice` varchar(10) DEFAULT NULL COMMENT '是否短信通知',
  `feedback` varchar(1000) DEFAULT NULL COMMENT '保修人反馈',
  `feedback_rate` double(4,2) DEFAULT '0.00' COMMENT '评分',
  `c_uid` int(11) NOT NULL DEFAULT '0',
  `u_uid` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `order_uid` int(11) DEFAULT NULL COMMENT '接单人ID',
  PRIMARY KEY (`id`),
  KEY `idx_com_cus_id` (`company_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COMMENT='工单';

-- ----------------------------
-- Table structure for wy_bse_workorder_log
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_workorder_log`;
CREATE TABLE `wy_bse_workorder_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workorder_id` int(11) NOT NULL,
  `status` int(11) DEFAULT NULL,
  `c_username` varchar(128) DEFAULT '' COMMENT '处理人',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_bse_wx_user
-- ----------------------------
DROP TABLE IF EXISTS `wy_bse_wx_user`;
CREATE TABLE `wy_bse_wx_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司ID',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户iD',
  `name` varchar(128) DEFAULT '',
  `email` varchar(128) DEFAULT '',
  `email_verified_at` varchar(128) DEFAULT '0',
  `username` varchar(128) DEFAULT '',
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(512) DEFAULT '',
  `openid` varchar(128) DEFAULT NULL,
  `nickname` varchar(128) DEFAULT '',
  `unionid` varchar(128) DEFAULT NULL,
  `country` varchar(128) DEFAULT '',
  `province` varchar(128) DEFAULT '',
  `city` varchar(128) DEFAULT '',
  `language` varchar(128) DEFAULT '',
  `location` varchar(128) DEFAULT '',
  `gender` varchar(128) DEFAULT '',
  `level` varchar(128) DEFAULT '',
  `is_admin` varchar(128) DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `c_uid` int(11) DEFAULT NULL COMMENT '创建人',
  `u_uid` int(11) DEFAULT NULL COMMENT '更新人',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_failed_jobs
-- ----------------------------
DROP TABLE IF EXISTS `wy_failed_jobs`;
CREATE TABLE `wy_failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8_unicode_ci NOT NULL,
  `queue` text COLLATE utf8_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_migrations
-- ----------------------------
DROP TABLE IF EXISTS `wy_migrations`;
CREATE TABLE `wy_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_password_resets
-- ----------------------------
DROP TABLE IF EXISTS `wy_password_resets`;
CREATE TABLE `wy_password_resets` (
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_public_message
-- ----------------------------
DROP TABLE IF EXISTS `wy_public_message`;
CREATE TABLE `wy_public_message` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(4) DEFAULT NULL COMMENT '消息类别',
  `title` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '标题',
  `content` text COLLATE utf8_unicode_ci COMMENT '消息内容',
  `company_id` int(11) NOT NULL DEFAULT '0' COMMENT '公司id',
  `role_id` int(11) DEFAULT '1' COMMENT '角色id',
  `receive_uid` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '接收人ID',
  `sender_uid` int(11) DEFAULT NULL COMMENT '发送人id',
  `sender_username` varchar(20) CHARACTER SET utf8 DEFAULT NULL COMMENT '发送人名字',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_id_receive` (`company_id`,`receive_uid`),
  KEY `idx_sender` (`sender_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_public_message_read
-- ----------------------------
DROP TABLE IF EXISTS `wy_public_message_read`;
CREATE TABLE `wy_public_message_read` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `msg_id` int(11) NOT NULL DEFAULT '0' COMMENT '消息id',
  `uid` int(11) DEFAULT NULL COMMENT '人员id',
  `is_delete` tinyint(4) DEFAULT '0' COMMENT '0 未删除1 删除',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_msgid_uid` (`msg_id`,`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=219 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_sys_area
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_area`;
CREATE TABLE `wy_sys_area` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `code` varchar(10) NOT NULL,
  `name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=110120 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_sys_company
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_company`;
CREATE TABLE `wy_sys_company` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '公司名称',
  `alias_name` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `credit_code` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '营业执照号',
  `contact_per` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '联系人',
  `tel` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `province_id` int(6) DEFAULT NULL COMMENT '省份id',
  `city_id` int(11) DEFAULT NULL COMMENT '城市id',
  `district_id` int(11) DEFAULT NULL COMMENT '区id',
  `address` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '地址',
  `logo` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '企业logo',
  `config` json DEFAULT NULL COMMENT '配置',
  `product_id` int(1) DEFAULT NULL COMMENT '关联产品',
  `proj_count` tinyint(4) NOT NULL DEFAULT '1' COMMENT '项目数量',
  `remark` varchar(500) COLLATE utf8_unicode_ci DEFAULT '' COMMENT '备注',
  `expire_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_sys_company_module
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_company_module`;
CREATE TABLE `wy_sys_company_module` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(60) NOT NULL,
  `module_id` int(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_sys_module
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_module`;
CREATE TABLE `wy_sys_module` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_sys_order
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_order`;
CREATE TABLE `wy_sys_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(20) DEFAULT NULL COMMENT '订单号',
  `product_id` int(1) DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '订单状态',
  `paytime` timestamp NULL DEFAULT NULL COMMENT '支付时间',
  `company_id` int(1) NOT NULL DEFAULT '0' COMMENT '客户ID',
  `month` int(1) NOT NULL DEFAULT '0' COMMENT '购买时长',
  `price` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `content` varchar(512) DEFAULT '' COMMENT '备注',
  `c_uid` int(11) NOT NULL DEFAULT '0' COMMENT '创建用户ID',
  `c_username` varchar(20) NOT NULL DEFAULT '' COMMENT '创建用户',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_sys_product
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_product`;
CREATE TABLE `wy_sys_product` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '产品名称',
  `en_name` varchar(20) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL COMMENT '价格',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for wy_sys_role
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_role`;
CREATE TABLE `wy_sys_role` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT '0' COMMENT '公司id',
  `name` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `menu_list` varchar(256) COLLATE utf8_unicode_ci NOT NULL COMMENT '页面列表',
  `permission` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `remark` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_sys_user_group
-- ----------------------------
DROP TABLE IF EXISTS `wy_sys_user_group`;
CREATE TABLE `wy_sys_user_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `project_limit` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `remark` varchar(200) COLLATE utf8_unicode_ci DEFAULT '',
  `company_id` int(1) NOT NULL,
  `c_uid` int(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
-- Table structure for wy_users
-- ----------------------------
DROP TABLE IF EXISTS `wy_users`;
CREATE TABLE `wy_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `realname` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  `phone` varchar(11) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `company_id` int(11) NOT NULL DEFAULT '0',
  `role_id` int(12) NOT NULL,
  `group_id` int(1) DEFAULT NULL COMMENT '用户组',
  `depart_id` int(11) NOT NULL DEFAULT '0' COMMENT '部门id',
  `unionid` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '微信唯一ID',
  `avatar` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '头像',
  `is_vaild` tinyint(4) DEFAULT '1' COMMENT '1启用2禁用',
  `is_admin` tinyint(4) DEFAULT '0' COMMENT '是否主账号',
  `is_manager` int(11) DEFAULT '0' COMMENT '0不是 1 是',
  `c_uid` int(11) DEFAULT NULL,
  `u_uid` int(11) DEFAULT NULL,
  `remark` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_name_passwd` (`name`,`password`),
  KEY `idx_unionid` (`unionid`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
