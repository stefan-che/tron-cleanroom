<?php

namespace App\Http\Resources\Recipient;

use App\Models\Recipient\Recipient;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recipient
 */
class RecipientResource extends JsonResource
{
    public static $wrap = '';

    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'external_id'           => $this->external_id,
            'name'                  => $this->name,
            'comment'               => $this->comment,
            'wallets'               => RecipientWalletResource::collection($this->whenLoaded('wallets')),
            'ledger_accounts'       => $this->when(
                (bool) $this->ledger_user_id,
                fn() => LedgerAccountResource::collection($this->whenLoaded('ledgerAccounts')),
            ),
        ];
    }
}
