<?php

namespace Tests\Feature;

use App\Enums\WalletOperationsTypes;
use App\Enums\WalletStatuses;
use App\Jobs\Wallet\AddEnergy;
use App\Jobs\Wallet\SendUsdt;
use App\Models\Recipient\Recipient;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Destination address rule: incoming (buy) white/gray/yellow only to a wallet of the same recipient and color,
 * an arbitrary address only for black; payout (sell) is unrestricted.
 * Requires a DB: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit
 */
class PayoutDestinationRuleTest extends TestCase
{
    use RefreshDatabase;

    private const string FREE_ADDRESS = 'TFreeAddress00000000000000000000001';

    private Recipient $recipient;
    private Recipient $otherRecipient;
    private RecipientWallet $white;
    private RecipientWallet $gray;
    private RecipientWallet $black;
    private RecipientWallet $otherWhite;
    private int $seq = 0;

    protected function setUp(): void
    {
        // Check before parent::setUp, otherwise RefreshDatabase would hit the real DB
        if (getenv('DB_CONNECTION') !== 'sqlite') {
            $this->markTestSkipped('Requires DB_CONNECTION=sqlite');
        }

        parent::setUp();

        $this->recipient = Recipient::create(['name' => 'Shop']);
        $this->otherRecipient = Recipient::create(['name' => 'Other shop']);

        $this->white = $this->recipientWallet($this->recipient, RecipientWallet::TYPE_WHITE, 'TOwnWhite');
        $this->gray = $this->recipientWallet($this->recipient, RecipientWallet::TYPE_GRAY, 'TOwnGray');
        $this->black = $this->recipientWallet($this->recipient, RecipientWallet::TYPE_BLACK, 'TOwnBlack');
        $this->otherWhite = $this->recipientWallet($this->otherRecipient, RecipientWallet::TYPE_WHITE, 'TOtherWhite');
    }

    private function recipientWallet(Recipient $recipient, int $type, string $address): RecipientWallet
    {
        // Without the observer: it creates energy wallets and queues jobs
        return RecipientWallet::withoutEvents(static fn() => RecipientWallet::create([
            'recipient_id' => $recipient->id,
            'type' => $type,
            'blockchain_type' => RecipientWallet::BLOCKCHAIN_TYPE_TRC_20,
            'address' => $address,
        ]));
    }

    private function wallet(array $attributes = []): Wallet
    {
        $this->seq++;

        return Wallet::create($attributes + [
            'blockchain_type' => RecipientWallet::BLOCKCHAIN_TYPE_TRC_20,
            'recipient_id' => $this->recipient->id,
            'operation_type' => WalletOperationsTypes::BUY->value,
            'status' => WalletStatuses::CHECK_TRANSACTION_FINISH->value,
            'amount' => 100,
            'external_id' => 'pay-' . $this->seq,
            'address' => 'TTransit' . $this->seq,
            'address_hex' => 'hex',
            'private_key' => 'k',
            'private_key_hash' => 'h',
            'private_key_salt' => 's',
            'private_key_secret' => 's',
            'public_key' => 'p',
        ])->refresh(); // load DB defaults (has_error, is_test)
    }

    private function service(): WalletService
    {
        return app(WalletService::class);
    }

    // --- isAllowedDestination

    public function test_buy_white_allows_only_own_white_wallet(): void
    {
        $wallet = $this->wallet();
        $s = $this->service();

        $this->assertTrue($s->isAllowedDestination($wallet, RecipientWallet::TYPE_WHITE, $this->white->address));
        $this->assertFalse($s->isAllowedDestination($wallet, RecipientWallet::TYPE_WHITE, $this->gray->address));
        $this->assertFalse($s->isAllowedDestination($wallet, RecipientWallet::TYPE_WHITE, $this->otherWhite->address));
        $this->assertFalse($s->isAllowedDestination($wallet, RecipientWallet::TYPE_WHITE, self::FREE_ADDRESS));
        $this->assertFalse($s->isAllowedDestination($wallet, RecipientWallet::TYPE_GRAY, self::FREE_ADDRESS));
        $this->assertFalse($s->isAllowedDestination($wallet, RecipientWallet::TYPE_YELLOW, self::FREE_ADDRESS));
    }

    public function test_buy_black_allows_free_address_but_not_non_black_recipient_wallet(): void
    {
        $wallet = $this->wallet();
        $s = $this->service();

        $this->assertTrue($s->isAllowedDestination($wallet, RecipientWallet::TYPE_BLACK, self::FREE_ADDRESS));
        $this->assertTrue($s->isAllowedDestination($wallet, RecipientWallet::TYPE_BLACK, $this->black->address));
        $this->assertFalse($s->isAllowedDestination($wallet, RecipientWallet::TYPE_BLACK, $this->white->address));
    }

