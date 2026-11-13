<?php

namespace App\Jobs\Ai;

use App\Models\Aml\AmlService;
use App\Models\Wallet\WalletAiAmlCheck;
use App\Repositories\WalletRepository;
use App\Services\Ai\AiAmlDataBuilder;
use App\Services\Ai\AiAmlServiceDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends all wallet transactions plus statistics for AI analysis
 * and stores the verdict in wallet_ai_aml_checks.
 *
 * The report is built per wallet. The settings-enabled check is done by
 * the caller (automatic run); a manual run always works.
 */
class AiAmlCheckJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    public $tries = 3;
    public $timeout = 120;

    /**
     * @param int $walletId Wallet the report is built for
     * @param int|null $transactionId Trigger transaction (for the current_transaction block)
     */
    public function __construct(
        public readonly int $walletId,
        public readonly ?int $transactionId = null,
    ) {
    }

    public function handle(): void
    {
        $wallet = (new WalletRepository())->getById($this->walletId);

        if (!$wallet) {
            Log::channel('aml')->warning('AiAmlCheckJob: wallet not found', [
                'wallet_id' => $this->walletId,
            ]);
            return;
        }

        // Trigger transaction: the given one or the wallet's latest
        $current = $this->transactionId
            ? $wallet->transactions->firstWhere('id', $this->transactionId)
            : $wallet->transactions->last();

        $data = (new AiAmlDataBuilder())->build($wallet, $current);

        $check = WalletAiAmlCheck::create([
            'wallet_id' => $wallet->id,
            'status' => WalletAiAmlCheck::STATUS_PENDING,
            'request_payload' => ['data' => $data],
        ]);

        $result = (new AiAmlServiceDispatcher())->analyze($data);

        // The model sometimes returns a placeholder coinkyt block ("service missing from input
        // data, manual investigation needed") despite the prompt. A separate assessment
        // only makes sense when a CoinKYT result actually arrived.
        $hasCoinKyt = $this->hasCoinKytData($data);

        if (!$hasCoinKyt && isset($result['coinkyt'])) {
            Log::channel('aml')->warning('AiAmlCheckJob: AI returned a coinkyt block without service data — discarded', [
                'wallet_id' => $wallet->id,
            ]);

            unset($result['coinkyt']);
        }

        // Full request log { text, params, data } and a prompt snapshot.
        // If the provider was not called (no active service, etc.), store at least the data.
        $requestPayload = $result['request'] ?? ['data' => $data];

        if (!($result['success'] ?? false)) {
            $check->update([
                'ai_service_id' => $result['service_id'] ?? null,
                'prompt' => $result['prompt'] ?? null,
                'request_payload' => $requestPayload,
                'status' => WalletAiAmlCheck::STATUS_FAILED,
                'error' => $result['message'] ?? 'Unknown error',
                'raw_response' => $result['raw'] ?? null,
            ]);

            Log::channel('aml')->error('AiAmlCheckJob: AI analysis error', [
                'wallet_id' => $wallet->id,
                'error' => $result['message'] ?? null,
            ]);
            return;
        }

        $check->update([
            'ai_service_id' => $result['service_id'] ?? null,
            'wallet_color' => $result['wallet_color'] ?? null,
            'risk' => $result['risk'] ?? null,
            'confidence' => $result['confidence'] ?? null,
            'summary' => $result['summary'] ?? null,
            'coinkyt_result' => $result['coinkyt'] ?? null,
            'coinkyt_wallet_color' => $result['coinkyt']['wallet_color'] ?? null,
            'coinkyt_risk' => $result['coinkyt']['risk'] ?? null,
            'prompt' => $result['prompt'] ?? null,
            'request_payload' => $requestPayload,
            'raw_response' => $result['raw'] ?? null,
            'status' => WalletAiAmlCheck::STATUS_COMPLETED,
            'error' => null,
        ]);

        Log::channel('aml')->info('AiAmlCheckJob: AI analysis completed', [
            'wallet_id' => $wallet->id,
            'wallet_color' => $result['wallet_color'] ?? null,
            'risk' => $result['risk'] ?? null,
        ]);
    }

    /**
     * Whether the input data has a CoinKYT result for at least one transaction.
     * `amls` only contains completed checks, so a disabled service never appears there.
     */
    private function hasCoinKytData(array $data): bool
    {
        foreach ($data['transactions'] ?? [] as $transaction) {
            if (!empty($transaction['amls'][AmlService::COINKYT_CODE])) {
                return true;
            }
        }

        return false;
    }
}
