<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Bhhaskin\LaravelWorkspaces\Traits\Workspaceable;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use Workspaceable;

    protected $guarded = [];
}
