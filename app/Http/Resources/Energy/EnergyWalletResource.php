<?php

namespace App\Http\Resources\Energy;

use App\Http\Resources\Recipient\RecipientResource;
use App\Models\Energy\EnergyWallet;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EnergyWallet
 */
class EnergyWalletResource extends JsonResource
{
    public static $wrap = '';

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'address' => $this->address,
            'label' => $this->label,
            'auto_replenishment_enabled' => $this->auto_replenishment_enabled,
            'expires_at' => $this->expires_at?->toISOString(),
            'is_error' => $this->is_error,
            'tronscan_link' => $this->tronscan_address_link,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'recipient' => RecipientResource::make($this->whenLoaded('recipient')),
        ];
    }
}
