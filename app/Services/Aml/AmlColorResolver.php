<?php

declare(strict_types=1);

namespace App\Services\Aml;

use App\Models\Wallet\TransactionAmlCheck;
use App\Models\Wallet\WalletAiAmlCheck;
use App\Services\Aml\Dto\AmlColorVerdict;

/**
 * Determines the wallet color from the result of ONE AML service.
 *
 * Mirrors the verdict algorithm of the AI prompt (docs/aml-prompt.md, "Verdict algorithm" section),
 * but without calling the AI and without merging services: the AI takes the maximum of each metric
 * across all providers, while here each check is rated separately. Used for reconciliation -
 * it shows which service pushes the final verdict up or down.
 *
 * Units: `signals` values are stored on a scale where 10 = 100% of volume (Wallet::formatSignals),
 * `counterparty` values are already percentages 0-100.
 *
 * IMPORTANT: the thresholds and venue lists below are duplicated in the AI prompt. When changing the policy,
 * update both in one commit: the prompt (`docs/aml-prompt.md` + AiServicesSeeder::defaultPrompt()
 * + the `ai_services` record) and this class's constants. Otherwise the reconciliation report will
 * show discrepancies that do not actually exist.
 */
final class AmlColorResolver
{
    /** Dangerous-source share threshold for BLACK, % */
    public const float BLACK_DANGER_SHARE = 7.0;

    /** Dangerous-source volume threshold for BLACK, USD */
    public const float BLACK_DANGER_USD = 3000.0;

    /** Sanctioned-category share threshold for BLACK, % */
    public const float BLACK_SANCTION_SHARE = 20.0;

    /** Sanctioned-category volume threshold for BLACK, USD */
    public const float BLACK_SANCTION_USD = 10000.0;

    /** Risk score at which a service moves the wallet to GREY */
    public const float GREY_RISK_SCORE = 60.0;

    /** Exchange-cluster share sufficient for WHITE, % */
    public const float WHITE_EXCHANGE_SHARE = 50.0;

    /**
     * Sanctioned counterparties and closed venues - always BLACK.
     */
    private const array FORBIDDEN_COUNTERPARTIES = [
        'JPEX', 'LocalMonero', 'Chatex', 'Bitzlato', 'Suex', 'FTX', 'BTC-e', 'WEX',
        'Mt.Gox', 'QuadrigaCX', 'Cryptopia', 'Thodex', 'Bisq', 'Garantex',
    ];

    /**
     * Restricted venues - capped at GREY.
     */
    private const array RESTRICTED_COUNTERPARTIES = [
        'Bittrex', 'CoinEx', 'KuCoin', 'BestChange', 'HodlHodl', 'Exch.cx', 'WhiteBIT',
    ];

    /**
     * Licensed exchanges with a good reputation - grounds for WHITE.
     */
    private const array LICENSED_COUNTERPARTIES = [
        'Binance', 'OKX', 'Bybit', 'MEXC', 'Kraken', 'Bitget', 'Gate.io', 'HTX',
        'Coinbase', 'Bitfinex',
    ];

    /**
     * Exchange cluster names: a 50%+ share gives WHITE even without an explicit exchange name.
     */
    private const array EXCHANGE_CLUSTERS = [
        'exchange', 'trusted exchange', 'exchange licensed', 'low-risk exchange',
        'exchange mlrisk low', 'centralized exchange', 'cex',
    ];

    /**
     * Color for a completed check. Null if the check is not completed - nothing to compare.
     *
     * @param float|null $amount Transaction volume in USDT, needed for the D_usd and S_usd thresholds
     */
    public static function forCheck(?TransactionAmlCheck $check, ?float $amount = null): ?AmlColorVerdict
    {
        if ($check === null || !$check->isCompleted()) {
            return null;
        }

        $signals = is_array($check->signals) ? $check->signals : [];

        $danger = self::shareOf($signals['high'] ?? []);
        $middle = self::shareOf($signals['middle'] ?? []);
        $sanction = self::shareOf(self::onlySanctions($signals));
        $risk = $check->risk_score_normalize;

        $dangerUsd = $amount !== null ? round($amount * $danger / 100, 2) : null;
        $sanctionUsd = $amount !== null ? round($amount * $sanction / 100, 2) : null;

        $counterparty = is_array($check->counterparty) ? $check->counterparty : [];
        $hasSignals = self::hasSignals($signals);

        [$color, $reason] = self::verdict(
            $danger,
            $middle,
            $sanction,
            $risk,
            $dangerUsd,
            $sanctionUsd,
            $counterparty,
            $signals,
            $hasSignals,
        );

        return new AmlColorVerdict(
            color: $color,
            reason: $reason,
            dangerShare: $danger,
            middleShare: $middle,
            sanctionShare: $sanction,
            riskScore: $risk,
            dangerUsd: $dangerUsd,
            sanctionUsd: $sanctionUsd,
        );
    }

