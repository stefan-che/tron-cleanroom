<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Aml;

use App\Models\Aml\AmlAddress;
use App\Models\Role;
use App\Orchid\Layouts\Aml\AmlChecksList;
use App\Orchid\Layouts\Aml\AmlInfoBlock;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AmlInfoScreen extends Screen
{

    /**
     * @var AmlAddress
     */
    public $aml;


    public function __construct()
    {
    }

    /**
     * @return iterable|null
     */
    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_AML,
        ];
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(AmlAddress $aml): iterable
    {
        return [
            'aml' => $aml,
            'checks' => $aml->checks,
        ];
    }

    /**
     * The name is displayed on the user's screen and in the headers
     */
    public function name(): ?string
    {
        return $this->aml->exists ? ('Checked address: ' . $this->aml->address) : '';
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
            Button::make('Mark as test')
                ->canSee(!$this->aml->is_test)
                ->icon('save')
                ->method('setTest')
                ->class('btn btn-default'),

            Button::make('Unmark test')
                ->canSee((bool)$this->aml->is_test)
                ->icon('save')
                ->method('resetTest')
                ->class('btn btn-warning'),
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
            Layout::tabs([
                'Address' => Layout::block(AmlInfoBlock::class)->vertical(),
                'Check history' => AmlChecksList::class,
            ]),
        ];
    }

    /**
     * @param AmlAddress $aml
     * @return array
     */
    public function asyncGetWallet(AmlAddress $aml): iterable
    {
        return [
            'aml' => $aml,
        ];
    }

    public function setComment(AmlAddress $aml, Request $request)
    {
        $data = $request->collect('aml')->toArray();

        $aml->fill([
            'comment' => $data['comment'],
        ]);

        $aml->save();

        Toast::info('Comment added')->delay(1000);
    }

    public function setTest(AmlAddress $aml, Request $request)
    {
        $aml->update([
            'is_test' => true,
        ]);

        Toast::info('Address marked as test')->delay(3000);
    }

    public function resetTest(AmlAddress $aml, Request $request)
    {
        $aml->update([
            'is_test' => false,
        ]);

        Toast::info('Address is no longer a test address')->delay(3000);
    }

}
