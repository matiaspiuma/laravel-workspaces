<?php
declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Traits;

use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;
use Bhhaskin\RolesPermissions\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Model
 */
trait HasWorkspaces
{
    protected function workspacesRelation(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspaceConfig::workspaceModel(),
            config('workspaces.tables.workspace_user')
        )
            ->withPivot(['uuid', 'last_joined_at', 'removed_at'])
            ->withTimestamps();
    }

    public function workspaces(): BelongsToMany
    {
        return $this->workspacesRelation()->wherePivotNull('removed_at');
    }

    public function workspacesIncludingRemoved(): BelongsToMany
    {
        return $this->workspacesRelation();
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(
            WorkspaceConfig::workspaceModel(),
            'owner_id'
        );
    }

    public function hasWorkspace(Model $workspace): bool
    {
        $expected = WorkspaceConfig::workspaceModel();

        if (! $workspace instanceof $expected) {
            return false;
        }

        return $this->workspaces()->whereKey($workspace->getKey())->exists();
    }

    public function workspaceRole(Model $workspace): ?Role
    {
        $roles = WorkspaceRoles::memberRoles($this, $workspace);

        return $roles[0] ?? null;
    }
}
