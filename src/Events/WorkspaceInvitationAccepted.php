<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Events;

use Bhhaskin\LaravelWorkspaces\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationAccepted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public Model $user
    ) {
    }
}
