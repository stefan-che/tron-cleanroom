<?php

namespace App\Services\Aml\Services;

use App\Jobs\Aml\WellWallet\AmlAddressCheck;
use App\Jobs\Aml\WellWallet\AmlTransactionCheck;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Services\Aml\Contracts\AmlServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class WellWalletService implements AmlServiceInterface
{
    const string BALANCE_CACHE_KEY = 'wellwallet:checks_balance';
    const int BALANCE_CACHE_TTL = 3600 * 24;

    public function getCode(): string
    {
        return AmlService::WELL_WALLET_CODE;
    }

    public function getName(): string
    {
        return 'WellWallet';
    }

    public function supportsBlockchain(int $blockchainType): bool
    {
        return in_array($blockchainType, $this->getSupportedBlockchains(), true);
    }

    public function supportsRecipients(string $recipientExtCode): bool
    {
        return true;
    }

    public function checkAddress(AmlAddress $amlAddress): void
    {
        AmlAddressCheck::dispatch($amlAddress);
    }

    public function checkTransaction(Transaction $transaction): void
    {
        // Check whether WellWallet supports this blockchain
        if (!$this->supportsBlockchain($transaction->wallet->blockchain_type)) {
            return;
        }

        AmlTransactionCheck::dispatch($transaction->id);
    }

    public function downloadReport(?string $directLink, ?array $response): mixed
    {
        if (!$response || !isset($response['data']['response']['pdf_link'])) {
            return response()->json(['error' => 'Report link is missing'], 400);
        }

        $pdfLink = $response['data']['response']['pdf_link'];

        $downloadResponse = Http::withHeaders([
            'token' => config('services.well_wallet.api_key'),
        ])
            ->timeout(60)
            ->get($pdfLink);

        if ($downloadResponse->successful()) {
            return response()->streamDownload(function () use ($downloadResponse) {
                echo $downloadResponse->body();
            }, 'aml_report.pdf');
        }

        return response()->json(['error' => 'Failed to download file'], 500);
    }

    /**
     * Extracts signals from the WellWallet response and normalizes percentages (0-100) to shares (0-1)
     */
    public function extractSignals(array $typesOfFunding): array
    {
        return collect($typesOfFunding)
            ->map(fn($v) => $v / 100)
            ->all();
    }

    /**
     * Extracts counterparties from the WellWallet response
     */
    public function extractCounterparty(array $sourcesOfFunding): array
    {
        return $sourcesOfFunding;
    }

    public function getSignalGroups(): array
    {
        return [
            'low' => [
                'Exchange', 'Low-risk exchange', 'Mining', 'Mining pool', 'Mining service',
                'Payment service', 'Online wallet / Payment service', 'Marketplace',
                'NFT marketplace', 'NFT project', 'NFT artist',
                'DeFi', 'DeFi / DEX', 'DeFi / Lending', 'DeFi / NFT marketplace',
                'DeFi / NFT project', 'DeFi / Staking', 'DeFi / DAO', 'DeFi / Investments',
                'DeFi / Social platform', 'DeFi / Community platfrom', 'DeFi analytics',
                'Stablecoin', 'Algorithmic stablecoin', 'Staking', 'Yield farming',
                'Liquidity provider', 'Lending', 'DEX', 'DAO', 'dApps', 'Oracle',
                'Validator', 'Meme coin', 'Minting / Mining / Staking', 'Wrapped token',
                'Tokenized assets', 'Airdrop / Distributor', 'Burn address', 'Faucet',
                'ZK-Rollup', 'MEV bot', 'MEV builder',
                'Smart contract management', 'Blockchain', 'Blockchain analytics',
                'Blockchain explorer', 'Blockchain solutions',
                'Crowdfunding platform', 'Incubator / Launchpad', 'Investments / Venture fund',
                'Insurance', 'Fintech', 'Custodian', 'Trading bot', 'Trading platform',
                'Prediction platform', 'Ticketing / Events', 'Auction',
                'E-commerce', 'Marketplace', 'Rental platform', 'Real estate',
                'Delivery service', 'Shipping / Logistics',
                'Entertainment platform', 'Streaming platform', 'Gaming', 'Sports',
                'Music-related project', 'Travel-related platform',
                'Educational platform', 'Science-related project', 'News platform',
                'Blog', 'Blogger', 'Journalist', 'Forum', 'Community',
                'Non-profit organization', 'Charity', 'Anti-slavery organisation', 'Donations',
                'Software development', 'Technologies', 'AI-based solutions', 'AR-related project',
                'Data analytics', 'Data storage', 'Data exchange', 'Data verification',
                'Cloud services', 'DNS', 'Search engine', 'Advertising',
                'Healthcare', 'Food industry', 'Agriculture', 'Beauty industry',
                'Animal control', 'Hardware', 'Telecommunications',
                'Job search / Recruiting', 'Vehicle-related service', 'Yacht-related services',
                'Cashback platform', 'Individual', 'Service',
                'Seized coins', 'White-hat hackers', 'Cybersecurity',
                'VPN', 'Energy sector / Ecology', 'Energy broker',
            ],
            'middle' => [
                'P2P exchange', 'High-risk exchange', 'High-risk payment service',
                'High-risk service', 'High-risk donations', 'High-risk forum',
                'Anonymizer', 'Privacy transactions',
                'ATM', 'Suspended ATM service', 'Bitcoin-OTC user', 'BitcoinTalk user',
                'Cloud mining', 'Online wallet', 'Adult entertainment',
                'Cross-chain protocol',
                'Darknet community', 'Darknet explorer',
                'unidentified service / exchange', 'unknown single wallet service',
                'maximum depth reached',
                'Suspended DeFi', 'Suspended DEX', 'Suspended exchange', 'Suspended faucet',
                'Suspended gambling platform', 'Suspended gaming platform',
                'Suspended investments / venture fund', 'Suspended marketplace',
                'Suspended NFT project', 'Suspended online wallet',
                'Suspended payment service', 'Suspended service',
                'Suspended yield farming platform',
                'HYIP hunter', 'Proxy seller', 'SIM cards seller',
                'Precursors distributor / manufacturer',
                'Political institution', 'Jurisprudence',
                'Pro-Russian political blogger', 'Pro-Russian Telegram channel',
                'Russian military blogger', 'Russian military correspondent',
                'Russian military news agency', 'Russian military-focused Telegram channel',
                'Russian political actor', 'Russian political party', 'Russian propagandist',
                'Palestian military correspondent', 'Ukrainian political actor',
            ],
            'high' => [
                'Gambling', 'Mixing service', 'OFSI Sanctions', 'EU Sanctions',
                'Address poisoning', 'Child abuse', 'Cloud mining scam',
                'Credit card dumps market', 'Cybercrime / Hack',
                'Darknet marketplace', 'Darknet service',
                'Drug smuggler', 'Drug store', 'Drug vendor',
                'Exit scam', 'Exploit', 'Extremism',
                'Fake docs seller', 'Fake money seller', 'Fraud',
                'Hacked exchange', 'Hacked wallet', 'Hacker', 'Hackers group', 'Hacking services',
                'Hitman hiring', 'Honeypot', 'Human trafficking',
                'Illegal service', 'Illegal services seller', 'Investment scam',
                'Malware developer', 'Malware seller',
                'Paramilitary group', 'Personal data seller', 'Phishing',
                'Ponzi scheme', 'Ransomware',
                'Reported blackmail', 'Reported darknet', 'Reported extortion',
                'Reported hack', 'Reported phishing', 'Reported ransomware', 'Reported scam',
                'Rug pull','NBCTF Sanctions','Blacklisted Wallet',
                'Russian arms supplier', 'Russian Intelligence', 'Russian invasion funding',
                'Russian paramilitary group',
                'Sanctioned entity', 'Sanctioned exchange', 'Sanctioned individual',
                'Scam', 'Scam token', 'Scam token deployer', 'Spam token',
                'Stolen electronics seller', 'Stolen wallets seller',
                'Terrorist financing', 'Terrorists organization',
                'Torture streaming service', 'Weapon shop',
            ],
        ];
    }

    /**
     * WellWallet supports a limited set of blockchains
     * @return array
     */
    public function getSupportedBlockchains(): array
    {
        return [
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20,
            AmlAddress::BLOCKCHAIN_TYPE_ETH,
            AmlAddress::BLOCKCHAIN_TYPE_BTC,
            AmlAddress::BLOCKCHAIN_TYPE_SOLANA,
        ];
    }

    public function setChecksBalance(float $balance): void
    {
        Redis::set(self::BALANCE_CACHE_KEY, $balance);
        Redis::expire(self::BALANCE_CACHE_KEY, self::BALANCE_CACHE_TTL);
    }

    public function getChecksBalance(): ?float
    {
        $value = Redis::get(self::BALANCE_CACHE_KEY);
        return $value !== null ? (float)$value : null;
    }

    public static function isEnabledService():bool
    {
        /**
         * @var AmlService $service
         */
        $service = AmlService::wellWallet()->first();
        return $service->is_enabled;
    }
}
