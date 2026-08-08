<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Application\ReleaseExpiredReservations;
use Illuminate\Console\Command;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired-reservations';

    protected $description = 'Release inventory held by expired (unpaid) checkout reservations';

    public function handle(ReleaseExpiredReservations $action): int
    {
        $released = $action->handle();

        $this->info("Released {$released} expired reservation(s).");

        return self::SUCCESS;
    }
}
