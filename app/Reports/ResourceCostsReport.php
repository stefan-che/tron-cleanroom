<?php

namespace App\Reports;

use App\Helpers\MathHelper;
use App\Models\Aml\AmlService;
use App\Models\Recipient\Recipient;
use App\Models\Recipient\TgUser;
use App\Models\Wallet\TransactionAmlCheck;
use App\Models\Wallet\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class ResourceCostsReport
{
    public function build(?int $tgUserId = null): string
    {
        $recipientIds = $tgUserId ? TgUser::getRecipientIds($tgUserId) : [];

        $now = Carbon::now();
        $month = $now->month;
        $year = $now->year;

        $energyByRecipient = $this->getEnergyByRecipient($recipientIds, $month, $year);
        $amlByRecipientService = $this->getAmlByRecipientService($recipientIds, $month, $year);
        $recipients = $this->getRecipients($recipientIds);
        $services = AmlService::all()->keyBy('id');

        return $this->buildMessage($now, $recipients, $energyByRecipient, $amlByRecipientService, $services);
    }

    private function getEnergyByRecipient(array $recipientIds, int $month, int $year): Collection
    {
        return Wallet::query()
            ->selectRaw('recipient_id, SUM(energy_cost_trx) as total')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->when($recipientIds, fn($q) => $q->whereIn('recipient_id', $recipientIds))
            ->whereNotNull('recipient_id')
            ->groupBy('recipient_id')
            ->get()
            ->keyBy('recipient_id');
    }

    private function getAmlByRecipientService(array $recipientIds, int $month, int $year): Collection
    {
        return TransactionAmlCheck::withTrashed()
            ->selectRaw('wallets.recipient_id, transactions_aml_checks.service_id, COUNT(*) as cnt')
            ->join('transactions', 'transactions_aml_checks.transaction_id', '=', 'transactions.id')
            ->join('wallets', 'transactions.wallet_id', '=', 'wallets.id')
            ->whereMonth('transactions_aml_checks.created_at', $month)
            ->whereYear('transactions_aml_checks.created_at', $year)
            ->when($recipientIds, fn($q) => $q->whereIn('wallets.recipient_id', $recipientIds))
            ->whereNotNull('wallets.recipient_id')
            ->groupBy('wallets.recipient_id', 'transactions_aml_checks.service_id')
            ->get()
            ->groupBy('recipient_id');
    }

    private function getRecipients(array $recipientIds): Collection
    {
        return Recipient::query()
            ->when($recipientIds, fn($q) => $q->whereIn('id', $recipientIds))
            ->orderBy('name')
            ->get();
    }

    private function buildMessage(
        Carbon $now,
        Collection $recipients,
        Collection $energyByRecipient,
        Collection $amlByRecipientService,
        Collection $services,
    ): string {
        $monthName = $now->translatedFormat('F Y');
        $message = "⚡ Resource costs report for {$monthName}\n\n";

        $totalEnergy = $energyByRecipient->sum('total');
        $totalAml = $amlByRecipientService->flatten(1)->sum('cnt');

        $message .= "Total energy: " . MathHelper::formatNumber($totalEnergy) . " TRX\n";
        $message .= "Total AML checks: {$totalAml}\n\n";

        if ($recipients->isEmpty()) {
            $message .= "No recipients available.";
            return $message;
        }

        foreach ($recipients as $recipient) {
            $energy = (float) ($energyByRecipient->get($recipient->id)?->total ?? 0);
            $recipientAmlRows = $amlByRecipientService->get($recipient->id, collect());
            $recipientAmlTotal = $recipientAmlRows->sum('cnt');

            $message .= "🏢 {$recipient->name}\n";
            $message .= "   ⚡ Energy: " . MathHelper::formatNumber($energy) . " TRX\n";
            $message .= "   🔍 AML checks: {$recipientAmlTotal}\n";

            foreach ($recipientAmlRows as $row) {
                $serviceName = $services->get($row->service_id)?->name ?? "Service #{$row->service_id}";
                $message .= "      {$serviceName}: {$row->cnt}\n";
            }

            $message .= "\n";
        }

        return rtrim($message);
    }
}