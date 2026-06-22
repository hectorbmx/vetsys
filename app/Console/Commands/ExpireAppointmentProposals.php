<?php

namespace App\Console\Commands;

use App\Services\AppointmentService;
use Illuminate\Console\Command;

class ExpireAppointmentProposals extends Command
{
    protected $signature = 'appointments:expire-proposals {--limit=500 : Maximo de propuestas por ejecucion}';

    protected $description = 'Expira contrapropuestas vencidas y restaura el estado anterior de sus citas.';

    public function handle(AppointmentService $appointments): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $expired = $appointments->expireDueProposals(limit: $limit);
        $this->info("Propuestas expiradas: {$expired}.");

        return self::SUCCESS;
    }
}
