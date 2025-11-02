<?php

declare(strict_types=1);

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Models\WorkspaceInvitation;

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | By default the package will attempt to resolve the user model from
    | your authentication provider configuration. You can override it here
    | if you are using a custom guard or non-standard setup.
    |
    */
    'user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Workspace Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used to represent a workspace. You may
    | replace it with your own implementation as long as it extends the
    | base Workspace model that ships with this package.
    |
    */
    'workspace_model' => Workspace::class,

    /*
    |--------------------------------------------------------------------------
    | Workspace Invitation Model
    |--------------------------------------------------------------------------
    |
    | This is the model that handles invitations to workspaces. You can swap
    | it out for a custom implementation if you need to hook into more
    | elaborate flows like sending notifications or auditing events.
    |
    */
    'invitation_model' => WorkspaceInvitation::class,

    /*
    |--------------------------------------------------------------------------
    | Roles Integration
    |--------------------------------------------------------------------------
    |
    | Configure how workspace roles integrate with the roles-permissions
    | package. Roles are resolved by slug within the provided scope. Entries
    | listed under "auto_create" will be created automatically if missing.
    |
    */
    'roles' => [
        'scope' => 'workspace',

        'default' => 'workspace-member',

        'owner' => 'workspace-owner',

        'owner_fallback' => 'workspace-member',

        'aliases' => [
            'owner' => 'workspace-owner',
            'member' => 'workspace-member',
            'default' => 'workspace-member',
        ],

        'auto_create' => [
            'workspace-owner' => [
                'name' => 'Workspace Owner',
                'description' => 'Full control over workspace membership and settings.',
            ],
            'workspace-member' => [
                'name' => 'Workspace Member',
                'description' => 'Standard workspace member with limited privileges.',
            ],
            'workspace-editor' => [
                'name' => 'Workspace Editor',
                'description' => 'Can edit workspace content and resources.',
            ],
            'workspace-viewer' => [
                'name' => 'Workspace Viewer',
                'description' => 'Read-only access to workspace content.',
            ],
            'workspace-contributor' => [
                'name' => 'Workspace Contributor',
                'description' => 'Can contribute content and collaborate within the workspace.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Abilities
    |--------------------------------------------------------------------------
    |
    | Map workspace-level abilities to the roles or permissions that grant
    | access. Gates are registered automatically using the
    | "workspace.{ability}" naming convention.
    |
    */
    'abilities' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitation Expiration
    |--------------------------------------------------------------------------
    |
    | Invitations will be considered expired after this many minutes. Set to
    | null to disable expiration entirely.
    |
    */
    'invitation_expires_after' => 10080, // 7 days

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | You can override the table names used by the package if necessary.
    |
    */
    'tables' => [
        'workspaces' => 'workspaces',
        'workspace_user' => 'workspace_user',
        'workspace_invitations' => 'workspace_invitations',
        'workspaceables' => 'workspaceables',
        'users' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Config Publish Path
    |--------------------------------------------------------------------------
    |
    | This value determines the default location where the config file will be
    | published in the host application. You normally do not need to change
    | this, but it can be useful if you maintain a custom config setup.
    |
    */
    'config_path' => null,
];
