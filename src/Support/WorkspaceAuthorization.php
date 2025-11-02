<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Support;

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\RolesPermissions\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

final class WorkspaceAuthorization
{
    public static function registerGates(): void
    {
        $abilities = config('workspaces.abilities', []);

        foreach ($abilities as $ability => $definition) {
            Gate::define("workspace.{$ability}", function ($user, Workspace $workspace) use ($ability) {
                if (! $user instanceof Model) {
                    return false;
                }

                return static::allows($user, $workspace, $ability);
            });
        }
    }

    public static function allows(Model $user, Workspace $workspace, string $ability): bool
    {
        $definition = config("workspaces.abilities.{$ability}");

        if ($definition === null) {
            return false;
        }

        $definition = static::normalizeDefinition($definition);

        if (($definition['allow_owner'] ?? true) && $workspace->isOwner($user)) {
            return true;
        }

        if (($definition['require_membership'] ?? true) && ! $workspace->isMember($user)) {
            return false;
        }

        if (static::rolesSatisfied($user, $workspace, $definition['roles'])) {
            return true;
        }

        if (static::permissionsSatisfied($user, $workspace, $definition['permissions'])) {
            return true;
        }

        return false;
    }

    private static function normalizeDefinition(mixed $definition): array
    {
        if (is_string($definition)) {
            $definition = ['roles' => [$definition]];
        }

        if (! is_array($definition)) {
            $definition = [];
        }

        $definition['roles'] = $definition['roles'] ?? [];
        $definition['permissions'] = $definition['permissions'] ?? [];
        $definition['require_membership'] = $definition['require_membership'] ?? true;
        $definition['allow_owner'] = $definition['allow_owner'] ?? true;

        return $definition;
    }

    /**
     * @param string|array|null $roles
     */
    private static function rolesSatisfied(Model $user, Workspace $workspace, string|array|null $roles): bool
    {
        if ($roles === null || $roles === []) {
            return false;
        }

        if ($roles === '*') {
            return true;
        }

        $roles = Arr::wrap($roles);

        if (empty($roles)) {
            return false;
        }

        $memberRoles = collect(WorkspaceRoles::memberRoles($user, $workspace))
            ->map(fn (Role $role) => $role->slug)
            ->all();

        return collect($roles)
            ->map(fn ($slug) => WorkspaceRoles::resolve((string) $slug, createIfMissing: true)->slug)
            ->intersect($memberRoles)
            ->isNotEmpty();
    }

    /**
     * @param string|array|null $permissions
     */
    private static function permissionsSatisfied(Model $user, Workspace $workspace, string|array|null $permissions): bool
    {
        if ($permissions === null || $permissions === []) {
            return false;
        }

        if (! method_exists($user, 'hasPermission')) {
            return false;
        }

        if ($permissions === '*') {
            return true;
        }

        foreach (Arr::wrap($permissions) as $permission) {
            if (is_string($permission) && $permission !== '' && $user->hasPermission($permission, $workspace)) {
                return true;
            }
        }

        return false;
    }
}
