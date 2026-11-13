<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\ApiRequest;
use App\Models\Recipient\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * Class SendRequest
 * @package App\Http\Requests\Event
 *
 * @property string $address
 * @property string $to_address
 * @property int $type
 * @property string|null $amount
 */
class SendRequest extends ApiRequest
{
    /**
     * Minimum partial send amount: the USDT TRC-20 precision step.
     * Kept as a string so the rule and error text do not turn into "1.0E-6"
     */
    private const string MIN_AMOUNT = '1';

    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules(): array
    {
        return [
            'address' => [
                'required',
                'max:128',
            ],
            'to_address' => [
                'nullable',
                'max:128',
                Rule::requiredIf(function () {
                    return $this->type === 'black';
                }),
            ],
            'type' => [
                'required',
                Rule::in(Wallet::typesEng())
            ],
            //Partial send: if not provided, the whole wallet balance is sent.
            'amount' => [
                'nullable',
                'numeric',
                'min:' . self::MIN_AMOUNT,
            ],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $wallet = (new WalletRepository())->getByAddress($this->address);
            if (!$wallet) {
                return $validator->errors()->add('address', 'No wallet with this address was found');
            }

            if (!$wallet->amount) {
                return $validator->errors()->add('address', 'The wallet has no balance, nothing to withdraw');
            }

            $amount = $this->getAmount();

            //Compare at USDT precision, otherwise a balance equal to the amount may fail due to double rounding
            if ($amount && round($amount, TronService::USDT_DECIMALS) > round((float)$wallet->amount, TronService::USDT_DECIMALS)) {
                return $validator->errors()->add(
                    'amount',
                    sprintf('The amount exceeds the wallet balance (%s USDT)', $wallet->amount)
                );
            }

            //Incoming (buy) white/grey/yellow: only a wallet of the same recipient and the same color
            if ($this->type !== 'black' && $wallet->isOperationBuy()) {
                $service = app(WalletService::class);
                $type = Wallet::getTypeByNameEng((string)$this->type);
                $toAddress = trim((string)$this->to_address);

                if ($toAddress && !$service->isAllowedDestination($wallet, $type, $toAddress)) {
                    return $validator->errors()->add(
                        'to_address',
                        'For incoming (buy) operations the address must be a wallet of this recipient with color ' . $this->type
                        . '; an arbitrary address is allowed only for black'
                    );
                }

                if ($wallet->address_to && !$service->isAllowedDestination($wallet, $type, $wallet->address_to)) {
                    return $validator->errors()->add(
                        'address',
                        'The saved destination address is not a wallet of this recipient with color ' . $this->type
                    );
                }
            }

            if ($this->type === 'black') {
                if (!preg_match($wallet->regexp, $this->to_address)) {
                    return $validator->errors()->add('to_address', 'Invalid address');
                }

                if ($this->to_address === $this->address) {
                    return $validator->errors()->add('to_address', 'The address cannot be the current wallet');
                }

                //TODO: allow setting the address only once

                $isRecipientsWallet = DB::table('recipient_wallets')
                    ->where('address', $this->to_address)
                    ->exists();

                if ($isRecipientsWallet) {
                    return $validator->errors()->add('to_address', 'The address cannot be a system wallet');
                }
            }
        });
    }

    /**
     * Partial send amount. Null means the whole wallet balance is sent.
     * @return float|null
     */
    public function getAmount(): ?float
    {
        if ($this->amount === null || $this->amount === '' || !is_numeric($this->amount)) {
            return null;
        }

        $amount = round((float)$this->amount, TronService::USDT_DECIMALS);

        return $amount >= (float)self::MIN_AMOUNT ? $amount : null;
    }

}
