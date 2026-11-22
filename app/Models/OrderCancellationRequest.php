<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCancellationRequest extends Model
{
    protected $fillable = ['order_id', 'user_id', 'reason', 'status'];
}
