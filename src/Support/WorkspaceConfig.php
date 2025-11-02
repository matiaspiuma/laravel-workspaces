<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class WorkspaceConfig
{
    public static function workspaceModel(): string
    {
        $model = config('workspaces.workspace_model');

        return static::ensureModelClass($model, 'workspace_model');
    }

    public static function invitationModel(): string
    {
        $model = config('workspaces.invitation_model');

        return static::ensureModelClass($model, 'invitation_model');
    }

    public static function userModel(): string
    {
        $model = config('workspaces.user_model')
            ?? config('auth.providers.users.model')
            ?? config('auth.providers.'.config('auth.defaults.guard').'.model');

        return static::ensureModelClass($model, 'user_model');
    }

    /**
     * @template TModel of Model
     *
     * @param class-string<TModel>|null $model
     *
     * @return class-string<TModel>
     */
    private static function ensureModelClass(?string $model, string $configKey): string
    {
        if (is_string($model) && is_subclass_of($model, Model::class)) {
            return $model;
        }

        throw new InvalidArgumentException("The configured {$configKey} must be a valid Eloquent model class.");
    }
}
