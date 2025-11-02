<?php
declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Traits;

use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin Model
 */
trait Workspaceable
{
    public function workspaces(): MorphToMany
    {
        return $this->morphToMany(
            WorkspaceConfig::workspaceModel(),
            'workspaceable',
            config('workspaces.tables.workspaceables')
        )
            ->withPivot(['uuid'])
            ->withTimestamps();
    }

    public function attachToWorkspace(Model $workspace): void
    {
        $expected = WorkspaceConfig::workspaceModel();

        if (! $workspace instanceof $expected) {
            throw new \InvalidArgumentException(sprintf(
                'Workspace must be an instance of %s; %s given.',
                $expected,
                $workspace::class
            ));
        }

        $workspace->assignTo($this);
    }

    public function detachFromWorkspace(Model $workspace): void
    {
        $expected = WorkspaceConfig::workspaceModel();

        if (! $workspace instanceof $expected) {
            return;
        }

        $workspace->detachFrom($this);
    }
}
