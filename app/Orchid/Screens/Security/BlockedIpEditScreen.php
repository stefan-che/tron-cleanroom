<?php

namespace App\Orchid\Screens\Security;

use App\Models\User\BlockedIp;
use App\Orchid\Layouts\Security\BlockedIpEditLayout;
use App\Services\Security\BlockedIpService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class BlockedIpEditScreen extends Screen
{
    public $blockedIp;
    private BlockedIpService $blockedIpService;

    public function __construct(BlockedIpService $blockedIpService)
    {
        $this->blockedIpService = $blockedIpService;
    }

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(BlockedIp $blockedIp): iterable
    {
        $this->blockedIp = $blockedIp;

        return [
            'blocked_ip' => $blockedIp,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->blockedIp->exists ? 'Edit IP block' : 'Create IP block';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return $this->blockedIp->exists
            ? 'Edit IP address block settings'
            : 'Block a new IP address';
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Save')
                ->icon('check')
                ->method('save'),

            Button::make('Delete')
                ->icon('trash')
                ->method('remove')
                ->canSee($this->blockedIp->exists),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            Layout::block(BlockedIpEditLayout::class)
                ->title('Block details')
                ->description('Enter the IP address and block settings'),
        ];
    }

    /**
     * Save an IP block
     */
    public function save(Request $request, BlockedIp $blockedIp)
    {
        $request->validate([
            'blocked_ip.ip_address' => [
                'required',
                'string',
                Rule::unique('blocked_ips', 'ip_address')->ignore($blockedIp->id),
                'ip'
            ],
            'blocked_ip.reason' => 'required|string|max:255',
            'blocked_ip.is_permanently_blocked' => 'boolean',
            'blocked_ip.blocked_until' => 'nullable|date|after:now',
        ]);

        $data = $request->get('blocked_ip');
        $ip = $data['ip_address'];
        $reason = $data['reason'];

        if ($blockedIp->exists) {
            // Update an existing block
            if ($data['is_permanently_blocked']) {
                $this->blockedIpService->blockPermanently($ip, $reason, auth()->id());
            } else {
                $minutes = isset($data['blocked_until'])
                    ? now()->diffInMinutes($data['blocked_until'])
                    : 15;
                $this->blockedIpService->blockTemporary($ip, $minutes, $reason, auth()->id());
            }

            Toast::info("IP block {$ip} updated");
        } else {
            // Create a new block
            if ($data['is_permanently_blocked']) {
                $this->blockedIpService->blockPermanently($ip, $reason, auth()->id());
            } else {
                $minutes = isset($data['blocked_until'])
                    ? now()->diffInMinutes($data['blocked_until'])
                    : 15;
                $this->blockedIpService->blockTemporary($ip, $minutes, $reason, auth()->id());
            }

            Toast::info("IP address {$ip} blocked");
        }

        return redirect()->route('platform.security.blocked-ips.list');
    }

    /**
     * Delete a block
     */
    public function remove(Request $request, BlockedIp $blockedIp)
    {
        $ipAddress = $blockedIp->ip_address;
        $blockedIp->delete();

        Toast::info("IP block {$ipAddress} deleted");

        return redirect()->route('platform.security.blocked-ips.list');
    }
}
