<?php

namespace App\Jobs\Aml;

use App\Models\Wallet\Transaction;
use App\Repositories\TransactionRepository;
use App\Services\Aml\AmlServiceDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class DispatchTransactionChecks implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, InteractsWithQueue;

    private Transaction $transaction;

    public $tries = 1;
    public $timeout = 60;

    /**
     * @param int $transactionId
     */
    public function __construct(private readonly int $transactionId)
    {
        $transactionRepository = new TransactionRepository();
        $this->transaction = $transactionRepository->getById($this->transactionId);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $dispatcher = app(AmlServiceDispatcher::class);
        $dispatcher->dispatchTransactionChecks($this->transaction);
    }

    public function uniqueId(): string
    {
        return 'dispatch_transaction_checks_' . $this->transactionId;
    }
}



