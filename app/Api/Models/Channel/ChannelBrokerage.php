<?php

namespace App\Api\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;


/**
 *. 渠道佣金日志
 */
class ChannelBrokerage extends Model
{

  // use SoftDeletes;

  protected $table = 'bse_channel_brokerage_log';

  // protected $fillable = [];
  protected $hidden = ['deleted_at', "company_id", 'u_uid', 'updated_at'];
}
