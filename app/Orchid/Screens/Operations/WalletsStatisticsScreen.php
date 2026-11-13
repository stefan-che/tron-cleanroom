<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Operations;

use App\Models\Role;
use App\Orchid\Layouts\Operations\Statistics\ConversionRateChart;
use App\Orchid\Layouts\Operations\Statistics\RecipientsStatisticsTable;
use App\Orchid\Layouts\Operations\Statistics\PeriodFilterLayout;
use App\Orchid\Layouts\Operations\Statistics\ProcessingTimeLineChart;
use App\Orchid\Layouts\Operations\Statistics\ProcessingTimeTable;
use App\Orchid\Layouts\Operations\Statistics\WalletTypesByRecipientBarChart;
use App\Orchid\Layouts\Operations\WalletsByRecipientPieChart;
use App\Reports\WalletsStatisticsReport;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class WalletsStatisticsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        return WalletsStatisticsReport::forPeriod($request->get('period', 'month'))->getData();
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Wallet statistics';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Wallet distribution by recipient';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_OPERATIONS,
        ];
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
            PeriodFilterLayout::class,

            Layout::columns([
                WalletsByRecipientPieChart::make('newWalletsChart', 'Wallets with status "Expired"')
                    ->description('Expired wallets by recipient'),
                WalletsByRecipientPieChart::make('completedWalletsChart', 'Wallets with status "Completed"')
                    ->description('Completed wallets by recipient'),
            ]),

            Layout::columns([
                ConversionRateChart::make('conversionRateChart', 'Conversion by recipient')
                    ->description('Share of completed wallets out of the total (percent). Higher means the recipient performs better.'),
            ]),

            Layout::columns([
                WalletsByRecipientPieChart::make('walletTypesChart', 'Distribution by wallet type')
                    ->description('Wallet count by type'),
            ]),

            Layout::columns([
                WalletTypesByRecipientBarChart::make('walletTypesByRecipientChart', 'Distribution by wallet type and recipient')
                    ->description('Wallet count by type for each recipient'),
            ]),
            Layout::columns([
                WalletsByRecipientPieChart::make('processingTimePieChart', 'Processing speed by recipient — period average (min)')
                    ->description('Average time from incoming transaction to sending to the wallet. Lower is better.'),
            ]),

            Layout::columns([
                ProcessingTimeLineChart::make('processingTimeAvgChart', 'Average processing time by recipient (min)')
                    ->description('Average time from incoming transaction to sending to the wallet, in minutes'),
            ]),

            Layout::columns([
                ProcessingTimeLineChart::make('processingTimeMinChart', 'Minimum processing time by recipient (min)')
                    ->description('Fastest operation of the day for each recipient'),
                ProcessingTimeLineChart::make('processingTimeMaxChart', 'Maximum processing time by recipient (min)')
                    ->description('Slowest operation of the day for each recipient'),
            ]),

            Layout::block(
                ProcessingTimeTable::class
            )->title('Processing time by recipient and day')
                ->vertical(),

            Layout::block(
                RecipientsStatisticsTable::class
            )->title('Recipient statistics')
                ->vertical(),
        ];
    }

}
