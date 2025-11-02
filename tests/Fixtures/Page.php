<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Tests\Fixtures;

use Bhhaskin\LaravelWorkspaces\Traits\Workspaceable;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use Workspaceable;

    protected $guarded = [];
}
