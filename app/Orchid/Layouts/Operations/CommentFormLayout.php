<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class CommentFormLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            TextArea::make('wallet.comment')
                ->rows(7)
                ->set('class', 'form-control')
                ->placeholder('Note about the operation')
                ->title('Comment'),
        ];
    }
}
