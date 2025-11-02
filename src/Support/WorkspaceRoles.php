<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Support;

use Bhhaskin\RolesPermissions\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

final class WorkspaceRoles
{
    public static function defaultRoleSlug(): string
    {
        return (string) config('workspaces.roles.default', 'workspace-member');
    }

    public static function ownerRoleSlug(): ?string
    {
        $slug = config('workspaces.roles.owner');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public static function resolve(string|Role|null $role = null, bool $createIfMissing = true): Role
    {
        if ($role instanceof Role) {
            return static::ensureRoleMatchesScope($role);
        }

        $slug = $role ?? static::defaultRoleSlug();

        if (! is_string($slug) || $slug === '') {
            throw new RuntimeException('Workspace roles must be referenced by a non-empty slug.');
        }

        $slug = static::normalizeSlug($slug);

        return static::findOrCreateRole($slug, $createIfMissing);
    }

    public static function findOrCreateRole(string $slug, bool $createIfMissing = true): Role
    {
        $slug = static::normalizeSlug($slug);

        $roleClass = static::roleModel();
        $scope = static::scope();

        /** @var Role|null $existing */
        $existing = $roleClass::query()
            ->where('slug', $slug)
            ->when($scope !== null, fn ($query) => $query->where('scope', $scope))
            ->when($scope === null, fn ($query) => $query->whereNull('scope'))
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $createIfMissing) {
            throw new RuntimeException("Workspace role [{$slug}] does not exist.");
        }

        $definition = static::autoCreateDefinition($slug);

        /** @var Role $role */
        $role = $roleClass::query()->create([
            'name' => $definition['name'] ?? Str::headline($slug),
            'slug' => $slug,
            'description' => $definition['description'] ?? null,
            'scope' => $scope,
        ]);

        return $role;
    }

    public static function detachRolesFor(Model $user, Model $workspace): void
    {
        if (! method_exists($user, 'roles')) {
            return;
        }

        $roles = collect(static::memberRoles($user, $workspace));

        if ($roles->isEmpty()) {
            return;
        }

        $relation = $user->roles();

        $roleIds = $roles->map(static fn (Role $role) => $role->getKey())->all();

        if (static::objectPermissionsEnabled() && method_exists($relation, 'wherePivot')) {
            [$typeColumn, $idColumn] = static::objectColumns();

            $relation->wherePivot($typeColumn, $workspace->getMorphClass())
                ->wherePivot($idColumn, $workspace->getKey())
                ->detach();

            return;
        }

        $relation->detach($roleIds);
    }

    public static function assignRoleTo(Model $user, Model $workspace, Role $role): void
    {
        static::guardRolesTrait($user);
        static::guardObjectPermissionsEnabled($workspace);

        $user->assignRole($role, $workspace);
    }

    public static function syncRoleFor(Model $user, Model $workspace, Role $role): void
    {
        static::guardRolesTrait($user);
        static::guardObjectPermissionsEnabled($workspace);

        $user->syncRoles($role, $workspace);
    }

    public static function hasRole(Model $user, Model $workspace, Role $role): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return (bool) $user->hasRole($role, $workspace);
    }

    public static function memberRoles(Model $user, Model $workspace): array
    {
        if (! method_exists($user, 'roles')) {
            return [];
        }

        if (! static::objectPermissionsEnabled()) {
            $slugs = static::managedRoleSlugs();

            return $user->roles()
                ->whereIn('slug', $slugs)
                ->get()
                ->all();
        }

        [$typeColumn, $idColumn] = static::objectColumns();

        $roles = $user->roles()
            ->wherePivot($typeColumn, $workspace->getMorphClass())
            ->wherePivot($idColumn, $workspace->getKey())
            ->get();

        return $roles->all();
    }

    public static function scope(): ?string
    {
        $scope = config('workspaces.roles.scope');

        if ($scope === null) {
            return null;
        }

        return is_string($scope) && $scope !== '' ? $scope : null;
    }

    /**
     * @return class-string<Role>
     */
    public static function roleModel(): string
    {
        /** @var class-string<Role>|null $model */
        $model = config('roles-permissions.models.role', Role::class);

        if (! is_string($model) || ! is_subclass_of($model, Role::class)) {
            return Role::class;
        }

        return $model;
    }

    private static function autoCreateDefinition(string $slug): array
    {
        $definitions = (array) config('workspaces.roles.auto_create', []);

        return Arr::get($definitions, $slug, []);
    }

    public static function ensureDefaultRoles(): void
    {
        if (! static::databaseReady()) {
            return;
        }

        $definitions = array_keys((array) config('workspaces.roles.auto_create', []));
        $slugs = array_unique(array_filter([
            static::defaultRoleSlug(),
            static::ownerRoleSlug(),
            static::ownerFallbackRoleSlug(),
            ...$definitions,
        ]));

        foreach ($slugs as $slug) {
            if (is_string($slug) && $slug !== '') {
                static::findOrCreateRole($slug);
            }
        }
    }

    private static function databaseReady(): bool
    {
        $roleModel = static::roleModel();
        $table = (new $roleModel())->getTable();

        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function normalizeSlug(string $slug): string
    {
        $aliasMap = (array) config('workspaces.roles.aliases', []);
        $normalized = Str::lower($slug);

        foreach ($aliasMap as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (Str::lower($key) === $normalized) {
                return is_string($value) && $value !== '' ? $value : $slug;
            }
        }

        return $slug;
    }

    public static function ownerFallbackRoleSlug(): ?string
    {
        $slug = config('workspaces.roles.owner_fallback');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return static::normalizeSlug($slug);
    }

    private static function managedRoleSlugs(): array
    {
        $slugs = array_filter([
            static::defaultRoleSlug(),
            static::ownerRoleSlug(),
            static::ownerFallbackRoleSlug(),
        ]);

        $auto = array_keys((array) config('workspaces.roles.auto_create', []));
        $slugs = array_merge($slugs, $auto);

        $slugList = array_map(fn ($slug) => static::normalizeSlug((string) $slug), $slugs);

        return array_values(array_unique(array_filter($slugList)));
    }

    private static function ensureRoleMatchesScope(Role $role): Role
    {
        $scope = static::scope();

        if ($scope === null && $role->scope !== null) {
            throw new RuntimeException('Provided role is scoped and cannot be used for workspace assignments.');
        }

        if ($scope !== null && $role->scope !== $scope) {
            throw new RuntimeException(sprintf(
                'Provided role [%s] has scope [%s] but expected [%s].',
                $role->slug,
                $role->scope ?? 'null',
                $scope
            ));
        }

        return $role;
    }

    private static function guardRolesTrait(Model $user): void
    {
        if (! method_exists($user, 'assignRole') || ! method_exists($user, 'syncRoles')) {
            throw new RuntimeException('Workspace members must use the HasRoles trait from bhhaskin/laravel-roles-permissions.');
        }
    }

    private static function guardObjectPermissionsEnabled(Model $workspace, bool $throwIfDisabled = true): void
    {
        if (static::objectPermissionsEnabled()) {
            return;
        }

        if ($throwIfDisabled) {
            throw new RuntimeException('Workspace role assignments require object-level permissions to be enabled in the roles-permissions configuration.');
        }
    }

    private static function objectPermissionsEnabled(): bool
    {
        return (bool) config('roles-permissions.object_permissions.enabled', false);
    }

    private static function objectColumns(): array
    {
        $config = config('roles-permissions.object_permissions.columns', []);

        return [
            $config['type'] ?? 'model_type',
            $config['id'] ?? 'model_id',
        ];
    }
}
