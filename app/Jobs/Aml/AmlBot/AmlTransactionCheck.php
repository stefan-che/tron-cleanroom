<?php

namespace App\Jobs\Aml\AmlBot;

use App\Enums\TransactionStatuses;
use App\Enums\WalletStatuses;
use App\Models\Aml\AmlService;
use App\Models\Setting;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\WalletLog;
use App\Repositories\AmlLogRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\WalletRepository;
use App\Services\Aml\AmlBot\CheckTransaction;
use App\Services\Aml\AmlBot\ReCheck;
use App\Services\Aml\Services\AmlBotService;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class AmlTransactionCheck implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @var Transaction
     */
    private Transaction $transaction;
    private TransactionRepository $transactionRepository;

    public $tries = 10;

    public $timeout = 120;

    /**
     * @param int $id
     * @return void
     */
    public function __construct(readonly int $id)
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->transactionRepository = new TransactionRepository;
        $this->transaction = $this->transactionRepository->getById($this->id);

        $amlService = AmlService::amlBot()->first();
        if (!$amlService) {
            return;
        }

        $amlBotCheck = $this->transaction->checks()
            ->where('service_id', $amlService->id)
            ->first();

        if ($amlBotCheck?->isCompleted()) {
            return;
        }

        $amlLogRepository = new AmlLogRepository;
        $walletLogger = new WalletLoggerService;

        if ($amlBotCheck?->uid) {
            $result = (new ReCheck())->reCheck($amlBotCheck->uid);
        } else {
            $result = (new CheckTransaction())->check($this->transaction->wallet->aml_code,
                $this->transaction->wallet->address,
                $this->transaction->tx_id,
                'deposit',
                Setting::getAmlBotFlowMode(),
            );

            if (($result['data']['uid'] ?? null) && $amlBotCheck) {
                $amlBotCheck->update([
                    'uid' => $result['data']['uid'],
                ]);
            }

            $this->transactionRepository->update($this->transaction, [
                'status' => TransactionStatuses::PROCESS->value,
            ]);
        }

        $amlLogRepository->store($result, $this->transaction->wallet_id, $amlService->id, $this->transaction->id);
        $walletLogger->log(WalletLog::AML_CHECK, $this->transaction->wallet, array_merge(['service' => $amlService->name], $result));

//        Risk values can roughly be split as follows:
//- 0-25% - clean wallet/transaction;
//- 25-75% - medium risk;
//- 75%+ - the wallet/transaction is considered risky.

        if ($result['result'] ?? false) {

            $riscScore = $result['data']['riskscore'] ?? null;

            if (!is_null($riscScore)) {
                AmlBotService::setTransactionRiskOnlyAmlBot($this->transaction, $result['data']);
                return;
            }
        }

        $this->release(1 * 60); // TODO: count attempts and stop if there are too many
    }

    public function uniqueId(): string
    {
        return 'aml_bot_transaction_' . $this->id;
    }
}
