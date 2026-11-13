<?php

namespace Tests\Feature;

use App\Models\Recipient\Recipient;
use App\Models\Recipient\TgUser;
use App\Repositories\RecipientRepository;
use App\Services\Telegram\MainBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

/**
 * Requires a DB: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit
 */
class RecipientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Check before parent::setUp, otherwise RefreshDatabase would hit the real DB
        if (getenv('DB_CONNECTION') !== 'sqlite') {
            $this->markTestSkipped('Requires DB_CONNECTION=sqlite');
        }

        parent::setUp();
    }

    public function test_external_id_is_generated_when_empty(): void
    {
        $recipient = app(RecipientRepository::class)->store(['name' => 'Shop']);

        $this->assertNotNull($recipient);
        $this->assertNotEmpty($recipient->external_id);
    }

    public function test_explicit_external_id_is_kept(): void
    {
        $recipient = Recipient::create(['name' => 'Shop', 'external_id' => 'shop-1']);

        $this->assertSame('shop-1', $recipient->external_id);
    }

    public function test_tg_user_recipient_ids(): void
    {
        $recipient = Recipient::create(['name' => 'Shop']);
        $user = TgUser::create(['telegram_id' => '100000001']);
        $user->recipients()->attach($recipient->id);

        $this->assertSame([$recipient->id], array_map('intval', TgUser::getRecipientIds('100000001')));
    }

    public function test_bot_creates_recipient_and_attaches_creator(): void
    {
        $this->fakeTransport();
        TgUser::create(['telegram_id' => '100000001', 'can_create_recipients' => true]);

        app(MainBotService::class)->handleNewRecipientInput(100000001, 'New shop');

        $recipient = Recipient::where('name', 'New shop')->first();
        $this->assertNotNull($recipient);
        $this->assertSame([$recipient->id], array_map('intval', TgUser::getRecipientIds('100000001')));
    }

    public function test_bot_refuses_recipient_creation_without_flag(): void
    {
        $this->fakeTransport();
        TgUser::create(['telegram_id' => '100000001']);

        app(MainBotService::class)->handleNewRecipientInput(100000001, 'New shop');

        $this->assertSame(0, Recipient::count());
    }

    public function test_bot_adds_tg_user_to_own_recipient(): void
    {
        $this->fakeTransport();
        $recipient = Recipient::create(['name' => 'Shop']);
        $owner = TgUser::create(['telegram_id' => '100000001', 'can_create_recipients' => true]);
        $owner->recipients()->attach($recipient->id);

        app(MainBotService::class)->handleRecipientTgUserInput(100000001, $recipient->id, '100000002 Operator');

        $added = TgUser::findByTelegramId('100000002');
        $this->assertNotNull($added);
        $this->assertSame('Operator', $added->name);
        $this->assertSame([$recipient->id], array_map('intval', TgUser::getRecipientIds('100000002')));
    }

    public function test_bot_does_not_add_tg_user_to_foreign_recipient(): void
    {
        $this->fakeTransport();
        $recipient = Recipient::create(['name' => 'Foreign']);
        TgUser::create(['telegram_id' => '100000001', 'can_create_recipients' => true]);

        app(MainBotService::class)->handleRecipientTgUserInput(100000001, $recipient->id, '100000002');

        $this->assertNull(TgUser::findByTelegramId('100000002'));
    }

    private function fakeTransport(): void
    {
        Telegram::swap(new class {
            public function __call(string $method, array $args): mixed
            {
                return null;
            }
        });
        Redis::shouldReceive('set', 'expire', 'get', 'del')->andReturnNull();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info', 'error', 'warning')->andReturnNull();
    }
}
