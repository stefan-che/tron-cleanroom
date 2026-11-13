<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\WalletAiAmlCheck;

class AiAmlFormatter
{
    /**
     * Makes free-form AI text safe for any consumer.
     *
     * Reports go to Telegram with parse_mode=HTML, so an unpaired "<" in text
     * like "D=0.8% (<7%)" breaks message delivery. Comparisons are converted to
     * words, other HTML special characters to typographic equivalents.
     * The method is idempotent: calling it again does not change already cleaned text.
     */
    public static function plainSummary(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // Comparisons with a number: "D<7%", "R >= 60"
        $text = preg_replace('/<\s*=\s*(?=[\d.,])/u', 'at most ', $text);
        $text = preg_replace('/>\s*=\s*(?=[\d.,])/u', 'at least ', $text);
        $text = preg_replace('/<\s*(?=[\d.,])/u', 'less than ', $text);
        $text = preg_replace('/>\s*(?=[\d.,])/u', 'more than ', $text);

        // Everything else the HTML parser treats as markup
        $text = strtr($text, [
            '<' => '‹',
            '>' => '›',
            '&' => 'and',
        ]);

        return trim(preg_replace('/[ \t]{2,}/u', ' ', $text));
    }

    /**
     * Colored badge of the AI verdict by wallet color.
     */
    public static function colorBadge(?string $color): string
    {
        return match ($color) {
            WalletAiAmlCheck::COLOR_WHITE => '<span class="badge bg-light text-dark border">WHITE</span>',
            WalletAiAmlCheck::COLOR_YELLOW => '<span class="badge bg-warning text-dark">YELLOW</span>',
            WalletAiAmlCheck::COLOR_GREY => '<span class="badge bg-secondary">GREY</span>',
            WalletAiAmlCheck::COLOR_BLACK => '<span class="badge bg-dark">BLACK</span>',
            default => '<span class="text-muted">-</span>',
        };
    }

    /**
     * Badge of the operator's final decision: the wallet type the funds were sent to.
     */
    public static function walletTypeBadge(?int $type): string
    {
        return match ($type) {
            RecipientWallet::TYPE_WHITE => '<span class="badge bg-light text-dark border">White</span>',
            RecipientWallet::TYPE_YELLOW => '<span class="badge bg-warning text-dark">Yellow</span>',
            RecipientWallet::TYPE_GRAY => '<span class="badge bg-secondary">Gray</span>',
            RecipientWallet::TYPE_BLACK => '<span class="badge bg-dark">Black</span>',
            default => '<span class="text-muted">-</span>',
        };
    }
}
