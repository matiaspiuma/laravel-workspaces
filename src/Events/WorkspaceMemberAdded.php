<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Events;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\RolesPermissions\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceMemberAdded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public Model $member,
        public Role $role
    ) {
    }
}
