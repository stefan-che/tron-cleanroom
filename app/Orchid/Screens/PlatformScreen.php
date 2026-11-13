<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\Wallet\WalletLog;
use App\Orchid\Layouts\Operations\WalletLogList;
use App\Orchid\Layouts\Platform\ChartDiffCompletedWallets;
use App\Orchid\Layouts\Platform\WalletsCreatedLineChart;
use App\Repositories\WalletRepository;
use App\Repositories\WalletsStatisticsRepository;
use App\Services\Aml\AmlBot\History;
use App\Services\Aml\CoinKyt\GetBalance;
use App\Services\Aml\Services\WellWalletService;
use App\Services\Aml\WellWallet\UserBalances;
use App\Services\Refee\Balance;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PlatformScreen extends Screen
{

    private WalletsStatisticsRepository $statisticsRepository;

    public function __construct(
        WalletsStatisticsRepository $repository,
    )
    {
        $this->statisticsRepository = $repository;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $wallets = app(WalletRepository::class)->getSyncTransactions();
        $createdWallets = $this->statisticsRepository->getCreatedWalletsByDay();

        return [
            'logs' => WalletLog::query()->limit(50)->latest()->get(),

            'metrics' => [
                'aml_balance' => ['value' => $this->getAmlBalance()],
                'refee_balance' => ['value' => $this->getRefeeBalance()],
                'coinkyt_balance' => ['value' => $this->getCoinKytBalance()],
                'wellwallet_balance' => ['value' => $this->getWellWalletBalance()],
                'sync_wallets_count' => ['value' => count($wallets)],
            ],
            'chartsDiffCompleted' => [
                [
                    'name' => 'Comparison',
                    'values' => [
                        0,0
                    ],
                    'labels' => [
                        'Yesterday',
                        'Today',
                    ],
                ],
            ],
            'walletsCreatedChart' => [
                [
                    'name' => 'Wallets created',
                    'values' => $createdWallets['values'],
                    'labels' => $createdWallets['labels'],
                ],
            ],
        ];
    }

    private function getMetricsData(): array
    {
        $wallets = $this->statisticsRepository->getStatisticsWallets();

        return [
            'wallets' => $wallets,
            'diffCompleted' => $wallets,
        ];
    }


    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Welcome';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return '';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [

            Layout::metrics([
                'AMLBot:' => 'metrics.aml_balance',
                'WellWallet(aml):' => 'metrics.wellwallet_balance',
                'ReFee (energy):' => 'metrics.refee_balance',
                'CoinKyt(AML):' => 'metrics.coinkyt_balance',
            ])->title('Provider balances'),

            Layout::metrics([
                'Wallets being monitored' => 'metrics.sync_wallets_count',
            ]),

            Layout::columns([
                WalletsCreatedLineChart::make('walletsCreatedChart', 'Wallets created in the last month')
                    ->description('Wallets created per day (excluding status "New")'),
            ]),

            Layout::block(
                WalletLogList::class
            )->title('Latest operations')
                ->vertical(),
        ];
    }

    private function getAmlBalance(): float
    {
        try {
            return (new History())->history()['balance'] ?? 0;
        } catch (\Exception $exception) {
            return 0;
        }
    }

    private function getRefeeBalance(): float
    {
        try {
            return (new Balance())->balance()['balance'] ?? 0;
        } catch (\Exception $exception) {
            return 0;
        }
    }

    private function getCoinKytBalance(): float
    {
        try {
            return (new GetBalance())->get()['current_balance'] ?? 0;
        } catch (\Exception $exception) {
            return 0;
        }
    }

    private function getWellWalletBalance(): ?float
    {
        try {
            return (new WellWalletService())->getChecksBalance();
        } catch (\Exception $exception) {
            return 0;
        }
    }
}
