<?php

namespace App\Http\Controllers;

use App\Enums\WalletStatuses;
use App\Helpers\TelegramHelper;
use App\Http\Requests\Wallet\ChangeAddressToRequest;
use App\Http\Requests\Wallet\SendRequest;
use App\Http\Requests\Wallet\StoreRequest;
use App\Http\Requests\Wallet\ViewRequest;
use App\Http\Resources\Wallet\WalletResource;
use App\Jobs\Wallet\SendWalletToListenerService;
use App\Jobs\Wallet\SendWalletToRecursiveAml;
use App\Models\Recipient\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected $service;
    protected $walletRepository;

    public function __construct(
        WalletService    $service,
        WalletRepository $walletRepository,
    )
    {
        $this->service = $service;
        $this->walletRepository = $walletRepository;
    }

    public function generate(StoreRequest $request)
    {
        $wallet = $this->service->generateWallet(
            $request->blockchain,
            $request->external_id,
            $request->recipient_id,
            (bool)$request->is_test,
            $request->getOperationType(),
            $request->getAddressTo(),
        );

        if (!$wallet) {

            TelegramHelper::sendMessage('❌ Could not create the wallet! Check the logs for the reason');

            $this->returnError(
                'Could not create the wallet',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        SendWalletToRecursiveAml::dispatch($wallet->id);
        SendWalletToListenerService::dispatch($wallet->id);

        return WalletResource::make($wallet);
    }

    public function status(ViewRequest $request)
    {
        $wallet = $this->walletRepository->getByAddress($request->address);

        if (!$wallet) {
            $this->returnError(
                'Could not find such a wallet',
            );
        }

        return WalletResource::make($wallet);
    }

    public function report(string $address)
    {
        $wallet = $this->walletRepository->getByAddress($address);

        if (!$wallet) {
            $this->returnError(
                'Could not find such a wallet',
            );
        }

        $amlAddress = \App\Models\Aml\AmlAddress::where('address', $address)
            ->where('blockchain_type', $wallet->blockchain_type)
            ->first();

        if (!$amlAddress) {
            $fileUrl = $wallet->aml_file_report_link;

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

        $latestCheck = $amlAddress->checks()
            ->whereNotNull('check_finish_at')
            ->whereNotNull('aml_file_report_link')
            ->latest('check_finish_at')
            ->first();

        if (!$latestCheck) {
            $this->returnError(
                'AML report not found',
            );
        }

        return redirect()->route('aml.report.address', $latestCheck->id);
    }

    public function send(SendRequest $request)
    {
        $wallet = $this->walletRepository->getByAddress($request->address);

        if (!$wallet) {
            $this->returnError(
                'Could not find such a wallet',
            );
        }

        $result = $this->service->send($wallet,
            Wallet::getTypeByNameEng($request->type),
            trim($request->to_address),
            $request->getAmount(),
        );

        if (!$result) {
            $this->returnError(
                'Could not send funds, check the logs for the reason',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return WalletResource::make($wallet);
    }

    public function activate(string $address)
    {
        $wallet = $this->walletRepository->getByAddress(trim($address));

        if (!$wallet) {
            $this->returnError(
                'Could not find such a wallet',
            );
        }

        if (!$this->service->setStatusNew($wallet)) {
            $this->returnError(
                'Could not activate the wallet',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $wallet->refresh();

        return WalletResource::make($wallet);
    }

    /**
     * Change the recipient address of funds; payout (sell) operations only
     */
    public function changeAddressTo(ChangeAddressToRequest $request)
    {
        $wallet = $request->getWallet();

        $result = $this->service->changeAddressToForSell($wallet, $request->getAddressTo());

        if (!$result) {
            $this->returnError(
                'Could not change the recipient address, check the logs for the reason',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $wallet->refresh();

        return WalletResource::make($wallet);
    }

    public function getList(Request $request)
    {
        $perPage = (int)($request->query('per_page', 30));

        $wallets = $this->walletRepository
            ->paginateByFilter([
                'status' => $request->query('status'),
                'recipient_id' => $request->query('recipient_id'),
            ], $perPage)
            ->appends($request->query());

        return WalletResource::collection($wallets);
    }
}
