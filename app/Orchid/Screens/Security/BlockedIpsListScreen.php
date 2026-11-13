<?php

namespace App\Orchid\Screens\Security;

use App\Models\User\BlockedIp;
use App\Orchid\Layouts\Security\BlockedIpsFiltersLayout;
use App\Orchid\Layouts\Security\BlockedIpsListLayout;
use App\Services\Security\BlockedIpService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class BlockedIpsListScreen extends Screen
{
    private BlockedIpService $blockedIpService;

    public function __construct(BlockedIpService $blockedIpService)
    {
        $this->blockedIpService = $blockedIpService;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'blocked_ips' => BlockedIp::with('unblockedBy')
                ->defaultSort('created_at', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Blocked IP addresses';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage IP addresses blocked by the security system';
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Add block')
                ->icon('plus')
                ->route('platform.security.blocked-ips.create'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            BlockedIpsListLayout::class,
        ];
    }

    /**
     * Unblock an IP address
     */
    public function unblock(Request $request): void
    {
        $blockedIp = BlockedIp::findOrFail($request->get('id'));

        $this->blockedIpService->unblock($blockedIp->ip_address, auth()->id());

        Toast::info("IP address {$blockedIp->ip_address} unblocked");
    }

    /**
     * Delete a block record
     */
    public function destroy(Request $request): void
    {
        $blockedIp = BlockedIp::findOrFail($request->get('id'));
        $ipAddress = $blockedIp->ip_address;

        $blockedIp->delete();

        Toast::info("Block record for IP {$ipAddress} deleted");
    }
}
