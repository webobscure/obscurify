<?php

namespace App\Shared\Tenancy\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class TenantAccessDeniedException extends RuntimeException
{
    public static function forStore(string $storeId): self
    {
        return new self('You do not have access to this store.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'tenant_access_denied',
        ], 403);
    }
}
