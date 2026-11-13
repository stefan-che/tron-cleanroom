<?php

namespace App\Listeners\Ledger;

use App\Events\Energy\RecipientWalletAddressChanged;
use App\Models\Recipient\LedgerAccount;
use App\Models\Recipient\LedgerAccountWallet;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Log;

// The new recipient wallet address is propagated to every ledger account the wallet is linked to.
readonly class SyncLedgerWalletOnAddressChange
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function handle(RecipientWalletAddressChanged $event): void
    {
        if (!$this->ledgerService->isConfigured()) {
            return;
        }

        $accounts = LedgerAccount::where('wallet_id', $event->wallet->id)->get();

        if ($accounts->isEmpty()) {
            return;
        }

        /**
         * @var LedgerAccount $account
         */
        foreach ($accounts as $account) {

            try {
                $result = $this->ledgerService->updateAccountWallet($account->ledger_id, $event->newAddress);

                if (!($result['ok'] ?? false)) {
                    Log::channel('ledger')->error('SyncLedgerWalletOnAddressChange: failed to update wallet in ledger', [
                        'ledger_account_id' => $account->id,
                        'ledger_id' => $account->ledger_id,
                        'new_address' => $event->newAddress,
                        'response' => $result,
                    ]);

                    continue;
                }

                $body = $result['body'] ?? [];
                if (is_array($body) && !empty($body['id'])) {
                    LedgerAccountWallet::updateOrCreate(
                        ['ledger_account_id' => $account->id, 'wallet_address' => $event->newAddress],
                        ['ledger_wallet_id' => $body['id']]
                    );
                }

                Log::channel('ledger')->info('SyncLedgerWalletOnAddressChange: wallet address updated in ledger', [
                    'ledger_account_id' => $account->id,
                    'old_address' => $event->oldAddress,
                    'new_address' => $event->newAddress,
                ]);

            } catch (\Exception $e) {
                Log::channel('ledger')->info('SyncLedgerWalletOnAddressChange updateAccountWallet error', [
                    'ex' => $e->getMessage(),
                    'account_id' => $account->id,
                    'wallet_id' => $event->wallet->id,
                    'new_address' => $event->newAddress,
                ]);

                continue;
            }
        }
    }
}
