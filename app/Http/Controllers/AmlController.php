<?php

namespace App\Http\Controllers;

use App\Http\Requests\Aml\CheckRequest;
use App\Http\Requests\Aml\ViewRequest;
use App\Http\Resources\AmlAddress\AmlAddressResource;
use App\Models\Aml\AmlService;
use App\Models\Wallet\WalletLog;
use App\Repositories\Aml\AmlAddressRepository;
use App\Repositories\AmlLogRepository;
use App\Repositories\TransactionRepository;
use App\Services\Aml\AmlBot\Address\AmlAddressService;
use App\Services\Aml\Services\AmlBotService;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmlController extends Controller
{
    protected $service;
    protected $amlAddressRepository;
    protected $transactionRepository;

    public function __construct(
        AmlAddressService     $service,
        AmlAddressRepository  $amlAddressRepository,
        TransactionRepository $transactionRepository,
    )
    {
        $this->service = $service;
        $this->amlAddressRepository = $amlAddressRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function check(CheckRequest $request)
    {
        $amlAddress = $this->amlAddressRepository->getByBlockChainAddress($request->address, $request->blockchain);

        if ($amlAddress && $amlAddress->is_test) {
            return AmlAddressResource::make($amlAddress);
        }

        $amlAddress = $this->service->check($request->blockchain, $request->address, (bool)$request->is_test);

        if (!$amlAddress) {
            $this->returnError('Could not check the address', Response::HTTP_ACCEPTED);
        }

        return AmlAddressResource::make($amlAddress);
    }

    public function status(ViewRequest $request)
    {
        $amlAddress = $this->amlAddressRepository->getByBlockChainAddress($request->address, $request->blockchain);

        if (!$amlAddress) {
            $this->returnError('Could not find such an address');
        }

        return AmlAddressResource::make($amlAddress);
    }

    public function report(string $address)
    {
        $amlAddress = $this->amlAddressRepository->getByAddress($address);

        if (!$amlAddress) {
            $this->returnError('Could not find such an address');
        }

        $latestCheck = $amlAddress->checks()
            ->whereNotNull('check_finish_at')
            ->whereNotNull('aml_file_report_link')
            ->latest('check_finish_at')
            ->first();

        if (!$latestCheck) {
            $fileUrl = $amlAddress->aml_file_report_link;

            if (!$fileUrl) {
                $this->returnError('Cannot download the file, no link');
            }
            $response = Http::get($fileUrl);

            if ($response->successful()) {
                return response()->streamDownload(function () use ($response) {
                    echo $response->body();
                }, basename($fileUrl) . '.pdf');
            }

            return response()->json(['error' => 'Could not download the file'], 500);
        }

        return redirect()->route('aml.report.address', $latestCheck->id);
    }

    /**
     * Webhook that receives AML check results
     */
    public function webhook(Request $request)
    {
        $tonce = $request->header('x-tonce');
        $signature = $request->header('x-signature');

        $data = $request->post();

        $logData = [
            'timestamp' => now()->toDateTimeString(),
            'headers' => [
                'x-tonce' => $tonce,
                'x-signature' => $signature,
                'all_headers' => $request->headers->all(),
            ],
            'postParams' => $data,
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
        ];

        Log::channel('aml')->info('AML Webhook received', $logData);

        if ($tonce && $signature) {
            $accessKey = config('services.aml.access_key');
            $expectedSignature = hash_hmac('sha256', $tonce, $accessKey);
            $riscScore = $data['riskscore'] ?? null;
            $txId = $data['tx'] ?? '';

            if ($signature === $expectedSignature) {
                if (!is_null($riscScore) && $transaction = $this->transactionRepository->getByTxId($txId)) {
                    $amlService = AmlService::amlBot()->first();
                    app(AmlLogRepository::class)->store(['data' => $data], $transaction->wallet_id, $amlService->id, $transaction->id);
                    app(WalletLoggerService::class)->log(WalletLog::AML_CHECK, $transaction->wallet, $data);

                    AmlBotService::setTransactionRiskOnlyAmlBot($transaction, $data);
                }
            } else {
                Log::channel('aml')->warning('AML Webhook: Invalid signature', [
                    'received' => $signature,
                    'expected' => $expectedSignature,
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }
}
