<?php

namespace App\Services\Ai;

use App\Models\Ai\AiService;
use App\Services\Ai\Contracts\AiAmlServiceInterface;
use App\Services\Ai\Dto\AiServiceBalance;
use App\Services\Ai\Services\OpenAiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Dispatcher of AI providers for reviewing AML results.
 *
 * Like EnergyServiceDispatcher: picks the active provider
 * by is_enabled and priority. New providers (e.g. Claude)
 * are added to the constructor and the ai_services table.
 */
class AiAmlServiceDispatcher
{
    /**
     * @var Collection<AiAmlServiceInterface>
     */
    private Collection $services;

    public function __construct()
    {
        $this->services = collect([
            new OpenAiService(),
        ]);
    }

    /**
     * Runs AML analysis via the active AI provider.
     * Prompt and model come from the active ai_services record.
     *
     * @return array analyze() result + 'service_id' key (ai_services id)
     */
    public function analyze(array $data): array
    {
        $serviceModel = $this->getActiveServiceModel();

        if (!$serviceModel) {
            Log::channel('aml')->warning('AiAmlServiceDispatcher: no active AI provider');

            return [
                'success' => false,
                'message' => 'No active AI provider',
                'service_id' => null,
            ];
        }

        if (empty($serviceModel->prompt)) {
            Log::channel('aml')->warning('AiAmlServiceDispatcher: prompt not set for provider', [
                'code' => $serviceModel->code,
            ]);

            return [
                'success' => false,
                'message' => 'Prompt not set for AI provider: ' . $serviceModel->name,
                'service_id' => $serviceModel->id,
            ];
        }

        $service = $this->getServiceByCode($serviceModel->code);

        if (!$service) {
            Log::channel('aml')->warning('AiAmlServiceDispatcher: no implementation for provider', [
                'code' => $serviceModel->code,
            ]);

            return [
                'success' => false,
                'message' => 'No AI provider implementation: ' . $serviceModel->code,
                'service_id' => $serviceModel->id,
            ];
        }

        Log::channel('aml')->info('AiAmlServiceDispatcher: starting analysis', [
            'service' => $serviceModel->code,
            'model' => $serviceModel->model,
        ]);

        $result = $service->analyze($serviceModel->prompt, $data, $serviceModel->model, $serviceModel->api_key);
        $result['service_id'] = $serviceModel->id;

        return $result;
    }

    /**
     * Account balances of all enabled AI providers.
     *
     * @return Collection<int, AiServiceBalance>
     */
    public function balances(): Collection
    {
        return AiService::where('is_enabled', true)
            ->orderBy('priority')
            ->get()
            ->map(fn(AiService $model) => $this->balanceFor($model));
    }

    /**
     * Account balance of a provider: top-up from the card minus
     * spend since the top-up date via the provider's Admin API.
     */
    public function balanceFor(AiService $model): AiServiceBalance
    {
        $service = $this->getServiceByCode($model->code);

        if (!$service) {
            return AiServiceBalance::notConfigured(
                $model->code,
                $model->name,
                'no AI provider implementation'
            );
        }

        if ($model->balance_topup === null) {
            return AiServiceBalance::notConfigured(
                $model->code,
                $model->name,
                'top-up amount not entered'
            );
        }

        return $service->getSpentSince($model->admin_api_key, $model->balance_topup_at)
            ->withTopup($model->balance_topup, $model->balance_topup_at, $model->balance_threshold);
    }

    /**
     * Active provider model with the highest priority.
     */
    private function getActiveServiceModel(): ?AiService
    {
        return AiService::where('is_enabled', true)
            ->orderBy('priority')
            ->first();
    }

    private function getServiceByCode(string $code): ?AiAmlServiceInterface
    {
        return $this->services->first(fn (AiAmlServiceInterface $s) => $s->getCode() === $code);
    }

    public function getAllServices(): Collection
    {
        return $this->services;
    }
}
