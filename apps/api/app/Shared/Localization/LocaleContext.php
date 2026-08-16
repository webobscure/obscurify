<?php

namespace App\Shared\Localization;

use Illuminate\Support\Facades\App;

/**
 * Holds the active locale for the current request/job lifecycle —
 * mirrors TenantContext exactly, but never "missing": current() always
 * returns a real locale code, falling back to config('app.locale') (the
 * platform default, ru) when nothing has been resolved yet. Setting a
 * locale also drives Laravel's own App::setLocale() immediately, so
 * every `__()` call, validator, and Carbon date format reflects it
 * without callers needing to call both.
 *
 * Bound as a singleton in the container — same PHP-FPM/Octane/queue-worker
 * reasoning as TenantContext's own docblock.
 */
final class LocaleContext
{
    private ?string $locale = null;

    public function set(string $locale): void
    {
        $this->locale = $locale;
        App::setLocale($locale);
    }

    public function clear(): void
    {
        $this->locale = null;
        App::setLocale(config('app.locale'));
    }

    public function current(): string
    {
        return $this->locale ?? App::getLocale();
    }

    /**
     * Run a callback with the given locale active, restoring whatever
     * locale was previously active afterwards. Intended for queue jobs
     * (notification/email delivery) that must render content in a
     * specific recipient's locale outside the HTTP request lifecycle.
     */
    public function scope(string $locale, callable $callback): mixed
    {
        $previous = $this->locale;
        $previousAppLocale = App::getLocale();
        $this->set($locale);

        try {
            return $callback();
        } finally {
            $this->locale = $previous;
            App::setLocale($previousAppLocale);
        }
    }
}
