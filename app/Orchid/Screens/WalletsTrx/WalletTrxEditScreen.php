<?php

declare(strict_types=1);

namespace App\Orchid\Screens\WalletsTrx;

use App\Models\WalletTrx;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Picture;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class WalletTrxEditScreen extends Screen
{

    /**
     * @var WalletTrx
     */
    public $wallet;

    /**
     * @return iterable|null
     */
    public function permission(): ?iterable
    {
        return [
        ];
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(WalletTrx $wallet): iterable
    {
        return [
            'wallet' => $wallet,
        ];
    }

    /**
     * The name is displayed on the user's screen and in the headers
     */
    public function name(): ?string
    {
        return $this->wallet->exists ? $this->wallet->address : 'Add new wallet';
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return '';
    }

    /**
     * Button commands.
     *
     * @return array
     */
    public function commandBar(): array
    {
        return [
            Button::make('Create')
                ->icon('pencil')
                ->method('createOrUpdate')
                ->canSee(!$this->wallet->exists),

            Button::make('Save and back to list')
                ->icon('note')
                ->method('createOrUpdate')
                ->parameters(['continue' => true])
                ->rawClick()
                ->canSee($this->wallet->exists),

            Button::make('Save')
                ->icon('note')
                ->method('createOrUpdate')
                ->canSee($this->wallet->exists),
        ];
    }

    /**
     * Views.
     *
     * @return Layout[]
     */
    public function layout(): array
    {
        return [
            Layout::rows([
                TextArea::make('wallet.comment')
                    ->title('Description / comment'),
            ])

        ];
    }


    public function createOrUpdate(WalletTrx $wallet, Request $request)
    {
        $wallet->fill($request->get('wallet'));
        $wallet->save();

        Toast::info('Saved')->delay(1000);

        if ($request->get('continue')) {
            return redirect()->route('platform.wallet_trx.list');
        }

        return redirect()->route('platform.wallet_trx.edit', $wallet->id);
    }

    /**
     * @param WalletTrx $wallet
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function remove(WalletTrx $wallet)
    {

        Toast::warning('Deleted')->delay(1000);

        return redirect()->route('platform.wallet_trx.list');
    }
}
