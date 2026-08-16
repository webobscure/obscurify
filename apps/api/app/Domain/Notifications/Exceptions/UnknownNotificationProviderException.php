<?php

namespace App\Domain\Notifications\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UnknownNotificationProviderException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self(__('notifications.unknown_provider', ['code' => $code]));
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unknown_notification_provider',
        ], 422);
    }
}
