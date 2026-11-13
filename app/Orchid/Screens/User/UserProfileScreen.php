<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Orchid\Layouts\User\ProfilePasswordLayout;
use App\Orchid\Layouts\User\User2FALayout;
use App\Orchid\Layouts\User\User2FAModalFormLayout;
use App\Orchid\Layouts\User\UserEditLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Orchid\Access\Impersonation;
use Orchid\Platform\Models\User;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UserProfileScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        return [
            'user' => $request->user(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'My Account';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Update your account details such as name, email address and password';
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Back to my account')
                ->novalidate()
                ->canSee(Impersonation::isSwitch())
                ->icon('bs.people')
                ->route('platform.switch.logout'),

            Button::make('Sign out')
                ->novalidate()
                ->icon('bs.box-arrow-left')
                ->route('platform.logout'),
        ];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [

            Layout::modal('User2FAModal', [
                User2FAModalFormLayout::class,
            ])
                ->async('asyncGet2FAData')
                ->applyButton('Save')
                ->closeButton('Close'),

            Layout::block(UserEditLayout::class)
                ->title(__('Profile Information'))
                ->description(__("Update your account's profile information and email address."))
                ->commands(
                    Button::make(__('Save'))
                        ->type(Color::BASIC())
                        ->icon('bs.check-circle')
                        ->method('save')
                ),

            Layout::block(ProfilePasswordLayout::class)
                ->title(__('Update Password'))
                ->description(__('Ensure your account is using a long, random password to stay secure.'))
                ->commands(
                    Button::make(__('Update password'))
                        ->type(Color::BASIC())
                        ->icon('bs.check-circle')
                        ->method('changePassword')
                ),



            Layout::block(User2FALayout::class)
                ->title('2FA'),
        ];
    }



    /**
     * @param \App\Models\User $user
     * @return array
     */
    public function asyncGet2FAData(Request $request): iterable
    {
        /**
         * @var \PragmaRX\Google2FALaravel\Google2FA $google2fa
         */
        $google2fa = app('pragmarx.google2fa');
        $user = $request->user();

        $secret = $user->two_factor_secret ?: $google2fa->generateSecretKey();

        if (!$user->two_factor_secret) {
            $user->update([
                'two_factor_secret' => $secret
            ]);
        }

        $QRCode = $google2fa->getQRCodeInline(
            config('app.name'),
            $user->email,
            $secret
        );

        return [
            'user' => $user,
            'referrer' => $user->referrer,
            'QRCode' => $QRCode,
            'permission' => $user->getStatusPermission(),
        ];
    }

    public function save2Fa(Request $request)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $google2fa = app('pragmarx.google2fa');
        $user = $request->user();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->input('code')
        );

        if ($valid) {
            $user->update([
                'is_two_factor_enabled' => true
            ]);
            Toast::info('Two-factor authentication enabled');
        } else {
            Toast::error('The code is probably incorrect');
        }
    }

    public function remove2Fa(Request $request)
    {
        $request->user()->update([
            'two_factor_secret' => '',
            'is_two_factor_enabled' => false,
        ]);

        Toast::error('Two-factor authentication disabled');
    }



    public function save(Request $request): void
    {
        $request->validate([
            'user.name'  => 'required|string',
            'user.email' => [
                'required',
                Rule::unique(User::class, 'email')->ignore($request->user()),
            ],
        ]);

        $request->user()
            ->fill($request->get('user'))
            ->save();

        Toast::info(__('Profile updated.'));
    }

    public function changePassword(Request $request): void
    {
        $guard = config('platform.guard', 'web');
        $request->validate([
            'old_password' => 'required|current_password:'.$guard,
            'password'     => 'required|confirmed|different:old_password',
        ]);

        tap($request->user(), function ($user) use ($request) {
            $user->password = Hash::make($request->get('password'));
        })->save();

        Toast::info(__('Password changed.'));
    }
}
