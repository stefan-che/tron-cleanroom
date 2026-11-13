<?php

namespace App\Reports;

use App\Helpers\MathHelper;
use App\Models\Recipient\TgUser;
use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class WalletsReport
{
    public function __construct(private WalletRepository $walletRepository)
    {
    }

    public function getWallets(array $recipientIds = []): Collection
    {
        return $this->walletRepository->getAlertReport($recipientIds);
    }

    public function buildMessage(Collection $wallets): string
    {
        $date = Carbon::now()->format('d.m.Y');

        if ($wallets->isEmpty()) {
            return "📊 Wallets report for {$date}\n\nNo unprocessed wallets.";
        }

        $message = "📊 Wallets report for {$date}\n\n";

        $walletsGrouped = $wallets->sortBy('recipient_id')->groupBy('recipient_id');
        $totalAmount = 0;

        foreach ($walletsGrouped as $recipientWallets) {
            $recipientName = $recipientWallets->first()->recipient->name ?? 'Recipient not set';
            $recipientAmount = 0;

            $message .= "🏢 Recipient: {$recipientName}\n\n";

            /** @var Wallet $wallet */
            foreach ($recipientWallets as $wallet) {
                $message .= "🧩 ID: " . $wallet->external_id_format . "\n";
                $message .= "🙈 Created: " . $wallet->created_at->diffForHumans() . "\n";
                $message .= "👛 Address: <a href=\"" . $wallet->tronscan_address_link . "\">" . $wallet->address . "</a>\n";
                $message .= "🚀 AMLBot: " . $wallet->aml_risk_score_normalize . "\n";
                $message .= "💰 Balance: " . MathHelper::formatNumber($wallet->amount) . "$\n";
                $message .= "-----------------------------\n";
                $recipientAmount += $wallet->amount;
            }

            $message .= "Recipient total: " . MathHelper::formatNumber($recipientAmount) . "$\n\n";
            $totalAmount += $recipientAmount;
        }

        $message .= "💵 Total for all recipients: " . MathHelper::formatNumber($totalAmount) . "$\n";

        return $message;
    }

    public function build(?int $tgUserId = null): string
    {
        $recipientIds = $tgUserId ? TgUser::getRecipientIds($tgUserId) : [];
        return $this->buildMessage($this->getWallets($recipientIds));
    }
}
