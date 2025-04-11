<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenAiMessage extends Model
{
    protected $fillable = [
        'assistant_id',
        'thread_id',
        'run_id',
        'prompt',
        'type',
        'raw_response',
        'metadata',
        'notes',
    ];
}
