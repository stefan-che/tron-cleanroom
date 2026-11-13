<?php

namespace App\Http\Controllers;

use App\Http\Resources\Wallet\TransactionResource;
use App\Models\Wallet\Transaction;
use App\Repositories\TransactionRepository;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Http;

class TransactionController extends Controller
{
    protected $service;
    protected $repository;

    public function __construct(
        WalletService         $service,
        TransactionRepository $repository,
    )
    {
        $this->service = $service;
        $this->repository = $repository;
    }

    public function runAml(string $hash)
    {
        $transaction = $this->repository->getByTxId($hash);

        if (!$transaction) {
            $this->returnError('Could not find the transaction');
        }

        if ($transaction->aml_file_report_link) {
            return TransactionResource::make($transaction);
        }

        if (!$transaction->isAmlCheck()) {
        }

        $this->service->runAmlTransaction($transaction);

        $transaction->refresh();

        return TransactionResource::make($transaction);
    }

    public function report(string $id)
    {
        /**
         * @var Transaction $transaction
         */
        $transaction = $this->repository->getById($id);

        if (!$transaction) {
            $this->returnError(
                'Could not find such a transaction',
            );
        }

        $latestCheck = $transaction->checks()
            ->whereNotNull('check_finish_at')
            ->whereNotNull('file_report_link')
            ->latest('check_finish_at')
            ->first();

        if (!$latestCheck) {
            $fileUrl = $transaction->aml_file_report_link;

            if (!$fileUrl) {
                $this->returnError(
                    'Cannot download the file, no link',
                );
            }
            $response = Http::get($fileUrl);

            if ($response->successful()) {
                return response()->streamDownload(function () use ($response) {
                    echo $response->body();
                }, basename($fileUrl) . '.pdf');
            }

            return response()->json(['error' => 'Could not download the file'], 500);
        }

        return redirect()->route('aml.report.transaction', $latestCheck->id);
    }
}
