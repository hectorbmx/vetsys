<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CustomerStatementGenerator;
use Illuminate\Console\Command;

class GenerateCustomerStatements extends Command
{
    protected $signature = 'statements:generate-monthly {--all : Genera para todos los clientes con estados activos sin revisar si hoy es dia de corte}';

    protected $description = 'Genera estados de cuenta guardados para clientes con corte contable vigente.';

    public function handle(CustomerStatementGenerator $generator): int
    {
        $generated = 0;
        $skipped = 0;

        Customer::with(['tenant', 'accountSetting'])
            ->whereHas('accountSetting', fn ($query) => $query->where('is_statement_enabled', true))
            ->chunkById(100, function ($customers) use ($generator, &$generated, &$skipped) {
                foreach ($customers as $customer) {
                    if (!$this->option('all') && !$generator->shouldGenerateToday($customer)) {
                        $skipped++;
                        continue;
                    }

                    $generator->generateStored($customer);
                    $generated++;
                }
            });

        $this->info("Estados generados: {$generated}. Omitidos: {$skipped}.");

        return self::SUCCESS;
    }
}
