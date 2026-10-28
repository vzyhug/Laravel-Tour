<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DepartureCancelled
{
    use Dispatchable;

    public function __construct(
        public int $departureId,
        public array $bookingIds,
        public string $reason,
    ) {}
}
