<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Tests\Fixtures;

use Bhhaskin\LaravelWorkspaces\Traits\HasWorkspaces;
use Bhhaskin\RolesPermissions\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasRoles;
    use HasWorkspaces;
    use Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
