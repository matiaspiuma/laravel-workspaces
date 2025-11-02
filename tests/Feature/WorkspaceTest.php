<?php

declare(strict_types=1);

use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationAccepted;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationCreated;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceInvitationDeclined;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceMemberAdded;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceMemberRemoved;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceMemberRoleUpdated;
use Bhhaskin\LaravelWorkspaces\Events\WorkspaceOwnershipTransferred;
use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;
use Bhhaskin\LaravelWorkspaces\Tests\Fixtures\Page;
use Bhhaskin\LaravelWorkspaces\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

it('generates identifiers and assigns the owner role', function () {
    $owner = createUser();

    $workspace = Workspace::create([
        'name' => 'Product Team',
        'owner_id' => $owner->id,
    ]);

    expect(Str::isUuid($workspace->uuid))->toBeTrue();
    expect($workspace->slug)->not->toBeEmpty();

    $workspace->addMember($owner);

    $membership = $workspace->members()->whereKey($owner->getKey())->firstOrFail()->pivot;

    expect(Str::isUuid($membership->uuid))->toBeTrue();
    expect($membership->last_joined_at)->not()->toBeNull();

    $role = $workspace->memberRole($owner);

    expect($role)->not()->toBeNull();
    expect($role?->slug)->toBe(WorkspaceRoles::ownerRoleSlug());
    expect($owner->hasRole($role, $workspace))->toBeTrue();
});

it('allows a user to join multiple workspaces with different roles', function () {
    $owner = createUser(['name' => 'Owner']);
    $member = createUser(['name' => 'Teammate']);

    $workspaceA = Workspace::create([
        'name' => 'Workspace A',
        'owner_id' => $owner->id,
    ]);

    $workspaceB = Workspace::create([
        'name' => 'Workspace B',
        'owner_id' => $owner->id,
    ]);

    $editorRole = WorkspaceRoles::findOrCreateRole('workspace-editor');
    $viewerRole = WorkspaceRoles::findOrCreateRole('workspace-viewer');

    $workspaceA->addMember($member, $editorRole);
    $workspaceB->addMember($member, $viewerRole);

    $member->refresh();

    expect($member->workspaces)->toHaveCount(2);
    expect($member->hasWorkspace($workspaceA))->toBeTrue();
    expect($member->hasWorkspace($workspaceB))->toBeTrue();

    $uuids = $member->workspaces->pluck('pivot.uuid');
    expect($uuids->every(fn ($uuid) => Str::isUuid($uuid)))->toBeTrue();

    expect($member->hasRole($editorRole, $workspaceA))->toBeTrue();
    expect($member->hasRole($viewerRole, $workspaceB))->toBeTrue();
    expect($workspaceA->memberRole($member)?->is($editorRole))->toBeTrue();
    expect($workspaceB->memberRole($member)?->is($viewerRole))->toBeTrue();
});

it('accepts invitations and assigns the requested role', function () {
    $owner = createUser(['name' => 'Owner']);
    $invitee = createUser(['email' => 'invitee@example.com']);

    $workspace = Workspace::create([
        'name' => 'Workspace Invitations',
        'owner_id' => $owner->id,
    ]);

    $contributorRole = WorkspaceRoles::findOrCreateRole('workspace-contributor');

    $invitation = $workspace->invite($invitee->email, role: $contributorRole);

    expect(Str::isUuid($invitation->uuid))->toBeTrue();
    expect(Str::isUuid($invitation->token))->toBeTrue();
    expect($invitation->role->is($contributorRole))->toBeTrue();

    $invitation->accept($invitee);
    $invitation->refresh();

    expect($invitation->accepted_at)->not()->toBeNull();
    expect($invitation->declined_at)->toBeNull();
    expect($workspace->isMember($invitee))->toBeTrue();

    $memberRole = $workspace->memberRole($invitee);

    expect($memberRole)->not()->toBeNull();
    expect($memberRole?->is($contributorRole))->toBeTrue();
});

it('records workspace assignments with uuids', function () {
    $owner = createUser(['name' => 'Owner']);
    $workspace = Workspace::create([
        'name' => 'Workspace Attachments',
        'owner_id' => $owner->id,
    ]);

    $page = Page::create(['title' => 'First Page']);

    $workspace->assignTo($page);

    $assignment = $workspace->assignments()->first();

    expect($assignment)->not()->toBeNull();
    expect(Str::isUuid($assignment->uuid))->toBeTrue();
    expect($assignment->workspaceable_id)->toBe($page->id);
    expect($assignment->workspaceable_type)->toBe($page->getMorphClass());

    $page->refresh();

    expect($page->workspaces->contains(fn ($attached) => $attached->is($workspace)))->toBeTrue();
    expect(Str::isUuid($page->workspaces->first()->pivot->uuid))->toBeTrue();
});

it('removes members and detaches workspace roles', function () {
    $owner = createUser(['name' => 'Owner']);
    $member = createUser(['name' => 'Member']);

    $workspace = Workspace::create([
        'name' => 'Workspace Removal',
        'owner_id' => $owner->id,
    ]);

    $role = WorkspaceRoles::findOrCreateRole('workspace-editor');

    $workspace->addMember($member, $role);

    expect($workspace->isMember($member))->toBeTrue();
    expect($member->hasRole($role, $workspace))->toBeTrue();

    $workspace->removeMember($member);

    expect($workspace->isMember($member))->toBeFalse();
    expect($member->hasRole($role, $workspace))->toBeFalse();

    $record = DB::table(config('workspaces.tables.workspace_user'))
        ->where('workspace_id', $workspace->id)
        ->where('user_id', $member->id)
        ->first();

    expect($record)->not()->toBeNull();
    expect($record->removed_at)->not()->toBeNull();
});

