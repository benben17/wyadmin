<?php

namespace App\Models;

use DateTimeInterface;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
  // 隐藏字段
  protected $hidden = ['deleted_at', 'updated_at'];

  public $timestamps = true; // 使用时间戳
  const CREATED_AT = 'created_at';
  const UPDATED_AT = 'updated_at';

  // 日期格式化
  protected function serializeDate(DateTimeInterface $date)
  {
    return $date->format('Y-m-d H:i:s');
  }



  // 批量插入
  public function addAll(array $data)
  {
    return $this->insert($data);
  }
}
