<?php
namespace App\Api\Models\Energy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class MeterLog extends Model
{
/**
  * 关联到模型的数据表
  *
  * @var string
  */

  protected $table = 'bse_meter_log';
  protected $fillable = [];
  protected $hidden = ['deleted_at',"company_id",'updated_at'];



}
