<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(Config::get('workspaces.tables.workspaceables', 'workspaceables'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')
                ->constrained(Config::get('workspaces.tables.workspaces', 'workspaces'))
                ->cascadeOnDelete();
            $table->morphs('workspaceable');
            $table->timestamps();

            $table->unique([
                'workspace_id',
                'workspaceable_type',
                'workspaceable_id',
            ], 'workspaceable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::get('workspaces.tables.workspaceables', 'workspaceables'));
    }
};
