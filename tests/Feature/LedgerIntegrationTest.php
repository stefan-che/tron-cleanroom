<?php

namespace Tests\Feature;

use App\Models\Recipient\LedgerAccount;
use App\Models\Recipient\LedgerAccountWallet;
use App\Models\Recipient\Recipient;
use App\Models\Recipient\TgUser;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Services\Telegram\MainBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

/**
 * Optional ledger integration: linking API, sync on address change, "not configured" mode.
 * Requires a DB: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit
 */
class LedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const string LEDGER_URL = 'http://ledger.example.com';
    private const string LEDGER_ID = '11111111-1111-1111-1111-111111111111';
    private const string LEDGER_WALLET_ID = '22222222-2222-2222-2222-222222222222';
    private const string ADDRESS = 'TLedgerTestAddressAAAAAAAAAAAAAAA1';
    private const string NEW_ADDRESS = 'TLedgerTestAddressBBBBBBBBBBBBBBB2';

    private Recipient $recipient;
    private RecipientWallet $wallet;
    private LedgerAccount $account;

    protected function setUp(): void
    {
        // Check before parent::setUp, otherwise RefreshDatabase would hit the real DB
        if (getenv('DB_CONNECTION') !== 'sqlite') {
            $this->markTestSkipped('Requires DB_CONNECTION=sqlite');
        }

        parent::setUp();

        config(['app.global_our_api_key' => null]);
        $this->configureLedger(true);

        $this->recipient = Recipient::create(['name' => 'Shop', 'ledger_user_id' => '100000001']);
        // Without the observer: it creates energy wallets
        $this->wallet = RecipientWallet::withoutEvents(fn() => RecipientWallet::create([
            'recipient_id' => $this->recipient->id,
            'type' => RecipientWallet::TYPE_WHITE,
            'blockchain_type' => RecipientWallet::BLOCKCHAIN_TYPE_TRC_20,
            'address' => self::ADDRESS,
        ]));
        $this->account = LedgerAccount::create([
            'ledger_id' => self::LEDGER_ID,
            'group_id' => '33333333-3333-3333-3333-333333333333',
            'recipient_id' => $this->recipient->id,
            'user_id' => '100000001',
            'name' => 'Main',
            'currency' => 'usdt',
            'amount' => 0,
            'is_show_bot' => true,
        ]);
    }

    public function test_link_wallet_calls_ledger_and_mirrors_wallet(): void
    {
        Http::fake(['*' => Http::response([
            'id' => self::LEDGER_WALLET_ID, 'account_id' => self::LEDGER_ID,
            'wallet_address' => self::ADDRESS, 'network' => 'tron',
        ], 201)]);

        $this->postJson('/api/ledger-accounts/' . self::LEDGER_ID . '/wallet', ['wallet_address' => self::ADDRESS])
            ->assertOk()
            ->assertJson(['success' => true, 'ledger_wallet_id' => self::LEDGER_WALLET_ID, 'network' => 'tron']);

        Http::assertSent(fn(Request $r) => $r->method() === 'POST'
            && $r->url() === self::LEDGER_URL . '/api/accounts/' . self::LEDGER_ID . '/wallets'
            && $r->hasHeader('X-API-KEY', 'secret-token')
            && $r['wallet_address'] === self::ADDRESS
            && $r['network'] === 'tron');

        $this->assertDatabaseHas('ledger_account_wallets', [
            'ledger_account_id' => $this->account->id,
            'ledger_wallet_id' => self::LEDGER_WALLET_ID,
            'wallet_address' => self::ADDRESS,
        ]);
    }

    public function test_link_wallet_passes_ledger_error_through(): void
    {
        Http::fake(['*' => Http::response(['error' => 'account not found'], 404)]);

        $this->postJson('/api/ledger-accounts/' . self::LEDGER_ID . '/wallet', ['wallet_address' => self::ADDRESS])
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'account not found']);

        $this->assertDatabaseCount('ledger_account_wallets', 0);
    }

    public function test_link_wallet_unknown_account_and_bad_address(): void
    {
        Http::fake();

        $this->postJson('/api/ledger-accounts/unknown/wallet', ['wallet_address' => self::ADDRESS])
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Ledger account not found']);

        $this->postJson('/api/ledger-accounts/' . self::LEDGER_ID . '/wallet', ['wallet_address' => 'not-an-address'])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_unlink_wallet_deletes_mirror(): void
    {
        LedgerAccountWallet::create([
            'ledger_account_id' => $this->account->id,
            'ledger_wallet_id' => self::LEDGER_WALLET_ID,
            'wallet_address' => self::ADDRESS,
            'network' => 'tron',
        ]);
        Http::fake(['*' => Http::response(null, 204)]);

        $this->deleteJson('/api/ledger-accounts/' . self::LEDGER_ID . '/wallet/' . self::LEDGER_WALLET_ID)
            ->assertOk()
            ->assertJson(['success' => true]);

        Http::assertSent(fn(Request $r) => $r->method() === 'DELETE'
            && $r->url() === self::LEDGER_URL . '/api/accounts/' . self::LEDGER_ID . '/wallets/' . self::LEDGER_WALLET_ID);
        $this->assertDatabaseCount('ledger_account_wallets', 0);
    }

    public function test_api_returns_503_when_not_configured(): void
    {
        $this->configureLedger(false);
        Http::fake();

        $this->postJson('/api/ledger-accounts/' . self::LEDGER_ID . '/wallet', ['wallet_address' => self::ADDRESS])
            ->assertStatus(503)
            ->assertJson(['success' => false]);

        $this->deleteJson('/api/ledger-accounts/' . self::LEDGER_ID . '/wallet/' . self::LEDGER_WALLET_ID)
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_address_change_is_pushed_to_linked_accounts(): void
    {
        $this->account->update(['wallet_id' => $this->wallet->id]);
        Http::fake(['*' => Http::response(['id' => self::LEDGER_WALLET_ID, 'wallet_address' => self::NEW_ADDRESS], 201)]);

        $this->wallet->update(['address' => self::NEW_ADDRESS]);

        Http::assertSent(fn(Request $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/api/accounts/' . self::LEDGER_ID . '/wallets')
            && $r['wallet_address'] === self::NEW_ADDRESS);
        $this->assertDatabaseHas('ledger_account_wallets', [
            'ledger_account_id' => $this->account->id,
            'wallet_address' => self::NEW_ADDRESS,
        ]);
    }

    public function test_address_change_is_noop_when_not_configured(): void
    {
        $this->configureLedger(false);
        $this->account->update(['wallet_id' => $this->wallet->id]);
        Http::fake();

        $this->wallet->update(['address' => self::NEW_ADDRESS]);

        Http::assertNothingSent();
        $this->assertSame(self::NEW_ADDRESS, $this->wallet->fresh()->address);
    }

    public function test_bot_link_requires_flag_and_configuration(): void
    {
        $this->fakeTelegram();
        Http::fake(['*' => Http::response(['id' => self::LEDGER_WALLET_ID], 201)]);
        TgUser::create(['telegram_id' => '100000001']);

        // User has no flag
        app(MainBotService::class)->ledgerConfirmLink(100000001, $this->account->id, $this->wallet->id);
        Http::assertNothingSent();

        // Flag is set, but the integration is disabled
        TgUser::findByTelegramId('100000001')->update(['can_manage_ledger_accounts' => true]);
        $this->configureLedger(false);
        app(MainBotService::class)->ledgerConfirmLink(100000001, $this->account->id, $this->wallet->id);
        Http::assertNothingSent();
        $this->assertNull($this->account->fresh()->wallet_id);
    }

    public function test_bot_links_wallet_to_account(): void
    {
        $this->fakeTelegram();
        Http::fake(['*' => Http::response(['id' => self::LEDGER_WALLET_ID], 201)]);
        TgUser::create(['telegram_id' => '100000001', 'can_manage_ledger_accounts' => true]);

        app(MainBotService::class)->ledgerConfirmLink(100000001, $this->account->id, $this->wallet->id);

        $this->assertSame($this->wallet->id, (int)$this->account->fresh()->wallet_id);
        $this->assertDatabaseHas('ledger_account_wallets', [
            'ledger_account_id' => $this->account->id,
            'ledger_wallet_id' => self::LEDGER_WALLET_ID,
        ]);
    }

    private function configureLedger(bool $on): void
    {
        config(['services.ledger' => $on
            ? ['url' => self::LEDGER_URL, 'token' => 'secret-token']
            : ['url' => '', 'token' => '']]);
        // LedgerService reads config in the constructor, so reset the instances
        $this->app->forgetInstance(\App\Services\Ledger\LedgerService::class);
        $this->app->forgetInstance(MainBotService::class);
    }

    private function fakeTelegram(): void
    {
        Telegram::swap(new class {
            public function __call(string $method, array $args): mixed
            {
                return null;
            }
        });
    }
}
