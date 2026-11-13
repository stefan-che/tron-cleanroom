<?php

/**
 * Energy services test
 *
 * Usage examples:
 * php artisan test:energy
 * php artisan test:energy --service=refee
 * php artisan test:energy --balance
 * php artisan test:energy --service=netts.io --balance
 * php artisan test:energy --service=tr.energy --balance
 *
 * php artisan test:energy --service=netts.io --rent --address=TEXAMPLEADDRESS0000000000000000003 --amount=32000
 * php artisan test:energy --rent --address=TEXAMPLEADDRESS0000000000000000003 --amount=60000
 * php artisan test:energy --cost --address=TEXAMPLEADDRESS0000000000000000003
 */

namespace App\Console\Commands;

use App\Models\Energy\EnergyService;
use App\Services\Energy\EnergyServiceDispatcher;
use Illuminate\Console\Command;

class TestEnergyServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:energy
                            {--service= : Service code (refee, tr.energy, netts.io) or all}
                            {--balance : Get the service balance}
                            {--rent : Rent energy}
                            {--cost : Get the energy cost for an address}
                            {--address= : TRON wallet address}
                            {--amount=32000 : Energy amount}
                            {--duration=1h : Rental duration (5m, 1h, 1d, 3d, 15d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test TRON energy services';

    private EnergyServiceDispatcher $dispatcher;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->dispatcher = app(EnergyServiceDispatcher::class);

        $this->info("⚡ Testing TRON energy services");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->newLine();

        // Show available services
        $this->showAvailableServices();
        $this->newLine();

        // Run the tests matching the given options
        if ($this->option('balance')) {
            $this->testBalance();
        } elseif ($this->option('rent')) {
            $this->testRentEnergy();
        } elseif ($this->option('cost')) {
            $this->testCost();
        } else {
            // By default show all tests
            $this->testAllServices();
        }

        return 0;
    }

    /**
     * Shows the list of available services
     */
    private function showAvailableServices(): void
    {
        $this->info("📋 Available energy services:");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $services = EnergyService::orderBy('priority')->get();

        foreach ($services as $service) {
            $status = $service->is_enabled ? '<fg=green>✓ Enabled</>' : '<fg=red>✗ Disabled</>';
            $priority = "Priority: {$service->priority}";

            $this->line("  {$status} <fg=cyan>{$service->name}</> ({$service->code})");
            $this->line("     └─ {$priority}");
            if ($service->site_link) {
                $this->line("     └─ Website: {$service->site_link}");
            }
        }
    }

    /**
     * Tests all services
     */
    private function testAllServices(): void
    {
        $this->newLine();
        $this->testBalance();

        $this->newLine();
        $this->info("💡 Example commands:");
        $this->line("  php artisan test:energy --balance");
        $this->line("  php artisan test:energy --rent --address=TEXAMPLEADDRESS0000000000000000003 --amount=32000");
        $this->line("  php artisan test:energy --cost --address=TEXAMPLEADDRESS0000000000000000003");
    }

    /**
     * Tests fetching the balance
     */
    private function testBalance(): void
    {
        $this->info("💰 Testing balance retrieval");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $serviceCode = $this->option('service');

        if ($serviceCode && $serviceCode !== 'all') {
            // Test a specific service
            $this->testServiceBalance($serviceCode);
        } else {
            // Test all services
            $services = $this->dispatcher->getAllServices();

            foreach ($services as $service) {
                $this->testServiceBalance($service->getCode());
                $this->newLine();
            }
        }
    }

    /**
     * Tests the balance of a specific service
     */
    private function testServiceBalance(string $serviceCode): void
    {
        $service = $this->dispatcher->getService($serviceCode);

        if (!$service) {
            $this->error("  ❌ Service '{$serviceCode}' not found");
            return;
        }

        $this->line("  🔸 <fg=cyan>{$service->getName()}</> ({$service->getCode()})");

        try {
            $this->line("     ⏳ Fetching balance...");

            $balance = $service->getBalance();

            if (isset($balance['error'])) {
                $this->line("     ❌ <fg=red>Error: {$balance['error']}</>");
                return;
            }

            if (isset($balance['balance'])) {
                $this->line("     💵 Balance: <fg=green>{$balance['balance']}</>");
            }

            if (isset($balance['username'])) {
                $this->line("     👤 User: {$balance['username']}");
            }

            if (isset($balance['full_name'])) {
                $this->line("     📝 Name: {$balance['full_name']}");
            }

            if (isset($balance['frozen_balance'])) {
                $this->line("     ❄️  Frozen balance: {$balance['frozen_balance']}");
            }

            $this->line("     ✅ <fg=green>Balance fetched successfully</>");

        } catch (\Throwable $e) {
            $this->line("     ❌ <fg=red>Error: {$e->getMessage()}</>");
            if ($this->output->isVerbose()) {
                $this->line("     🔍 {$e->getFile()}:{$e->getLine()}");
            }
        }
    }

    /**
     * Tests energy rental
     */
    private function testRentEnergy(): void
    {
        $address = $this->option('address');
        $amount = (int) $this->option('amount');
        $duration = $this->option('duration');

        if (!$address) {
            $this->error("❌ Wallet address is not specified. Use --address=TEXAMPLEADDRESS0000000000000000003");
            return;
        }

        $this->info("⚡ Testing energy rental");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("  📍 Address: <fg=cyan>{$address}</>");
        $this->line("  ⚡ Amount: <fg=cyan>{$amount}</>");
        $this->line("  ⏱️  Duration: <fg=cyan>{$duration}</>");
        $this->newLine();

        // Show which service will be used
        $activeService = EnergyService::where('is_enabled', true)
            ->orderBy('priority')
            ->first();

        if (!$activeService) {
            $this->error("  ❌ No active energy services!");
            $this->line("     Enable a service in the admin panel: Settings → Energy services");
            return;
        }

        $this->line("  🎯 Active service: <fg=green>{$activeService->name}</> (priority: {$activeService->priority})");
        $this->newLine();

        if (!$this->confirm('  ⚠️  Proceed with energy rental? This is a real request!', false)) {
            $this->line("  ℹ️  Operation cancelled");
            return;
        }

        try {
            $this->line("  ⏳ Renting energy via the dispatcher...");

            $result = $this->dispatcher->rentEnergy($amount, $address, $duration);

            if ($result['success']) {
                $this->line("  ✅ <fg=green>Energy rented successfully!</>");
                $this->line("  📋 Details:");

                if (isset($result['data']['order_id'])) {
                    $this->line("     └─ Order ID: {$result['data']['order_id']}");
                }
                if (isset($result['data']['energy_amount'])) {
                    $this->line("     └─ Energy: {$result['data']['energy_amount']}");
                }
                if (isset($result['data']['price'])) {
                    $this->line("     └─ Price: {$result['data']['price']}");
                }
                if (isset($result['data']['summa'])) {
                    $this->line("     └─ Amount: {$result['data']['summa']} TRX");
                }
                if (isset($result['data']['balance'])) {
                    $this->line("     └─ Remaining balance: {$result['data']['balance']} TRX");
                }
                if (isset($result['message'])) {
                    $this->line("     └─ Message: {$result['message']}");
                }
            } else {
                $this->line("  ❌ <fg=red>Failed to rent energy</>");
                $this->line("     └─ Error: {$result['message']}");

                if (!empty($result['data'])) {
                    $this->line("     └─ Additional info:");
                    foreach ($result['data'] as $key => $value) {
                        if (is_scalar($value)) {
                            $this->line("        • {$key}: {$value}");
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            $this->line("  ❌ <fg=red>Exception: {$e->getMessage()}</>");
            if ($this->output->isVerbose()) {
                $this->line("  🔍 {$e->getFile()}:{$e->getLine()}");
            }
        }
    }

    /**
     * Tests fetching the cost
     */
    private function testCost(): void
    {
        $address = $this->option('address');

        if (!$address) {
            $this->error("❌ Wallet address is not specified. Use --address=TEXAMPLEADDRESS0000000000000000003");
            return;
        }

        $addresses = explode(',', $address);

        $this->info("💵 Testing cost calculation");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("  📍 Addresses: <fg=cyan>" . implode(', ', $addresses) . "</>");
        $this->newLine();

        // Show which service will be used
        $activeService = EnergyService::where('is_enabled', true)
            ->orderBy('priority')
            ->first();

        if (!$activeService) {
            $this->error("  ❌ No active energy services!");
            return;
        }

        $this->line("  🎯 Active service: <fg=green>{$activeService->name}</>");
        $this->newLine();

        try {
            $this->line("  ⏳ Calculating cost via the dispatcher...");

            $costs = $this->dispatcher->getCost($addresses);

            if (empty($costs)) {
                $this->line("  ⚠️  <fg=yellow>Failed to get the cost</>");
                return;
            }

            $this->line("  ✅ <fg=green>Cost received:</>");

            foreach ($costs as $addr => $cost) {
                $this->line("  📍 {$addr}:");

                if (is_array($cost)) {
                    foreach ($cost as $key => $value) {
                        if (is_scalar($value)) {
                            $this->line("     └─ {$key}: {$value}");
                        }
                    }
                } else {
                    $this->line("     └─ Required energy: {$cost}");
                }
            }

        } catch (\Throwable $e) {
            $this->line("  ❌ <fg=red>Error: {$e->getMessage()}</>");
            if ($this->output->isVerbose()) {
                $this->line("  🔍 {$e->getFile()}:{$e->getLine()}");
            }
        }
    }
}
