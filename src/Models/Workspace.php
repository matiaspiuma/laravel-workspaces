<?php
declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Models;

use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationAccepted;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationCreated;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationDeclined;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceMemberAdded;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceMemberRemoved;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceMemberRoleUpdated;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceOwnershipTransferred;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;
use Bhhaskin\RolesPermissions\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Workspace extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
        'meta' => 'array',
    ];

    public function getTable()
    {
        return config('workspaces.tables.workspaces', parent::getTable());
    }

    public function memberRoles(Model $user): Collection
    {
        $this->ensureUserModel($user);

        return collect(WorkspaceRoles::memberRoles($user, $this));
    }

    public function memberRole(Model $user): ?Role
    {
        $roles = $this->memberRoles($user);

        $ownerSlug = WorkspaceRoles::ownerRoleSlug();

        if ($ownerSlug) {
            $ownerRole = $roles->firstWhere('slug', $ownerSlug);

            if ($ownerRole instanceof Role) {
                return $ownerRole;
            }
        }

        return $roles->first();
    }

    public function transferOwnership(Model $newOwner): void
    {
        $this->ensureUserModel($newOwner);

        $previousOwner = $this->owner;

        if ($previousOwner instanceof Model && $previousOwner->is($newOwner)) {
            return;
        }

        $ownerRoleSlug = WorkspaceRoles::ownerRoleSlug();

        $this->owner()->associate($newOwner);
        $this->save();

        if ($ownerRoleSlug) {
            $this->addMember($newOwner, $ownerRoleSlug);
        } else {
            $this->addMember($newOwner, null);
        }

        if ($previousOwner instanceof Model && ! $previousOwner->is($newOwner)) {
            $fallbackSlug = config('workspaces.roles.owner_fallback', WorkspaceRoles::defaultRoleSlug());

            if ($fallbackSlug) {
                if ($this->isMember($previousOwner)) {
                    $this->updateMemberRole($previousOwner, $fallbackSlug);
                } else {
                    $this->addMember($previousOwner, $fallbackSlug);
                }
            }
        }

        event(new WorkspaceOwnershipTransferred($this, $previousOwner, $newOwner));
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (empty($workspace->uuid)) {
                $workspace->uuid = (string) Str::uuid();
            }

            if (empty($workspace->slug)) {
                $workspace->slug = static::generateSlug($workspace->name);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(WorkspaceConfig::userModel(), 'owner_id');
    }

    protected function membersRelation(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspaceConfig::userModel(),
            config('workspaces.tables.workspace_user')
        )
            ->withPivot(['uuid', 'last_joined_at', 'removed_at'])
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->membersRelation()->wherePivotNull('removed_at');
    }

    public function membersIncludingRemoved(): BelongsToMany
    {
        return $this->membersRelation();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceConfig::invitationModel());
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkspaceAssignment::class);
    }

    public function scopeOwnedBy(Builder $query, Model $user): Builder
    {
        return $query->where('owner_id', $user->getKey());
    }

    public function addMember(Model $user, string|Role|null $role = null): void
    {
        $this->ensureUserModel($user);

        if ($role === null && ($this->isOwner($user) || $this->owner_id === null) && ($ownerRole = WorkspaceRoles::ownerRoleSlug())) {
            $role = $ownerRole;
        }

        $roleModel = WorkspaceRoles::resolve($role);

        $this->ensureMembership($user, true);

        WorkspaceRoles::assignRoleTo($user, $this, $roleModel);

        event(new WorkspaceMemberAdded($this, $user, $roleModel));
    }

    public function removeMember(Model $user): void
    {
        $this->ensureUserModel($user);

        $record = $this->membershipRecord($user);

        if (! $record || $record->removed_at !== null) {
            return;
        }

        $this->membersIncludingRemoved()->updateExistingPivot($user->getKey(), [
            'removed_at' => CarbonImmutable::now(),
        ]);

        WorkspaceRoles::detachRolesFor($user, $this);

        event(new WorkspaceMemberRemoved($this, $user));
    }

    public function updateMemberRole(Model $user, string|Role $role): void
    {
        $this->ensureUserModel($user);

        $membership = $this->membershipRecord($user);

        if (! $membership || $membership->removed_at !== null) {
            $this->addMember($user, $role);

            return;
        }

        $roleModel = WorkspaceRoles::resolve($role);

        WorkspaceRoles::syncRoleFor($user, $this, $roleModel);

        event(new WorkspaceMemberRoleUpdated($this, $user, $roleModel));
    }

    public function isMember(Model $user): bool
    {
        $this->ensureUserModel($user);

        return $this->members()->whereKey($user->getKey())->exists();
    }

    public function isOwner(Model $user): bool
    {
        $this->ensureUserModel($user);

        return $this->owner_id === $user->getKey();
    }

    public function assignTo(Model $model): void
    {
        $this->assignments()->firstOrCreate([
            'workspaceable_type' => $model->getMorphClass(),
            'workspaceable_id' => $model->getKey(),
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    public function detachFrom(Model $model): void
    {
        $this->assignments()
            ->where('workspaceable_type', $model->getMorphClass())
            ->where('workspaceable_id', $model->getKey())
            ->delete();
    }

    public function invitationForEmail(string $email): ?Model
    {
        return $this->invitations()
            ->where('email', Str::lower($email))
            ->latest()
            ->first();
    }

    public function invite(string $email, string|Role|null $role = null, ?CarbonImmutable $expiresAt = null): Model
    {
        $normalizedEmail = Str::lower(trim($email));

        $this->invitations()->where('email', $normalizedEmail)->delete();

        $roleModel = WorkspaceRoles::resolve($role);

        $invitation = $this->invitations()->create([
            'email' => $normalizedEmail,
            'role_id' => $roleModel->getKey(),
            'token' => Str::uuid()->toString(),
            'expires_at' => $expiresAt ?? $this->defaultExpiration(),
        ]);

        event(new WorkspaceInvitationCreated($invitation));

        return $invitation;
    }

    protected function membershipTable(): string
    {
        return config('workspaces.tables.workspace_user', 'workspace_user');
    }

    protected function membershipRecord(Model $user): ?object
    {
        return DB::table($this->membershipTable())
            ->where('workspace_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->first();
    }

    protected function ensureMembership(Model $user, bool $touchLastJoined = true): void
    {
        $table = $this->membershipTable();
        $record = DB::table($table)
            ->where('workspace_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        $payload = [
            'removed_at' => null,
        ];

        if ($touchLastJoined || ! $record) {
            $payload['last_joined_at'] = CarbonImmutable::now();
        }

        if ($record) {
            $payload['uuid'] = $record->uuid ?: (string) Str::uuid();

            $this->membersIncludingRemoved()->updateExistingPivot($user->getKey(), $payload);

            return;
        }

        $this->membersIncludingRemoved()->attach($user->getKey(), array_merge([
            'uuid' => (string) Str::uuid(),
        ], $payload));
    }

    protected function ensureUserModel(Model $user): void
    {
        $expected = WorkspaceConfig::userModel();

        if (! $user instanceof $expected) {
            throw new InvalidArgumentException(sprintf(
                'Workspace member must be an instance of %s; %s given.',
                $expected,
                $user::class
            ));
        }
    }

    protected function defaultExpiration(): ?CarbonImmutable
    {
        $minutes = config('workspaces.invitation_expires_after');

        if ($minutes === null) {
            return null;
        }

        return CarbonImmutable::now()->addMinutes((int) $minutes);
    }

    protected static function generateSlug(string $name): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            $slug = Str::lower(Str::random(10));
        }

        return $slug;
    }
}
