<?php

namespace App\Models;

use DateTimeInterface;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
  protected $hidden = ['deleted_at', 'updated_at'];

  protected function serializeDate(DateTimeInterface $date)
  {
    return $date->format('Y-m-d H:i:s');
  }

  public function addAll(array $data)
  {
    return $this->insert($data);
  }
}