it('marks invitations as declined without adding membership', function () {
    $owner = createUser(['name' => 'Owner']);
    $workspace = Workspace::create([
        'name' => 'Workspace Declines',
        'owner_id' => $owner->id,
    ]);

    $inviteEmail = 'declined@example.com';

    $invitation = $workspace->invite($inviteEmail);

    expect($invitation->accepted_at)->toBeNull();
    expect($invitation->declined_at)->toBeNull();

    $invitation->decline();
    $invitation->refresh();

    expect($invitation->accepted_at)->toBeNull();
    expect($invitation->declined_at)->not()->toBeNull();
    expect($workspace->members()->where('users.email', $inviteEmail)->exists())->toBeFalse();
});

it('assigns default role when invitation omits explicit role', function () {
    $owner = createUser(['name' => 'Owner']);
    $invitee = createUser(['email' => 'default-role@example.com']);

    $workspace = Workspace::create([
        'name' => 'Workspace Defaults',
        'owner_id' => $owner->id,
    ]);

    $defaultSlug = WorkspaceRoles::defaultRoleSlug();
    $defaultRole = WorkspaceRoles::findOrCreateRole($defaultSlug);

    $invitation = $workspace->invite($invitee->email);

    $invitation->accept($invitee);

    $role = $workspace->memberRole($invitee);

    expect($role)->not()->toBeNull();
    expect($role?->slug)->toBe($defaultRole->slug);
    expect($invitee->hasRole($defaultRole, $workspace))->toBeTrue();
});

it('dispatches membership lifecycle events', function () {
    Event::fake([
        WorkspaceMemberAdded::class,
        WorkspaceMemberRoleUpdated::class,
        WorkspaceMemberRemoved::class,
    ]);

    $owner = createUser(['name' => 'Owner']);
    $member = createUser(['name' => 'Member']);

    $workspace = Workspace::create([
        'name' => 'Workspace Events',
        'owner_id' => $owner->id,
    ]);

    $workspace->addMember($member, 'workspace-editor');
    $workspace->updateMemberRole($member, 'workspace-viewer');
    $workspace->removeMember($member);

    Event::assertDispatched(WorkspaceMemberAdded::class);
    Event::assertDispatched(WorkspaceMemberRoleUpdated::class);
    Event::assertDispatched(WorkspaceMemberRemoved::class);
});

it('dispatches invitation events for lifecycle actions', function () {
    Event::fake([
        WorkspaceInvitationCreated::class,
        WorkspaceInvitationAccepted::class,
        WorkspaceInvitationDeclined::class,
    ]);

    $owner = createUser(['name' => 'Owner']);
    $invitee = createUser(['email' => 'event-invitee@example.com']);

    $workspace = Workspace::create([
        'name' => 'Workspace Invitation Events',
        'owner_id' => $owner->id,
    ]);

    $invitation = $workspace->invite($invitee->email);
    $invitation->accept($invitee);
    $declined = $workspace->invite('decline-events@example.com');
    $declined->decline();

    Event::assertDispatched(WorkspaceInvitationCreated::class, 2);
    Event::assertDispatched(WorkspaceInvitationAccepted::class);
    Event::assertDispatched(WorkspaceInvitationDeclined::class);
});

it('transfers ownership and updates roles', function () {
    Event::fake([
        WorkspaceOwnershipTransferred::class,
    ]);

    $owner = createUser(['name' => 'Initial Owner']);
    $newOwner = createUser(['name' => 'New Owner']);

    $workspace = Workspace::create([
        'name' => 'Ownership Transfers',
        'owner_id' => $owner->id,
    ]);

    $workspace->addMember($owner);
    $workspace->addMember($newOwner, 'workspace-editor');

    $workspace->transferOwnership($newOwner);
    $workspace->refresh();

    expect($workspace->owner_id)->toBe($newOwner->id);

    $ownerRole = $workspace->memberRole($newOwner);
    expect($ownerRole)->not()->toBeNull();
    expect($ownerRole?->slug)->toBe(WorkspaceRoles::ownerRoleSlug());

    $fallbackRole = $workspace->memberRole($owner);
    expect($fallbackRole)->not()->toBeNull();
    expect($fallbackRole?->slug)->toBe(WorkspaceRoles::defaultRoleSlug());

    Event::assertDispatched(WorkspaceOwnershipTransferred::class);
});

it('evaluates workspace ability gates against configured roles', function () {
    $owner = createUser(['name' => 'Owner']);
    $editor = createUser(['name' => 'Editor']);
    $viewer = createUser(['name' => 'Viewer']);

    $workspace = Workspace::create([
        'name' => 'Workspace Gate Checks',
        'owner_id' => $owner->id,
    ]);

    $workspace->addMember($owner);
    $workspace->addMember($editor, 'workspace-editor');
    $workspace->addMember($viewer, 'workspace-viewer');

    expect(Gate::forUser($owner)->allows('workspace.manage-members', $workspace))->toBeTrue();
    expect(Gate::forUser($editor)->allows('workspace.manage-members', $workspace))->toBeFalse();
    expect(Gate::forUser($viewer)->allows('workspace.manage-members', $workspace))->toBeFalse();

    expect(Gate::forUser($editor)->allows('workspace.edit-content', $workspace))->toBeTrue();
    expect(Gate::forUser($viewer)->allows('workspace.edit-content', $workspace))->toBeFalse();
});

function createUser(array $attributes = []): User
{
    static $increment = 1;

    $defaults = [
        'name' => sprintf('User %d', $increment),
        'email' => $attributes['email'] ?? sprintf('user-%d@example.com', $increment),
        'password' => Hash::make('password'),
    ];

    $increment++;

    return User::create(array_merge($defaults, $attributes));
}
