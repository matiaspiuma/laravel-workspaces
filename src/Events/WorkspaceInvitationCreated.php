<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Events;

use Bhhaskin\LaravelWorkspaces\Models\WorkspaceInvitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WorkspaceInvitation $invitation
    ) {
    }
}
