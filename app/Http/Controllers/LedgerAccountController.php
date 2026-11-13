<?php

namespace App\Http\Controllers;

use App\Models\Recipient\LedgerAccount;
use App\Models\Recipient\LedgerAccountWallet;
use App\Rules\TronAddressRule;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Proxy to the external ledger, mirrored in ledger_account_wallets; see docs/LEDGER_INTEGRATION.md
class LedgerAccountController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    )
    {
    }

    public function linkWallet(Request $request, string $ledgerId): JsonResponse
    {
        $this->ensureConfigured();

        $request->validate([
            'wallet_address' => ['required', 'string', new TronAddressRule()],
            'network' => ['nullable', 'string'],
        ]);

        $account = LedgerAccount::where('ledger_id', $ledgerId)->first();
        if (!$account) {
            $this->returnError('Ledger account not found');
        }

        $walletAddress = $request->string('wallet_address')->toString();
        $network = $request->string('network', LedgerAccountWallet::NETWORK_TRON)->toString();

        $result = $this->ledgerService->updateAccountWallet($account->ledger_id, $walletAddress, $network);

        if (!$result['ok']) {
            return $this->ledgerError($result);
        }

        $body = $result['body'];
        if (is_array($body) && !empty($body['id'])) {
            LedgerAccountWallet::updateOrCreate(
                ['ledger_account_id' => $account->id, 'wallet_address' => $walletAddress],
                ['ledger_wallet_id' => $body['id'], 'network' => $network],
            );
        }

        return response()->json([
            'success' => true,
            'ledger_wallet_id' => $body['id'] ?? null,
            'wallet_address' => $walletAddress,
            'network' => $network,
        ]);
    }

    public function unlinkWallet(string $ledgerId, string $ledgerWalletId): JsonResponse
    {
        $this->ensureConfigured();

        $account = LedgerAccount::where('ledger_id', $ledgerId)->first();
        if (!$account) {
            $this->returnError('Ledger account not found');
        }

        $result = $this->ledgerService->deleteAccountWallet($account->ledger_id, $ledgerWalletId);

        if (!($result['ok'] ?? false)) {
            return $this->ledgerError($result);
        }

        LedgerAccountWallet::where('ledger_account_id', $account->id)
            ->where('ledger_wallet_id', $ledgerWalletId)
            ->delete();

        return response()->json(['success' => true]);
    }

    private function ensureConfigured(): void
    {
        if (!$this->ledgerService->isConfigured()) {
            $this->returnError('Ledger integration is not configured', Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    private function ledgerError(array $result): JsonResponse
    {
        $body = $result['body'];

        return response()->json([
            'success' => false,
            'message' => (is_array($body) ? ($body['error'] ?? null) : null) ?? 'Ledger API error',
            'details' => $body,
        ], $result['status'] ?: Response::HTTP_BAD_GATEWAY);
    }
}
