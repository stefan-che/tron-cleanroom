<?php
/**
 * php artisan db:seed --class=TransactionAmlChecksSeeder
 */

namespace Database\Seeders;

use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\TransactionAmlCheck;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionAmlChecksSeeder extends Seeder
{
    /** How many transactions to process */
    private int $transactionLimit = 10;

    /** Empty the table before seeding */
    private bool $clearBefore = false;

    public function run(): void
    {
        $this->transactionLimit = (int) env('SEED_TRANSACTION_LIMIT', 10);
        $this->clearBefore      = (bool) env('SEED_CLEAR', false);

        $services = AmlService::all();

        if ($services->isEmpty()) {
            $this->command->warn('No AML services in the DB. Run AmlServicesSeeder first.');
            return;
        }

        $transactions = Transaction::query()
            ->latest()
            ->limit($this->transactionLimit)
            ->get();

        if ($transactions->isEmpty()) {
            $this->command->warn('No transactions in the DB.');
            return;
        }

        if ($this->clearBefore) {
            DB::table('transactions_aml_checks')->truncate();
            $this->command->info('Table transactions_aml_checks cleared.');
        }

        $this->command->info(sprintf(
            'Seeding transactions_aml_checks: %d transactions × %d services...',
            $transactions->count(),
            $services->count()
        ));

        $now = now();
        $rows = 0;

        foreach ($transactions as $transaction) {
            foreach ($services as $service) {
                $status = $this->randomStatus();
                $isCompleted = $status === TransactionAmlCheck::STATUS_COMPLETED;
                $isSkipped   = $status === TransactionAmlCheck::STATUS_SKIPPED;

                $riskScore      = null;
                $fileReportLink = null;
                $response       = null;
                $signals        = null;
                $uid            = null;
                $checkFinishAt  = null;

                if ($isCompleted) {
                    $riskScore = round(fake()->randomFloat(4, 0, 1), 4);
                    $uid       = fake()->uuid();
                    $checkFinishAt = $now->copy()->subMinutes(fake()->numberBetween(1, 120));
                    $response  = $this->fakeResponse($service->code, $riskScore);
                    $signals   = $this->fakeSignals();

                    if (fake()->boolean(70)) {
                        $fileReportLink = 'https://example.com/reports/' . fake()->uuid() . '.pdf';
                    }
                }

                TransactionAmlCheck::updateOrCreate(
                    [
                        'transaction_id' => $transaction->id,
                        'service_id'     => $service->id,
                    ],
                    [
                        'status'          => $status,
                        'risk_score'      => $riskScore,
                        'file_report_link' => $fileReportLink,
                        'uid'             => $uid,
                        'response'        => $response,
                        'signals'         => $signals,
                        'counterparty'    => $isCompleted ? $this->fakeCounterparty() : null,
                        'check_finish_at' => $checkFinishAt,
                    ]
                );

                $rows++;
            }
        }

        $this->command->info("Records created / updated: {$rows}");
    }

    private function randomStatus(): int
    {
        // Distribution: 60% completed, 20% pending, 10% skipped, 10% failed
        $rand = fake()->numberBetween(1, 100);

        return match (true) {
            $rand <= 60 => TransactionAmlCheck::STATUS_COMPLETED,
            $rand <= 80 => TransactionAmlCheck::STATUS_PENDING,
            $rand <= 90 => TransactionAmlCheck::STATUS_SKIPPED,
            default     => TransactionAmlCheck::STATUS_FAILED,
        };
    }

    private function fakeResponse(string $serviceCode, float $riskScore): array
    {
        return match ($serviceCode) {
            AmlService::AML_BOT_CODE => [
                'uid'             => fake()->uuid(),
                'address'         => fake()->regexify('[A-Z]{1}[a-zA-Z0-9]{33}'),
                'riskscore'       => $riskScore,
                'report_link'     => 'https://example.com/reports/' . fake()->uuid(),
                'status'          => 'DONE',
                'riskscore_label' => $riskScore > 0.5 ? 'HIGH' : ($riskScore > 0.2 ? 'MEDIUM' : 'LOW'),
            ],
            AmlService::GETBLOCK_CODE => [
                'hash'          => fake()->sha256(),
                'risk_score'    => $riskScore,
                'report_link'   => 'https://example.com/reports/' . fake()->uuid(),
                'status'        => 'completed',
                'blockchain'    => 'tron',
            ],
            default => [
                'risk_score'  => $riskScore,
                'status'      => 'success',
                'report_link' => 'https://example.com/reports/' . fake()->uuid(),
            ],
        };
    }

    private function fakeSignals(): array
    {
        $categories = ['darknet', 'mixer', 'exchange', 'p2p', 'gambling', 'scam', 'sanctions'];

        $count = fake()->numberBetween(0, 3);
        if ($count === 0) {
            return [];
        }

        $selected = fake()->randomElements($categories, $count);
        $signals = [];

        foreach ($selected as $category) {
            $signals[] = [
                'name'    => $category,
                'score'   => round(fake()->randomFloat(4, 0.01, 0.5), 4),
                'volume'  => fake()->randomFloat(2, 1, 1000),
            ];
        }

        return $signals;
    }

    private function fakeCounterparty(): array
    {
        $types = ['exchange', 'wallet', 'mixer', 'unknown'];

        return [
            'name'    => fake()->company(),
            'type'    => fake()->randomElement($types),
            'address' => fake()->regexify('[A-Z]{1}[a-zA-Z0-9]{33}'),
            'share'   => round(fake()->randomFloat(2, 0.01, 1.0), 2),
        ];
    }
}
