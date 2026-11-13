<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Settings;

use App\Models\Role;
use App\Models\Setting;
use App\Models\Ai\AiService;
use App\Models\Aml\AmlService;
use App\Models\Energy\EnergyService;
use App\Models\Tron\TronNode;
use App\Orchid\Layouts\Settings\AiAmlSettingsLayout;
use App\Orchid\Layouts\Settings\AiServiceFormLayout;
use App\Orchid\Layouts\Settings\AiServicesLayout;
use App\Orchid\Layouts\Settings\AmlServicesLayout;
use App\Orchid\Layouts\Settings\EnergyServicesLayout;
use App\Orchid\Layouts\Settings\FormValueLayout;
use App\Orchid\Layouts\Settings\ListLayout;
use App\Orchid\Layouts\Settings\NewTronNodeFormLayout;
use App\Orchid\Layouts\Settings\TronNodeFormLayout;
use App\Orchid\Layouts\Settings\TronNodesLayout;
use App\Services\Ai\AiAmlServiceDispatcher;
use App\Services\Tron\TronNodeDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SettingsScreen extends Screen
{

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'settings' => Setting::filters()->paginate(30),
            'amlServices' => AmlService::all(),
            'energyServices' => EnergyService::orderBy('priority')->get(),
            'aiServices' => AiService::orderBy('priority')->get(),
            'tronNodes' => TronNode::byPriority()->get(),
            'aiSettings' => [
                'enabled' => Setting::isAiAmlCheckEnabled(),
            ],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Settings';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return '';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_SETTINGS,
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('AI AML settings')
                ->icon('robot')
                ->modal('aiAmlSettingsModal')
                ->method('saveAiSettings'),

            ModalToggle::make('Add TRON node')
                ->icon('globe')
                ->modal('newTronNodeModal')
                ->method('createTronNode'),

            Button::make('Clear cache')
                ->icon('refresh')
                ->method('flushCaches'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::tabs([
                'Settings' => ListLayout::class,
                'AML providers' => AmlServicesLayout::class,
                'Energy providers' => EnergyServicesLayout::class,
                'AI providers' => AiServicesLayout::class,
                'TRON nodes' => TronNodesLayout::class,
            ]),

            Layout::modal('settingsValueModal', [
                FormValueLayout::class,
            ])
                ->async('asyncGetSetting')
                ->applyButton('Save')
                ->closeButton('Close'),

            Layout::modal('aiAmlSettingsModal', [
                AiAmlSettingsLayout::class,
            ])
                ->applyButton('Save')
                ->closeButton('Close'),

            Layout::modal('newTronNodeModal', [
                NewTronNodeFormLayout::class,
            ])
                ->applyButton('Add')
                ->closeButton('Close'),

            Layout::modal('tronNodeModal', [
                TronNodeFormLayout::class,
            ])
                ->async('asyncGetTronNode')
                ->applyButton('Save')
                ->closeButton('Close'),

            Layout::modal('aiServiceModal', [
                AiServiceFormLayout::class,
            ])
                ->async('asyncGetAiService')
                ->applyButton('Save')
                ->closeButton('Close')
                ->size(\Orchid\Screen\Layouts\Modal::SIZE_LG),
        ];
    }

    /**
     * @param AiService $service
     * @return array
     */
    public function asyncGetAiService(AiService $service): iterable
    {
        // Do not send current keys to the browser
        $service->api_key = '';
        $service->admin_api_key = '';

        return [
            'aiService' => $service,
        ];
    }

    /**
     * Save an AI provider card (model, prompt, active flag).
     */
    public function saveAiService(AiService $service, Request $request): void
    {
        $data = $request->collect('aiService')->toArray();

        $isEnabled = !empty($data['is_enabled']);

        if ($isEnabled) {
            AiService::where('id', '!=', $service->id)
                ->where('is_enabled', true)
                ->update(['is_enabled' => false]);
        }

        $attributes = [
            'model' => $data['model'] ?? $service->model,
            'prompt' => $data['prompt'] ?? '',
            'is_enabled' => $isEnabled,
            // Empty field = balance monitoring for this provider is off
            'balance_topup' => ($data['balance_topup'] ?? '') !== '' ? (float) $data['balance_topup'] : null,
            'balance_topup_at' => ($data['balance_topup_at'] ?? '') !== '' ? $data['balance_topup_at'] : null,
            'balance_threshold' => ($data['balance_threshold'] ?? '') !== '' ? (float) $data['balance_threshold'] : null,
        ];

        // Keys are updated only if a new one is entered (empty field = keep)
        if (!empty($data['api_key'])) {
            $attributes['api_key'] = $data['api_key'];
        }

        if (!empty($data['admin_api_key'])) {
            $attributes['admin_api_key'] = $data['admin_api_key'];
        }

        $service->update($attributes);

        Toast::info("AI provider {$service->name} saved");
    }


    /**
     * Add a node
     */
    public function createTronNode(Request $request): void
    {
        $data = $request->collect('newTronNode')->toArray();

        $request->validate([
            'newTronNode.name' => 'required|string|max:255',
            'newTronNode.code' => 'required|string|max:255|unique:tron_nodes,code',
            'newTronNode.full_node' => 'required|string|max:255',
        ], [], [
            'newTronNode.name' => 'name',
            'newTronNode.code' => 'code',
            'newTronNode.full_node' => 'full node address',
        ]);

        $node = TronNode::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'] ?? TronNode::TYPE_OWN,
            'full_node' => $data['full_node'],
            'solidity_node' => ($data['solidity_node'] ?? '') !== '' ? $data['solidity_node'] : null,
            'event_server' => ($data['event_server'] ?? '') !== '' ? $data['event_server'] : null,
            'api_key' => ($data['api_key'] ?? '') !== '' ? $data['api_key'] : null,
            'priority' => (int)($data['priority'] ?? 1),
            'is_enabled' => !empty($data['is_enabled']),
            'is_available' => true,
        ]);

        Toast::info("Node {$node->name} added");
    }

    /**
     * Delete a node
     */
    public function deleteTronNode(TronNode $node): void
    {
        $name = $node->name;

        $node->delete();

        Toast::info("Node {$name} deleted");
    }

    /**
     * @param TronNode $node
     * @return array
     */
    public function asyncGetTronNode(TronNode $node): iterable
    {
        // Do not send the current key to the browser
        $node->api_key = '';

        return [
            'tronNode' => $node,
        ];
    }

    /**
     * Save a node: addresses, priority and statuses
     */
    public function saveTronNode(TronNode $node, Request $request): void
    {
        $data = $request->collect('tronNode')->toArray();

        $attributes = [
            'name' => $data['name'] ?? $node->name,
            'full_node' => $data['full_node'] ?? $node->full_node,
            'solidity_node' => ($data['solidity_node'] ?? '') !== '' ? $data['solidity_node'] : null,
            'event_server' => ($data['event_server'] ?? '') !== '' ? $data['event_server'] : null,
            'priority' => (int)($data['priority'] ?? $node->priority),
            'is_enabled' => !empty($data['is_enabled']),
            'is_available' => !empty($data['is_available']),
            'description' => $data['description'] ?? $node->description,
        ];

        // The key is updated only if a new one is entered (empty field = keep)
        if (!empty($data['api_key'])) {
            $attributes['api_key'] = $data['api_key'];
        }

        if ($attributes['is_available'] && !$node->is_available) {
            $attributes['unavailable_at'] = null;
            $attributes['last_error'] = null;
        }

        $node->update($attributes);

        Toast::info("Node {$node->name} saved");
    }

    /**
     * Enable/disable a node
     */
    public function toggleTronNode(TronNode $node): void
    {
        try {
            $node->update([
                'is_enabled' => !$node->is_enabled,
            ]);

            $status = $node->is_enabled ? 'enabled' : 'disabled';
            Toast::info("Node {$node->name} {$status}");
        } catch (\Throwable $e) {
            Toast::error('Failed to change node status: ' . $e->getMessage());
        }
    }

    /**
     * Check a node now: fetch the latest block and update the status
     */
    public function checkTronNode(TronNode $node): void
    {
        $lastBlock = app(TronNodeDispatcher::class)->getLastBlock($node);

        if (!$lastBlock) {
            $node->markUnavailable('No response from node (manual check)');
            Toast::error("Node {$node->name} is not responding");

            return;
        }

        $node->markAvailable($lastBlock);

        Toast::info("Node {$node->name} is available, latest block: {$lastBlock}");
    }

    /**
     * @param Setting $setting
     * @return array
     */
    public function asyncGetSetting(Setting $setting): iterable
    {
        return [
            'setting' => $setting,
        ];
    }

    public function saveSetting(Setting $setting, Request $request)
    {
        $setting
            ->fill($request->collect('setting')->toArray())
            ->save();

        $setting->refresh();

        Toast::info(__('Setting was saved.'));

        return redirect()->route('platform.settings.list');
    }

    /**
     * Clear caches and restart queues.
     */
    public function flushCaches(): void
    {
        try {
            Artisan::call('optimize');
            Artisan::call('queue:restart');
            Toast::info('Caches cleared, queues restarted.');
        } catch (\Throwable $e) {
            Toast::error('Operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Enable/disable an AML provider
     */
    public function toggleAmlService(AmlService $service): void
    {
        try {
            $service->update([
                'is_enabled' => !$service->is_enabled,
            ]);

            $status = $service->is_enabled ? 'enabled' : 'disabled';
            Toast::info("Provider {$service->name} {$status}");
        } catch (\Throwable $e) {
            Toast::error('Failed to change provider status: ' . $e->getMessage());
        }
    }

    /**
     * Enable/disable an energy provider
     * Only one provider can be active at a time
     */
    public function toggleEnergyService(EnergyService $service): void
    {
        try {
            $newStatus = !$service->is_enabled;
            
            if ($newStatus) {
                // When enabling a provider, disable all others first
                EnergyService::where('id', '!=', $service->id)
                    ->where('is_enabled', true)
                    ->update(['is_enabled' => false]);
            }
            
            $service->update([
                'is_enabled' => $newStatus,
            ]);

            $status = $service->is_enabled ? 'enabled' : 'disabled';
            Toast::info("Energy provider {$service->name} {$status}");
        } catch (\Throwable $e) {
            Toast::error('Failed to change energy provider status: ' . $e->getMessage());
        }
    }

    /**
     * Enable/disable an AI provider.
     * Only one provider is active at a time.
     */
    public function toggleAiService(AiService $service): void
    {
        try {
            $newStatus = !$service->is_enabled;

            if ($newStatus) {
                AiService::where('id', '!=', $service->id)
                    ->where('is_enabled', true)
                    ->update(['is_enabled' => false]);
            }

            $service->update(['is_enabled' => $newStatus]);

            $status = $service->is_enabled ? 'enabled' : 'disabled';
            Toast::info("AI provider {$service->name} {$status}");
        } catch (\Throwable $e) {
            Toast::error('Failed to change AI provider status: ' . $e->getMessage());
        }
    }

    /**
     * Show the AI provider account balance (same source as the alert).
     */
    public function checkAiServiceBalance(AiService $service): void
    {
        $balance = (new AiAmlServiceDispatcher())->balanceFor($service);

        if (!$balance->isKnown()) {
            Toast::warning($balance->describe());
            return;
        }

        $balance->isBelowThreshold()
            ? Toast::error('❌ ' . $balance->describe())
            : Toast::info($balance->describe());
    }

    /**
     * Save the global toggle for automatic AI AML checks.
     */
    public function saveAiSettings(Request $request): void
    {
        $data = $request->collect('aiSettings')->toArray();

        Setting::updateOrCreate(
            ['key' => Setting::AI_AML_CHECK_ENABLED],
            [
                'value' => !empty($data['enabled']) ? '1' : '0',
                'description' => 'AI AML check: enabled (1) / disabled (0)',
            ]
        );

        Toast::info('AI AML settings saved');
    }

}
