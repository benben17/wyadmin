<?php

namespace App\Api\Models\Common;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

/**
 *  消息已读未读
 */
class MessageRead extends Model
{
	/**
	 * 关联到模型的数据表
	 *
	 * @var string
	 */


	// use SoftDeletes;
	protected $table = 'public_message_read';
	protected $fillable = ['mesg_id', 'uid', 'is_read', 'created_at'];
	protected $hidden = ['deleted_at', 'updated_at'];

	public function addAll(array $data)
	{
		$res = DB::table($this->getTable())->insert($data);
		return $res;
	}
	// protected static function boot()
	// {
	// 	parent::boot();
	// 	static::addGlobalScope(new CompanyScope);
	// }
}