    /**
     * Check order mirrors the prompt: the first matching condition is the answer.
     *
     * @return array{0: ?string, 1: string} [color, reason]; null color - not enough data
     */
    private static function verdict(
        float $danger,
        float $middle,
        float $sanction,
        ?float $risk,
        ?float $dangerUsd,
        ?float $sanctionUsd,
        array $counterparty,
        array $signals,
        bool $hasSignals,
    ): array {
        // 1. BLACK
        if ($danger >= self::BLACK_DANGER_SHARE) {
            return [WalletAiAmlCheck::COLOR_BLACK, 'Dangerous-source share reached the threshold of '
                . self::BLACK_DANGER_SHARE . '%.'];
        }

        if ($dangerUsd !== null && $dangerUsd > self::BLACK_DANGER_USD) {
            return [WalletAiAmlCheck::COLOR_BLACK, 'Dangerous-source volume exceeded $'
                . self::BLACK_DANGER_USD . '.'];
        }

        if ($sanction > self::BLACK_SANCTION_SHARE
            || ($sanctionUsd !== null && $sanctionUsd > self::BLACK_SANCTION_USD)) {
            return [WalletAiAmlCheck::COLOR_BLACK, 'Sanctioned-category threshold exceeded.'];
        }

        $forbidden = self::matchCounterparty($counterparty, self::FORBIDDEN_COUNTERPARTIES);
        if ($forbidden !== null) {
            return [WalletAiAmlCheck::COLOR_BLACK, 'Counterparties include a forbidden venue: ' . $forbidden . '.'];
        }

        // 2. GREY
        if ($danger > 0) {
            return [WalletAiAmlCheck::COLOR_GREY, 'Dangerous sources present, but their share is below the threshold of '
                . self::BLACK_DANGER_SHARE . '%.'];
        }

        if ($middle > 0) {
            return [WalletAiAmlCheck::COLOR_GREY, 'Suspicious sources present.'];
        }

        if ($risk !== null && $risk >= self::GREY_RISK_SCORE) {
            return [WalletAiAmlCheck::COLOR_GREY, 'Service risk score is at least '
                . self::GREY_RISK_SCORE . ' - manual investigation required.'];
        }

        $restricted = self::matchCounterparty($counterparty, self::RESTRICTED_COUNTERPARTIES);
        if ($restricted !== null) {
            return [WalletAiAmlCheck::COLOR_GREY, 'Counterparty from the restricted list: ' . $restricted . '.'];
        }

        // WHITE and YELLOW both require a source breakdown. Without it no color is
        // returned: "no data" is not the same as "grey" in the reconciliation
        // report - otherwise a data-poor service would always look risky.
        if (!$hasSignals) {
            return [null, 'Service returned no source-of-funds breakdown - nothing to assess.'];
        }

        // 3. WHITE
        $licensed = self::matchCounterparty($counterparty, self::LICENSED_COUNTERPARTIES);
        if ($licensed !== null) {
            return [WalletAiAmlCheck::COLOR_WHITE, 'Funds from trusted sources, counterparty is '
                . 'licensed exchange ' . $licensed . '.'];
        }

        $exchangeShare = self::exchangeShare($counterparty, $signals);
        if ($exchangeShare >= self::WHITE_EXCHANGE_SHARE) {
            return [WalletAiAmlCheck::COLOR_WHITE, 'Funds from trusted sources, exchange-cluster share '
                . round($exchangeShare, 1) . '%.'];
        }

        // 4. YELLOW
        return [WalletAiAmlCheck::COLOR_YELLOW, 'Funds from trusted sources, but no exchange counterparty.'];
    }

    /**
     * Total share of a signal group in percent: the stored scale is 10 = 100%.
     */
    private static function shareOf(array $group): float
    {
        return round(array_sum(array_map('floatval', $group)) * 10, 2);
    }

    /**
     * Sanctioned categories across all groups: the prompt computes S by category name
     * (sanctions, SANCTIONED_JURISDICTION and similar), not by the group
     * a particular service put it in.
     */
    private static function onlySanctions(array $signals): array
    {
        $sanctions = [];

        foreach (['low', 'middle', 'high'] as $group) {
            foreach ($signals[$group] ?? [] as $name => $value) {
                if (str_contains(mb_strtolower((string) $name), 'sanction')) {
                    $sanctions[$group . '.' . $name] = $value;
                }
            }
        }

        return $sanctions;
    }

    /**
     * Whether there is any source breakdown at all: empty means GREY, not WHITE.
     */
    private static function hasSignals(array $signals): bool
    {
        foreach (['low', 'middle', 'high'] as $group) {
            if (!empty(array_filter($signals[$group] ?? [], fn($value) => (float) $value > 0))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exchange-cluster share: from counterparties (already percent) or from trusted signals.
     */
    private static function exchangeShare(array $counterparty, array $signals): float
    {
        $byCounterparty = 0.0;
        foreach ($counterparty as $name => $value) {
            if (self::isExchangeCluster((string) $name)) {
                $byCounterparty += (float) $value;
            }
        }

        $bySignals = 0.0;
        foreach ($signals['low'] ?? [] as $name => $value) {
            if (self::isExchangeCluster((string) $name)) {
                $bySignals += (float) $value * 10;
            }
        }

        return round(max($byCounterparty, $bySignals), 2);
    }

    private static function isExchangeCluster(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));

        return in_array($normalized, self::EXCHANGE_CLUSTERS, true);
    }

    /**
     * Finds a counterparty from the list. Only explicit venue names are compared:
     * cluster names ("Exchange", "Unnamed service") are not part of the lists.
     *
     * @return ?string Venue name from the list, if named among the counterparties
     */
    private static function matchCounterparty(array $counterparty, array $known): ?string
    {
        foreach ($counterparty as $name => $value) {
            if ((float) $value <= 0) {
                continue;
            }

            $whole = self::normalizeName((string) $name);
            $words = array_map(
                fn(string $word) => self::normalizeName($word),
                preg_split('/[\s\/|,]+/u', (string) $name) ?: []
            );

            foreach ($known as $candidate) {
                $needle = self::normalizeName($candidate);

                if ($needle !== '' && ($whole === $needle || in_array($needle, $words, true))) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private static function normalizeName(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/u', '', mb_strtolower($name));
    }
}
