<?php

namespace App\Domain\Shipping\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class OvershipmentException extends RuntimeException
{
    public static function forOrderItem(string $orderItemId): self
    {
        return new self("Cannot ship more of order item \"{$orderItemId}\" than was ordered.");
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'overshipment',
        ], 422);
    }
}
