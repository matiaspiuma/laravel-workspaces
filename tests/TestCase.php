<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Tests;

use Bhhaskin\LaravelWorkspaces\LaravelWorkspacesServiceProvider;
use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;
use Bhhaskin\RolesPermissions\RolesPermissionsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            RolesPermissionsServiceProvider::class,
            LaravelWorkspacesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('auth.providers.users.model', \Bhhaskin\LaravelWorkspaces\Tests\Fixtures\User::class);
        $app['config']->set('workspaces.user_model', \Bhhaskin\LaravelWorkspaces\Tests\Fixtures\User::class);

        $app['config']->set('roles-permissions.object_permissions.enabled', true);
        $app['config']->set('roles-permissions.role_scopes.workspace', Workspace::class);

        $app['config']->set('workspaces.roles.auto_create', array_merge(
            $app['config']->get('workspaces.roles.auto_create', []),
            [
                'workspace-editor' => [
                    'name' => 'Workspace Editor',
                    'description' => 'Can edit workspace content.',
                ],
                'workspace-viewer' => [
                    'name' => 'Workspace Viewer',
                    'description' => 'Read-only access to workspace content.',
                ],
                'workspace-contributor' => [
                    'name' => 'Workspace Contributor',
                    'description' => 'Can contribute content within the workspace.',
                ],
            ]
        ));

        $app['config']->set('workspaces.roles.owner_fallback', 'workspace-member');

        $app['config']->set('workspaces.abilities', [
            'view' => [
                'roles' => '*',
            ],
            'manage-members' => [
                'roles' => ['workspace-owner'],
            ],
            'manage-invitations' => [
                'roles' => ['workspace-owner'],
            ],
            'transfer-ownership' => [
                'roles' => ['workspace-owner'],
            ],
            'edit-content' => [
                'roles' => ['workspace-editor'],
            ],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/bhhaskin/laravel-roles-permissions/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->artisan('migrate', ['--database' => 'testbench'])->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        WorkspaceRoles::ensureDefaultRoles();
    }
}