    public function test_buy_without_colour_allows_only_own_wallets(): void
    {
        $wallet = $this->wallet();
        $s = $this->service();

        $this->assertTrue($s->isAllowedDestination($wallet, null, $this->gray->address));
        $this->assertFalse($s->isAllowedDestination($wallet, null, $this->otherWhite->address));
        $this->assertFalse($s->isAllowedDestination($wallet, null, self::FREE_ADDRESS));
    }

    public function test_sell_is_not_restricted(): void
    {
        $wallet = $this->wallet(['operation_type' => WalletOperationsTypes::SELL->value]);

        $this->assertTrue($this->service()->isAllowedDestination($wallet, RecipientWallet::TYPE_WHITE, self::FREE_ADDRESS));
    }

    // --- WalletService::send

    public function test_send_white_to_free_address_is_refused(): void
    {
        Queue::fake();
        $wallet = $this->wallet();

        $this->assertFalse($this->service()->send($wallet, RecipientWallet::TYPE_WHITE, self::FREE_ADDRESS));

        $wallet->refresh();
        $this->assertNull($wallet->address_to);
        $this->assertSame(WalletStatuses::CHECK_TRANSACTION_FINISH->value, (int)$wallet->status);
        Queue::assertNotPushed(SendUsdt::class);
    }

    public function test_send_white_to_other_recipient_wallet_is_refused(): void
    {
        Queue::fake();
        $wallet = $this->wallet();

        $this->assertFalse($this->service()->send($wallet, RecipientWallet::TYPE_WHITE, $this->otherWhite->address));
        Queue::assertNotPushed(SendUsdt::class);
    }

    public function test_send_white_with_stored_foreign_address_is_refused(): void
    {
        Queue::fake();
        $wallet = $this->wallet(['address_to' => self::FREE_ADDRESS]);

        $this->assertFalse($this->service()->send($wallet, RecipientWallet::TYPE_WHITE));
        Queue::assertNotPushed(SendUsdt::class);
    }

    public function test_send_white_without_address_uses_own_white_wallet(): void
    {
        Queue::fake();
        $wallet = $this->wallet();

        $this->assertTrue($this->service()->send($wallet, RecipientWallet::TYPE_WHITE));

        $wallet->refresh();
        $this->assertSame($this->white->address, $wallet->address_to);
        $this->assertSame(RecipientWallet::TYPE_WHITE, (int)$wallet->type);
        Queue::assertPushed(SendUsdt::class);
    }

    public function test_send_black_to_free_address_is_allowed(): void
    {
        Queue::fake();
        $wallet = $this->wallet();

        $this->assertTrue($this->service()->send($wallet, RecipientWallet::TYPE_BLACK, self::FREE_ADDRESS));
        $this->assertSame(self::FREE_ADDRESS, $wallet->refresh()->address_to);
        Queue::assertPushed(SendUsdt::class);
    }

    public function test_send_sell_keeps_client_address(): void
    {
        Queue::fake();
        $wallet = $this->wallet([
            'operation_type' => WalletOperationsTypes::SELL->value,
            'address_to' => self::FREE_ADDRESS,
        ]);

        $this->assertTrue($this->service()->send($wallet, RecipientWallet::TYPE_BLACK, self::FREE_ADDRESS));
        Queue::assertPushed(SendUsdt::class);
    }

    // --- setWhite/Gray/YellowWallet pins the color

    public function test_set_gray_wallet_records_colour(): void
    {
        $wallet = $this->wallet();

        $this->assertTrue($this->service()->setGrayWallet($wallet));

        $wallet->refresh();
        $this->assertSame($this->gray->address, $wallet->address_to);
        $this->assertSame(RecipientWallet::TYPE_GRAY, (int)$wallet->type);
        $this->assertTrue($this->service()->isAllowedStoredDestination($wallet));
    }

    // --- SendUsdt: the last line of defense (admin and bot buttons dispatch it directly)

    public function test_send_usdt_job_refuses_foreign_stored_address_for_non_black(): void
    {
        Bus::fake([AddEnergy::class]);
        $wallet = $this->wallet([
            'address_to' => self::FREE_ADDRESS,
            'type' => RecipientWallet::TYPE_WHITE,
        ]);

        (new SendUsdt($wallet->id))->handle();

        $this->assertTrue((bool)$wallet->refresh()->has_error);
        Bus::assertNotDispatched(AddEnergy::class);
    }

    public function test_send_usdt_job_refuses_free_address_without_colour(): void
    {
        Bus::fake([AddEnergy::class]);
        $wallet = $this->wallet(['address_to' => self::FREE_ADDRESS]);

        (new SendUsdt($wallet->id))->handle();

        $this->assertTrue((bool)$wallet->refresh()->has_error);
        Bus::assertNotDispatched(AddEnergy::class);
    }
}
