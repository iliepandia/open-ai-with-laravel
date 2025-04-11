<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenAiApiLog extends Model
{
    protected $fillable = [
        'user_id',
        'request',
        'response',
        'duration',
    ];
}
