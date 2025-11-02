<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Models;

use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class WorkspaceAssignment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uuid' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $assignment) {
            if (empty($assignment->uuid)) {
                $assignment->uuid = (string) Str::uuid();
            }
        });
    }

    public function getTable()
    {
        return config('workspaces.tables.workspaceables', parent::getTable());
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(WorkspaceConfig::workspaceModel());
    }

    public function workspaceable(): MorphTo
    {
        return $this->morphTo();
    }
}
