<?php

namespace App\Reports;

use App\Models\Energy\EnergyWallet;
use Illuminate\Support\Collection;

readonly class ExpiredEnergyWalletsReport
{
    public function buildBody(Collection $expired): string
    {
        $grouped = $expired->groupBy(fn(EnergyWallet $w) => $w->recipient_id ?? 0);

        $body = '';
        foreach ($grouped as $recipientWallets) {
            $recipientName = $recipientWallets->first()->recipient?->name ?? '—';
            $body .= "🏢 <b>Recipient: {$recipientName}</b>\n\n";

            /** @var EnergyWallet $wallet */
            foreach ($recipientWallets as $wallet) {
                $body .= "👛 Address: <a href=\"" . $wallet->tronscan_address_link . "\">" . $wallet->address . "</a>\n";
                $body .= "🙈 Created: " . $wallet->created_at->diffForHumans() . "\n";
                $body .= "⏱️ Expired: " . $wallet->expires_at->format('d.m.Y H:i') . "\n\n";
            }

            $recipientCount = $recipientWallets->count();
            $body .= "Recipient wallets: <b>{$recipientCount}</b>\n";
            $body .= "-----------------------------\n\n";
        }

        return trim($body);
    }

    public function buildAlertMessage(Collection $expired): string
    {
        $count = $expired->count();

        return implode("\n", [
            "⚠️ <b>Energy auto-replenishment</b>",
            "",
            "Found <b>{$count}</b> enabled wallets that have expired:",
            "",
            $this->buildBody($expired),
            "",
            "Check them in the bot.",
        ]);
    }

    public function buildBotMessage(Collection $expired): string
    {
        if ($expired->isEmpty()) {
            return "⏰ <b>Expired wallets</b>\n\n✅ No expired enabled wallets.";
        }

        $count = $expired->count();

        return "⏰ <b>Expired wallets</b> · {$count}\n\nEnabled wallets that have expired:\n\n" . $this->buildBody($expired);
    }
}
