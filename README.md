# Laravel Workspaces

Reusable workspace (team) management for your Laravel applications.

This package adds models, migrations, and traits that help you:

- Create workspaces that belong to any user (users can own many workspaces).
- Invite additional users into a workspace using secure tokens.
- Manage workspace member roles through [`bhhaskin/laravel-roles-permissions`](https://github.com/bhhaskin/laravel-roles-permissions).
- Attach workspaces to any other model in your application (pages, posts, etc.).
- Transfer ownership, remove members, and keep a soft-delete history of memberships and invitations.
- Reference every workspace, membership, invitation, and assignment via stable UUIDs for frontend APIs.

## Installation

```bash
composer require bhhaskin/laravel-workspaces
```

This package depends on `bhhaskin/laravel-roles-permissions`. Make sure to publish both sets of configuration and migrations, then run your migrations:

```bash
php artisan vendor:publish --tag=workspaces-config
php artisan vendor:publish --tag=workspaces-migrations
php artisan vendor:publish --tag=laravel-roles-permissions-config
php artisan vendor:publish --tag=laravel-roles-permissions-migrations
php artisan migrate
```

Enable object-level permissions so role assignments can be scoped to a workspace. In `config/roles-permissions.php`:

```php
'object_permissions' => [
    'enabled' => true,
],

'role_scopes' => [
    'workspace' => \Bhhaskin\LaravelWorkspaces\Models\Workspace::class,
],
```

## Setup

Update your `User` model to use the `HasWorkspaces` trait:

```php
use Bhhaskin\LaravelWorkspaces\Traits\HasWorkspaces;
use Bhhaskin\RolesPermissions\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use HasWorkspaces;
}

// Users can join or own multiple workspaces
$user->workspaces;       // memberships (pivot exposes UUID, role, timestamps)
$user->ownedWorkspaces;  // workspaces where the user is the owner
```

Any model that should be linked to a workspace can use the `Workspaceable` trait:

```php
use Bhhaskin\LaravelWorkspaces\Traits\Workspaceable;

class Page extends Model
{
    use Workspaceable;
}
```

## Usage

### Creating a workspace

```php
use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;

/** @var Workspace $workspace */
$workspace = Workspace::create([
    'name' => 'Product Team',
    'owner_id' => $owner->id,
]);

// Owner role is assigned automatically when the owner joins.
$workspace->addMember($owner);

// Assign additional roles using slugs (aliases like "owner" and "member" are supported)
$editorRole = WorkspaceRoles::findOrCreateRole('workspace-editor');
$workspace->addMember($teammate, $editorRole);
```

### Inviting members

```php
$contributorRole = WorkspaceRoles::findOrCreateRole('workspace-contributor');
$invitation = $workspace->invite('teammate@example.com', role: $contributorRole);

// Later, after the user accepts the invite:
$invitation->accept($user);

// Use UUIDs when sending invitation links:
$invitation->uuid; // e.g. expose in APIs
```

Invitations automatically expire after seven days (configurable) and may only be accepted by the intended email address.

You can inspect roles for a workspace member:

```php
$role = $workspace->memberRole($user); // Returns a Role instance (or null)
$user->hasRole($role, $workspace);     // Provided by laravel-roles-permissions
```

### Removing members or leaving a workspace

```php
$workspace->removeMember($user); // Soft deletes the membership and detaches workspace-scoped roles

if (! $workspace->isMember($user)) {
    // The user successfully left the workspace
}
```

Historical membership entries remain in the pivot table with a `removed_at` timestamp so you can audit previous collaborators.

### Transferring ownership

```php
$workspace->transferOwnership($newOwner);

// Ownership role automatically moves across and the old owner is demoted to the configured fallback role.
```

### Mapping workspaces to other models

```php
$page->attachToWorkspace($workspace);

$workspace->assignTo($page); // Equivalent helper on the workspace model

// Both models now have UUID-backed relationships:
$workspace->uuid;
$page->workspaces->first()->pivot->uuid;
```

Models gain a `workspaces()` relation, and workspaces track attached models through the `assignments()` relation.

### Authorization

Workspace abilities are mapped to roles (or permissions) through the `workspaces.abilities` config array. Gates are registered using the `workspace.{ability}` convention:

```php
if (Gate::forUser($user)->allows('workspace.manage-members', $workspace)) {
    // $user can manage members in this workspace
}

Gate::authorize('workspace.edit-content', $workspace);
```

Roles listed under `workspaces.roles.auto_create` are ensured during boot, and you can extend the ability map to fit your domain.

### Events

The package dispatches events as part of the workspace lifecycle so you can hook into notifications or analytics:

- `WorkspaceMemberAdded`
- `WorkspaceMemberRemoved`
- `WorkspaceMemberRoleUpdated`
- `WorkspaceInvitationCreated`
- `WorkspaceInvitationAccepted`
- `WorkspaceInvitationDeclined`
- `WorkspaceOwnershipTransferred`

Listen to these events to trigger custom logic (emails, audits, etc.).

## Configuration

The published `config/workspaces.php` file lets you adjust:

- Custom user/workspace/invitation model classes.
- Table names.
- Role scope, aliases, owner fallback behaviour, and which roles should be auto-created for workspaces.
- Ability-to-role mappings (registered automatically as `workspace.*` gates).
- Invitation expiration window.

## Testing & Extending

The package ships with simple events and helpers designed to be extended. Replace the provided models by pointing to your own implementation in the config file and override any behaviour you need.

Run the automated test suite (powered by Pest and Orchestra Testbench):

```bash
composer test
```
