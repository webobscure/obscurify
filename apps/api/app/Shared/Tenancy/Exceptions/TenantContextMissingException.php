<?php

namespace App\Shared\Tenancy\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class TenantContextMissingException extends RuntimeException
{
    public static function noActiveStore(): self
    {
        return new self('No active store. Select a store before performing this operation.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'tenant_context_missing',
        ], 428);
    }
}
