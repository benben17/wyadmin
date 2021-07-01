<?php

namespace App\Api\Models\Common;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 *  消息
 */
class Message extends Model
{
	/**
	 * 关联到模型的数据表
	 *
	 * @var string
	 */


	// use SoftDeletes;
	protected $table = 'public_message';
	protected $fillable = ['type', 'title', 'content', 'company_id', 'role_id', 'sender_uid', 'receive_uid', 'sender_username'];
	protected $hidden = ['deleted_at', "company_id", 'updated_at'];



	public function messageRead()
	{
		return $this->hasMany(MessageRead::class, 'msg_id', 'id');
	}

	protected static function boot()
	{
		parent::boot();
		static::addGlobalScope(new CompanyScope);
	}
}
