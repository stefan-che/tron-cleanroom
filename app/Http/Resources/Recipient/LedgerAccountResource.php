<?php

namespace App\Http\Resources\Recipient;

use App\Models\Recipient\LedgerAccount;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerAccount
 */
class LedgerAccountResource extends JsonResource
{
    public static $wrap = '';

    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'ledger_id'      => $this->ledger_id,
            'name'           => $this->name,
            'currency'       => $this->currency,
            'amount'         => $this->amount,
            'wallet_address' => $this->wallet?->address,
        ];
    }
}
