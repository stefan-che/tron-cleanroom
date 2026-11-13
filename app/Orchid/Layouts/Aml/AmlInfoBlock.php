<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Aml;

use App\Helpers\DateHelper;
use App\Helpers\LogFormatter;
use App\Models\Aml\AmlAddress;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Legend;
use Orchid\Screen\Sight;

class AmlInfoBlock extends Legend
{
    protected $target = 'aml';
    protected $title = '';

    protected function columns(): iterable
    {
        /** @var AmlAddress $aml */
        $aml = $this->query->get('aml');

        return [

            Sight::make('aml_code', 'Blockchain')
                ->render(fn(AmlAddress $aml) => $aml->aml_code),

            Sight::make('address', 'Address')
                ->render(fn(AmlAddress $aml) => Link::make((string)$aml->address)
                    ->canSee((bool)$aml->explorer_address_link)
                    ->rawClick()
                    ->icon('share-alt')
                    ->target('_blank')
                    ->href($aml->explorer_address_link)
                ),

            Sight::make('created_at', 'Created at')
                ->render(fn(AmlAddress $aml) => DateHelper::toDisplayTime($aml->created_at)?->format('Y-m-d H:i:s')),

            Sight::make('updated_at', 'Last updated')
                ->render(fn(AmlAddress $aml) => DateHelper::toDisplayTime($aml->updated_at)?->format('Y-m-d H:i:s')),

            Sight::make('check_finish_at', 'Last checked')
                ->render(fn(AmlAddress $aml) => DateHelper::toDisplayTime($aml->check_finish_at)?->format('Y-m-d H:i:s')),

            Sight::make('status', 'Status')
                ->render(fn(AmlAddress $aml) => $aml->status_name),

            Sight::make('is_test', 'Test wallet')
                ->canSee((bool)$aml->is_test)
                ->render(fn(AmlAddress $aml) => $aml->is_test ? '<i class="text-danger">●</i> Yes'
                    : '<i class="text-success">●</i> No'),


            Sight::make('has_error', 'Error')
                ->render(fn(AmlAddress $aml) => $aml->has_error ? '<i class="text-danger">●</i> Yes'
                    : '<i class="text-success">●</i> No'),

            Sight::make('aml_risk_score', 'AML risk')
                ->render(fn(AmlAddress $aml) => $aml->aml_risk_score),

            Sight::make('aml_file_report_link', 'AML report')
                ->canSee((bool)$aml->aml_file_report_link)
                ->render(fn(AmlAddress $aml) => Link::make(__('Open report'))
                    ->icon('share-alt')
                    ->href(route('aml.report', $aml->address))
                    ->target('_blank')
                ),

            Sight::make('response', 'Response')
                ->render(function(AmlAddress $aml){
                    if (!$aml->response) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($aml->response);
                }),

            Sight::make('comment', 'Comment')
                ->render(fn(AmlAddress $aml) => Group::make([
                    TextArea::make('comment')
                        ->set('class', 'form-control')
                        ->canSee((bool)$aml->comment)
                        ->value($aml->comment),

                    ModalToggle::make('')
                        ->modal('walletCommentModal')
                        ->modalTitle('Comment')
                        ->method('setComment')
                        ->icon('plus')
                        ->asyncParameters([
                            'wallet' => $aml->id,
                        ])
                ])->alignEnd()->alignBaseLine()),
        ];
    }

}
