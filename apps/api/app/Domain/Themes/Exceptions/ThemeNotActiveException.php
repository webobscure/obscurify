<?php

namespace App\Domain\Themes\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ThemeNotActiveException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This store has no active theme.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'error' => 'no_active_theme'], 404);
    }
}
