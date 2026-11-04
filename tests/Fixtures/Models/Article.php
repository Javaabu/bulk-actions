<?php

namespace Javaabu\BulkActions\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function publish(): void
    {
        $this->update(['status' => 'published']);
    }
}
