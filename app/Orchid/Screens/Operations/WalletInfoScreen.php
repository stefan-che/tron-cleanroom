<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Operations;

use App\Enums\WalletStatuses;
use App\Helpers\UserHelper;
use App\Jobs\Ai\AiAmlCheckJob;
use App\Jobs\Aml\AmlBot\AmlTransactionCheck;
use App\Jobs\Wallet\ActivateWallet;
use App\Jobs\Wallet\AddEnergy;
use App\Jobs\Wallet\SendUsdt;
use App\Jobs\Wallet\Trx\AddTrxWallet;
use App\Jobs\Wallet\Trx\SendToTrxWallet;
use App\Jobs\Wallet\Trx\SendTrx;
use App\Jobs\Wallet\Trx\UpdateWalletsBalance;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Role;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletLog;
use App\Orchid\Layouts\Operations\AddressToFormLayout;
use App\Orchid\Layouts\Operations\CommentFormLayout;
use App\Orchid\Layouts\Operations\SendTrxFormLayout;
use App\Orchid\Layouts\Operations\WalletActionsBlock;
use App\Orchid\Layouts\Operations\WalletInfoBlock;
use App\Orchid\Layouts\Operations\WalletLogList;
use App\Orchid\Layouts\Operations\WalletTestingStatusesLayout;
use App\Orchid\Layouts\Operations\TransactionAmlChecksList;
use App\Orchid\Layouts\Operations\WalletAiChecksList;
use App\Orchid\Layouts\Operations\WalletCoinKytMonitoringList;
use App\Orchid\Layouts\Operations\WalletTransactionList;
use App\Orchid\Layouts\Operations\WalletWebhookList;
use App\Models\Wallet\Transaction;
use App\Repositories\Recipient\RecipientWalletRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\WalletRepository;
use App\Rules\TronAddressRule;
use App\Services\Aml\CoinKyt\Monitoring\MonitoringService;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletLoggerService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class WalletInfoScreen extends Screen
{

    /**
     * @var Wallet
     */
    public $wallet;

    protected $walletService;

    public function __construct()
    {
        $this->walletService = new WalletService;
    }

    /**
     * @return iterable|null
     */
    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_OPERATIONS,
        ];
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(Wallet $wallet): iterable
    {
        return [
            'wallet' => $wallet,
            'transactions' => $wallet->transactions,
            'logs' => $wallet->logs,
            'webhooks' => $wallet->webhooks,
            'ai_checks' => $wallet->aiAmlChecks()->with('service')->get(),
            'coinkyt_events' => $wallet->coinKytMonitoringEvents,
        ];
    }

    /**
     * The name is displayed on the user's screen and in the headers
     */
    public function name(): ?string
    {
        return $this->wallet->exists ? (sprintf('Wallet: %s %s', $this->wallet->isTestWallet() ? '[TEST]' : '',  $this->wallet->address,)) : '';
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return '';
    }

    /**
     * Button commands.
     *
     * @return array
     */
    public function commandBar(): array
    {
        return [

        ];
    }

    /**
     * Views.
     *
     * @return Layout[]
     */
    public function layout(): array
    {
        $tabs = [
            'Details' => Layout::block(WalletInfoBlock::class)->vertical(),
            'Transactions' => Layout::block(WalletTransactionList::class)->vertical(),
            'Logs' => Layout::block(WalletLogList::class)->vertical(),
            'AI responses' => Layout::block(WalletAiChecksList::class)->vertical(),
            'CoinKYT monitoring' => Layout::block(WalletCoinKytMonitoringList::class)->vertical(),
            'Actions' => Layout::block(WalletActionsBlock::class)->vertical(),
        ];

        if ($this->wallet->isTestWallet()) {
            $tabs['Testing'] = Layout::block(WalletTestingStatusesLayout::class)->vertical();
        }

        if (UserHelper::isPermSetWallet()) {
            $tabs['Webhooks'] = Layout::block(WalletWebhookList::class)->vertical();
        }

        return [
            Layout::modal('addressToModal', [
                AddressToFormLayout::class,
            ])
                ->async('asyncGetWallet')
                ->applyButton('Save')
                ->closeButton('Close'),

            Layout::modal('walletCommentModal', [
                CommentFormLayout::class,
            ])
                ->async('asyncGetWallet')
                ->applyButton('Save')
                ->closeButton('Close'),

            Layout::modal('sendTrxToTrxWalletModal', [
                SendTrxFormLayout::class,
            ])
                ->async('asyncGetWallet')
                ->applyButton('Send')
                ->closeButton('Close'),

            Layout::modal('transactionAmlChecksModal', [
                TransactionAmlChecksList::class,
            ])
                ->async('asyncGetTransactionChecks')
                ->withoutApplyButton()
                ->closeButton('Close')
                ->size(Modal::SIZE_LG),

            Layout::tabs($tabs),
        ];
    }

    /**
     * @param Wallet $wallet
     * @return array
     */
    public function asyncGetWallet(Wallet $wallet): iterable
    {
        return [
            'wallet' => $wallet,
        ];
    }

    /**
     * @param Transaction $transaction
     * @return array
     */
    public function asyncGetTransactionChecks(Transaction $transaction): iterable
    {
        $transaction->load('wallet');

        return [
            'wallet' => $transaction->wallet,
            'transaction_aml_checks' => $transaction->checks()->with('service')->orderBy('id')->get(),
        ];
    }

    public function sendWhiteUsdt(Wallet $wallet)
    {
        $this->walletService->setWhiteWallet($wallet);
    }

    public function sendGrayUsdt(Wallet $wallet)
    {
        $this->walletService->setGrayWallet($wallet);
    }

    public function sendYellowUsdt(Wallet $wallet)
    {
        $this->walletService->setYellowWallet($wallet);
    }

    public function requestEnergy(Wallet $wallet)
    {
        AddEnergy::dispatchSync($wallet->id); //dispatch
        Toast::info('Request sent')->delay(1000);
    }

    public function activateWallet(Wallet $wallet)
    {
        ActivateWallet::dispatchSync($wallet->id); //dispatch
        Toast::info('Request sent')->delay(1000);
    }

    public function runAMLCheck(Request $request)
    {
        $transaction = (new TransactionRepository())->getById((int)$request->id);

        if (!$transaction) {
            Toast::error('Transaction not found')->delay(1000);
            return;
        }

        (new TransactionRepository())->update($transaction, [
            'aml_uid' => null,
            'aml_risk_score' => null,
            'aml_file_report_link' => null,
        ]);

        $transaction->checks()->delete();

        app(WalletService::class)->runAmlTransaction($transaction);

        Toast::info('Request sent')->delay(1000);
    }

    public function runRefreshAMLCheck(Request $request)
    {
        $transaction = (new TransactionRepository())->getById((int)$request->id);

        if (!$transaction) {
            Toast::error('Transaction not found')->delay(1000);
            return;
        }

        Toast::error('This feature is under development')->delay(1000);
    }

    public function sendUsdt(Wallet $wallet)
    {
        SendUsdt::dispatch($wallet->id);
        Toast::info('Request sent')->delay(1000);
    }

    /**
     * Manually run an AI AML check for the wallet (in the background, via the queue).
     */
    public function runAiAmlCheck(Wallet $wallet): void
    {
        AiAmlCheckJob::dispatch($wallet->id);
        Toast::info('AI check started in the background')->delay(1000);
    }

    /**
     * Add the wallet to CoinKYT monitoring.
     */
    public function addCoinKytMonitoring(Wallet $wallet): void
    {
        try {
            app(MonitoringService::class)->addWallet($wallet);
            Toast::info('Wallet added to CoinKYT monitoring')->delay(1000);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage())->delay(3000);
        }
    }

    /**
     * Remove the wallet from CoinKYT monitoring.
     */
    public function removeCoinKytMonitoring(Wallet $wallet): void
    {
        try {
            app(MonitoringService::class)->removeWallet($wallet);
            Toast::info('Wallet removed from CoinKYT monitoring')->delay(1000);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage())->delay(3000);
        }
    }

    public function sendTrx(Wallet $wallet)
    {
        SendTrx::dispatchSync($wallet->id);
        Toast::info('Request sent')->delay(1000);
    }

    public function refreshTrxBalance(Wallet $wallet)
    {
        UpdateWalletsBalance::dispatchSync($wallet->id);
        Toast::info('Request sent')->delay(1000);
    }

    public function refreshUsdtBalance(Wallet $wallet)
    {
        $this->walletService->reCalcUsdtBalance($wallet);
        Toast::info('Request sent')->delay(1000);
    }

    public function refreshEnergy(Wallet $wallet): void
    {
        $tronService = new TronService();
        $energy = $tronService->getAvailableEnergy($wallet->address);

        $wallet->update([
            'amount_energy' => max($energy, 0),
        ]);

        Toast::info('Energy amount updated')->delay(1000);
    }

    public function setStatusNew(Wallet $wallet)
    {
        $this->walletService->setStatusNew($wallet);

        Toast::info('Status set')->delay(1000);
    }

    public function setStatusCompleted(Wallet $wallet)
    {
        $this->walletService->setStatusCompleted($wallet);

        Toast::info('Status set')->delay(1000);
    }

    public function setStatusTransferMoneyProcessFinish(Wallet $wallet)
    {
        $this->walletService->setStatusTransferMoneyProcessFinish($wallet);

        Toast::info('Status set')->delay(1000);
    }

    public function setStatusCheckTransactionFinish(Wallet $wallet): void
    {
        $this->walletService->setStatusCheckTransactionFinish($wallet);

        Toast::info('Status set')->delay(1000);
    }

    public function sendTrxToTrxWallet(Wallet $wallet, Request $request)
    {
        SendToTrxWallet::dispatchSync($wallet->id, $request->to);
        Toast::info('Request sent')->delay(1000);
    }

    public function addTrxWallet(Wallet $wallet, Request $request)
    {
        AddTrxWallet::dispatchSync($wallet->id, Wallet::ADDITIONAL_TRX); //dispatch
        Toast::info('Request sent')->delay(1000);
    }

    public function setComment(Wallet $wallet, Request $request)
    {
        $data = $request->collect('wallet')->toArray();

        $wallet->fill([
            'comment' => $data['comment'],
        ]);

        $wallet->save();

        Toast::info('Comment added')->delay(1000);
    }

    public function setAddressTo(Wallet $wallet, Request $request)
    {
        $validated = $request->validate([
            'wallet.address_to_recipients' => [
                'required_without:wallet.address_to_custom',
                'nullable',
                'string',
                'max:36',
                new TronAddressRule(),
            ],
            'wallet.address_to_custom' => [
                'required_without:wallet.address_to_recipients',
                'nullable',
                'string',
                'max:36',
                new TronAddressRule(),
            ],
            'wallet.code_2fa' => [
                Rule::requiredIf(static fn() => !empty($request->input('wallet.address_to_custom'))),
                'nullable',
                'string',
                'min:6',
                'max:6',
            ],
        ]);

        if (!empty($validated['wallet']['code_2fa'])) {
            $google2fa = app('pragmarx.google2fa');

            $valid = $google2fa->verifyKey(
                UserHelper::get()->two_factor_secret, $validated['wallet']['code_2fa']
            );

            if (!$valid) {
                throw ValidationException::withMessages([
                    'wallet.code_2fa' => 'Invalid two-factor authentication code'
                ]);
            }
        }

        $isCustom = !empty($validated['wallet']['address_to_custom']);
        $addressTo = trim($validated['wallet']['address_to_custom'] ?? $validated['wallet']['address_to_recipients'] ?? '');

        $oldAddress = $wallet->address_to;

        $fill = ['address_to' => $addressTo];

        //Buy: a free-form address = refund to the sender (BLACK),
        //an address from the recipient's wallets uses its risk tier. The tier is stored; SendUsdt checks it.
        if ($wallet->isOperationBuy()) {
            $type = $isCustom
                ? RecipientWallet::TYPE_BLACK
                : (int)(new RecipientWalletRepository)->getByAddress($addressTo)?->type;

            if (!$type || !$this->walletService->isAllowedDestination($wallet, $type, $addressTo)) {
                throw ValidationException::withMessages([
                    'wallet.address_to_recipients' => 'For buy, the address must be one of this recipient\'s wallets with the matching risk tier; a free-form address is only for refunds (BLACK)',
                ]);
            }

            $fill['type'] = $type;
        }

        $wallet->fill($fill);

        $wallet->save();

        (new WalletLoggerService)->log(WalletLog::SET_WALLET_TO, $wallet, [
            'old_address' => $oldAddress,
            'new_address' => $addressTo,
            'email' => UserHelper::get()->email,
        ]);

        Toast::info('Address set')->delay(1000);
    }


    public function setStatus(Wallet $wallet, Request $request)
    {
        $st = (int)$request->get('status');
        $isNew = $st === WalletStatuses::NEW->value;
        $isProcessFinish = $st === WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value;

        $data = [
            'status' => $st,
            'created_at' => $isNew ? now() : $wallet->created_at,
            'has_error' => false,
        ];

        if ($wallet->isTestWallet() && $isProcessFinish) {
            $data += [
                'last_transfer_amount' => $wallet->transactions()->sum('amount'),
                'amount_energy' => 0,
                'amount' => 0,
                'send_tx_hash' => 'fake_' . bin2hex(random_bytes(8)),
            ];
        }

        $wallet->update($data);

        if ($isNew) {
        }

        Toast::info('Status set')->delay(1000);
    }

    public function setTest(Wallet $wallet, Request $request)
    {
        $wallet->update([
            'is_test' => true,
        ]);

        Toast::info('Test status set')->delay(3000);
    }

}
