<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class AiServiceFormLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('aiService.name')
                ->title('Name')
                ->readonly(),

            Input::make('aiService.model')
                ->title('Model')
                ->placeholder('e.g. gpt-4o')
                ->help('Model ID at the provider'),

            Input::make('aiService.api_key')
                ->type('password')
                ->title('API key')
                ->autocomplete('new-password')
                ->placeholder('••••••••')
                ->help('Leave empty to keep the current value. If not set, the value from .env is used.'),

            Input::make('aiService.admin_api_key')
                ->type('password')
                ->title('Admin API key')
                ->autocomplete('new-password')
                ->placeholder('••••••••')
                ->help('Only needed for spend tracking (OpenAI: sk-admin-...). Leave empty to keep the current value.'),

            Input::make('aiService.balance_topup')
                ->type('number')
                ->step(0.01)
                ->title('Last top-up amount, USD')
                ->help('The provider API does not return the balance, so it is calculated as this amount '
                    . 'minus spend since the top-up date. Update it on every account top-up.'),

            DateTimer::make('aiService.balance_topup_at')
                ->title('Top-up date')
                ->format('Y-m-d')
                ->help('Spend is counted from this date. If empty, spend is counted from the start of the current month.'),

            Input::make('aiService.balance_threshold')
                ->type('number')
                ->step(0.01)
                ->title('Balance threshold, USD')
                ->help('A Telegram alert is sent when the balance drops below this. Leave empty to disable the check.'),

            Switcher::make('aiService.is_enabled')
                ->sendTrueOrFalse()
                ->title('Active')
                ->help('Only one AI service can be active'),

            TextArea::make('aiService.prompt')
                ->rows(22)
                ->title('Prompt')
                ->help('System prompt with the analysis rules. The AI must return JSON: decision, risk, confidence, summary.'),
        ];
    }
}
