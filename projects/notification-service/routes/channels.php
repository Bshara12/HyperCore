<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('notifications.{projectId}.{recipientType}.{recipientId}', function ($user, string $projectId, string $recipientType, string $recipientId) {
    $requestProject = request()->attributes->get('project', []);
    $requestUser = request()->attributes->get('auth_user', []);

    if ((int) data_get($requestProject, 'id') !== $projectId) {
        return false;
    }

    if ($recipientType !== 'user') {
        return false;
    }

    return (string) data_get($requestUser, 'id') === (string) $recipientId;
});
