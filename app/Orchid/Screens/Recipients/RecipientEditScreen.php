<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Recipients;

use App\Models\Recipient\Recipient;
use App\Models\Role;
use App\Orchid\Layouts\Recipients\TabMenuLayout;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class RecipientEditScreen extends Screen
{

    /**
     * @var Recipient
     */
    public $recipient;

    /**
     * @return iterable|null
     */
    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_RECIPIENTS,
        ];
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(Recipient $recipient): iterable
    {
        return [
            'recipient' => $recipient,
        ];
    }

    /**
     * The name is displayed on the user's screen and in the headers
     */
    public function name(): ?string
    {
        return $this->recipient->exists ? $this->recipient->name : 'New recipient';
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
            Button::make('Save')
                ->icon('check')
                ->method('save'),
        ];
    }

    /**
     * Views.
     *
     * @return Layout[]
     */
    public function layout(): array
    {
        $layout = $this->recipient->exists ? [TabMenuLayout::class] : [];

        $layout[] = Layout::rows([
            Input::make('recipient.name')
                ->title('Name')
                ->required(),

            Input::make('recipient.external_id')
                ->title('API ID (recipient_id)')
                ->help('Leave empty to generate automatically.'),

            TextArea::make('recipient.comment')
                ->title('Description / comment'),

            Input::make('recipient.ledger_user_id')
                ->title('Ledger ID')
                ->help('If empty, ledger accounts are not shown for this recipient.')
                ->canSee(app(LedgerService::class)->isConfigured()),
        ]);

        return $layout;
    }

    public function save(Request $request, Recipient $recipient): RedirectResponse
    {
        $request->validate([
            'recipient.name'        => 'required|string|max:255',
            'recipient.external_id' => [
                'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('recipients', 'external_id')->ignore($recipient->id),
            ],
            'recipient.comment'     => 'nullable|string|max:2000',
            'recipient.ledger_user_id' => 'nullable|string|max:255',
        ]);

        $data = $request->input('recipient');
        if (empty($data['external_id'])) {
            // An empty value does not overwrite an already issued ID
            unset($data['external_id']);
        }

        $recipient->fill($data)->save();

        Toast::success('Saved.');

        return redirect()->route('platform.recipient.edit', $recipient);
    }
}
