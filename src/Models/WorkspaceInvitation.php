<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Models;

use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationAccepted;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationDeclined;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;
use Bhhaskin\RolesPermissions\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkspaceInvitation extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
        'expires_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'declined_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];

    public function getTable()
    {
        return config('workspaces.tables.workspace_invitations', parent::getTable());
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            if (empty($invitation->uuid)) {
                $invitation->uuid = (string) Str::uuid();
            }

            if (empty($invitation->token)) {
                $invitation->token = (string) Str::uuid();
            }

            $invitation->email = Str::lower(trim($invitation->email));
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(WorkspaceConfig::workspaceModel());
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(WorkspaceRoles::roleModel());
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof CarbonImmutable && $this->expires_at->isPast();
    }

    public function isHandled(): bool
    {
        return $this->accepted_at !== null || $this->declined_at !== null;
    }

    public function accept(Model $user): void
    {
        $this->assertUserCanAccept($user);

        $role = $this->role instanceof Role
            ? $this->role
            : WorkspaceRoles::resolve();

        $this->workspace->addMember($user, $role);

        $this->forceFill([
            'accepted_at' => CarbonImmutable::now(),
        ])->save();

        event(new WorkspaceInvitationAccepted($this, $user));
    }

    public function decline(): void
    {
        $this->forceFill([
            'declined_at' => CarbonImmutable::now(),
        ])->save();

        event(new WorkspaceInvitationDeclined($this));
    }

    protected function assertUserCanAccept(Model $user): void
    {
        $expected = WorkspaceConfig::userModel();

        if (! $user instanceof $expected) {
            throw new InvalidArgumentException(sprintf(
                'Invitation acceptor must be an instance of %s; %s given.',
                $expected,
                $user::class
            ));
        }

        if ($this->isExpired()) {
            throw new InvalidArgumentException('The invitation has expired.');
        }

        if ($this->isHandled()) {
            throw new InvalidArgumentException('The invitation has already been accepted or declined.');
        }

        $email = Str::lower((string) $user->getAttribute('email'));

        if ($email === '' || $email !== $this->email) {
            throw new InvalidArgumentException('The invitation email does not match the user email.');
        }
    }
}
