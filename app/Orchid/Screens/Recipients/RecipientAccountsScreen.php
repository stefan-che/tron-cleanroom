<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Recipients;

use App\Models\Recipient\LedgerAccount;
use App\Models\Recipient\Recipient;
use App\Models\Role;
use App\Orchid\Layouts\Recipients\Accounts\AccountsListLayout;
use App\Orchid\Layouts\Recipients\Accounts\AccountWalletsListLayout;
use App\Orchid\Layouts\Recipients\TabMenuLayout;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

// Recipient accounts in the external ledger (optional integration, docs/LEDGER_INTEGRATION.md)
class RecipientAccountsScreen extends Screen
{
    /**
     * @var Recipient $recipient
     */
    public $recipient;

    public function query(Recipient $recipient): iterable
    {
        return [
            'recipient' => $recipient,
            'accounts' => LedgerAccount::where('recipient_id', $recipient->id)->paginate(50),
        ];
    }

    public function name(): ?string
    {
        return sprintf('Ledger accounts: %s', $this->recipient->name);
    }

    public function description(): ?string
    {
        if (!app(LedgerService::class)->isConfigured()) {
            return 'Account ledger is not connected.';
        }

        return $this->recipient->ledger_user_id
            ? 'Ledger ID: ' . $this->recipient->ledger_user_id
            : '';
    }

    public function permission(): ?iterable
    {
        return [Role::PERMISSION_RECIPIENTS];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Refresh accounts from ledger')
                ->icon('refresh')
                ->method('syncAccounts')
                ->canSee(app(LedgerService::class)->isConfigured()),
        ];
    }

    public function layout(): iterable
    {
        return [
            TabMenuLayout::class,
            AccountsListLayout::class,

            Layout::modal('accountWalletsModal', [
                AccountWalletsListLayout::class,
            ])
                ->size(Modal::SIZE_LG)
                ->async('asyncGetAccountWallets')
                ->title('Account wallets in the ledger')
                ->withoutApplyButton()
                ->closeButton('Close'),
        ];
    }

    public function asyncGetAccountWallets(Request $request): iterable
    {
        $account = LedgerAccount::with('ledgerWallets')->find((int) $request->input('account_id'));

        return [
            'account_wallets' => $account?->ledgerWallets ?? collect(),
        ];
    }

    public function syncAccounts(Recipient $recipient, LedgerService $ledgerService): void
    {
        if (!$ledgerService->isConfigured()) {
            Toast::error('Account ledger is not connected.');
            return;
        }

        $userId = $recipient->ledger_user_id;

        if (!$userId) {
            Toast::error('The recipient has no ledger ID.');
            return;
        }

        $page = 1;
        $perPage = 100;
        $added = 0;
        $updated = 0;

        do {
            $result = $ledgerService->getAccounts($page, $perPage, (int)$userId);

            if (!$result['ok']) {
                Log::channel('ledger')->error('RecipientAccountsScreen: getAccounts failed', [
                    'recipient_id' => $recipient->id,
                    'response' => $result,
                ]);
                Toast::error('The ledger did not respond, try again later.');
                return;
            }

            $data = $result['body']['data'] ?? [];
            $pagination = $result['body']['pagination'] ?? [];

            foreach ($data as $item) {
                $account = LedgerAccount::updateOrCreate(
                    [
                        'ledger_id' => $item['id'],
                        'recipient_id' => $recipient->id,
                    ],
                    [
                        'group_id' => $item['group_id'],
                        'user_id' => (string)$item['user_id'],
                        'name' => $item['name'],
                        'currency' => $item['currency'],
                        'amount' => (int)$item['amount'],
                    ]
                );

                $account->wasRecentlyCreated ? $added++ : $updated++;
            }

            $totalPages = $pagination['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        Toast::success("Sync complete. Added: {$added}, updated: {$updated}.");
    }

    public function toggleShowBot(Request $request, Recipient $recipient): void
    {
        $account = LedgerAccount::where('id', $request->input('account_id'))
            ->where('recipient_id', $recipient->id)
            ->firstOrFail();

        $account->update(['is_show_bot' => !$account->is_show_bot]);

        Toast::success($account->is_show_bot ? 'Account shown in the bot.' : 'Account hidden in the bot.');
    }
}
