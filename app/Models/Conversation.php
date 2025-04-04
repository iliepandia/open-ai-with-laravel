<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'assistant_id',
        'thread_id',
        'run_id',
        'source',
        'message',
        'annotations',
        'feedback',
        'feedback_text',
    ];
}
