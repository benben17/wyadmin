CREATE TABLE `wy_bse_equipment` (
  id int not null auto_increment,
  company_id int not null default 0 comment '公司id',
  proj_id  int not null default 0 comment '项目ID',
  `system_name` varchar(255) DEFAULT NULL COMMENT '系统',
  `position` varchar(255) DEFAULT NULL COMMENT '位置',
  `major` varchar(255) DEFAULT NULL COMMENT '主责专业',
  `device_name` varchar(255) DEFAULT NULL COMMENT '名称',
  `model` varchar(256) DEFAULT NULL COMMENT '设备型号参数',
  `quantity` int(11) DEFAULT NULL COMMENT '设备数量',
  `unit` varchar(32) DEFAULT NULL COMMENT '单位',
  `maintain_period` varchar(50) DEFAULT NULL COMMENT '周期按周、月、季度、半年、年、进行维保',
  `maintain_content` varchar(4096) COMMENT '维保内容',
  `maintain_times` int(11) DEFAULT NULL COMMENT '每年维保次数',
  `c_uid` int DEFAULT NULL COMMENT '创建人',
  `u_uid` int DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`Id`),
  key(company_id,proj_id)
)


CREATE TABLE `wy_bse_maintain_equipment` (
  id int not null auto_increment,
  company_id int not null default 0 comment '公司id',
  proj_id  int not null default 0 comment '项目ID',
  `equipment_id` int DEFAULT 0 COMMENT '设备Id',
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
  c_username  varchar(64) DEFAULT NULL COMMENT '录入人',
  `c_uid` int DEFAULT NULL COMMENT '创建人',
  `u_uid` int DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  key(company_id,proj_id)
)