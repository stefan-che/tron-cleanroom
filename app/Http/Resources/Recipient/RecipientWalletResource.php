<?php

namespace App\Http\Resources\Recipient;

use App\Models\Recipient\Wallet;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class RecipientWalletResource extends JsonResource
{
    public static $wrap = '';

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'address' => $this->address,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'type_name_en' => $this->type_name_en,
            'blockchain_type' => $this->blockchain_type,
            'blockchain_type_name' => $this->blockchain_type_name,
        ];
    }
}
