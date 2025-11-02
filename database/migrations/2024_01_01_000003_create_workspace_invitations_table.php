<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $rolesTable = Config::get('roles-permissions.tables.roles', 'roles');

        Schema::create(Config::get('workspaces.tables.workspace_invitations', 'workspace_invitations'), function (Blueprint $table) use ($rolesTable) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')
                ->constrained(Config::get('workspaces.tables.workspaces', 'workspaces'))
                ->cascadeOnDelete();
            $table->string('email')->index();
            $table->foreignId('role_id')->nullable()->constrained($rolesTable)->nullOnDelete();
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::get('workspaces.tables.workspace_invitations', 'workspace_invitations'));
    }
};
