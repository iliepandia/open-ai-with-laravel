<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WpPostMeta extends Model
{
    protected $table = "postmeta";

    protected $connection = "mysql-live";

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('app.wp_db_prefix') . $this->table;
    }
}
