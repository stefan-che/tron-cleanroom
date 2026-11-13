<?php

namespace Tests\Feature;

use App\Models\Recipient\Recipient;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Wallet\WalletListenerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * cleanroom-watcher webhook signature: X-Signature: sha256=<hmac_sha256(body, secret)>.
 * Requires a DB: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit
 */
class WalletWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'test-hmac-secret';
    private const string ADDRESS = 'TWebhookTestAddress000000000000001';

    protected function setUp(): void
    {
        // Check before parent::setUp, otherwise RefreshDatabase would hit the real DB
        if (getenv('DB_CONNECTION') !== 'sqlite') {
            $this->markTestSkipped('Requires DB_CONNECTION=sqlite');
        }

        parent::setUp();

        Queue::fake();
        config(['services.wallet_listener.hmac_secret' => self::SECRET]);

        $recipient = Recipient::create(['name' => 'Shop']);

        Wallet::create([
            'blockchain_type' => RecipientWallet::BLOCKCHAIN_TYPE_TRC_20,
            'recipient_id' => $recipient->id,
            'external_id' => 'pay-1',
            'address' => self::ADDRESS,
            'address_hex' => 'hex',
            'private_key' => 'k',
            'private_key_hash' => 'h',
            'private_key_salt' => 's',
            'private_key_secret' => 's',
            'public_key' => 'p',
        ]);
    }

    private function body(): string
    {
        // direction OUT: only wallet_webhooks is written, no AML or TRON calls
        return json_encode([
            'address' => self::ADDRESS,
            'network' => 'tron',
            'txid' => 'tx-1',
            'direction' => 'OUT',
            'amount' => '10',
        ]);
    }

    private function postSigned(string $body, ?string $signature)
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($signature !== null) {
            $headers['HTTP_X_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/webhook/wallet-listener', [], [], [], $headers, $body);
    }

    public function test_valid_signature_is_processed(): void
    {
        $body = $this->body();

        $this->postSigned($body, 'sha256=' . hash_hmac('sha256', $body, self::SECRET))
            ->assertOk();

        $this->assertSame(1, DB::table('wallet_webhooks')->count());
    }

    public function test_wrong_signature_is_rejected(): void
    {
        $body = $this->body();

        $this->postSigned($body, 'sha256=' . hash_hmac('sha256', $body, 'other-secret'))
            ->assertStatus(401);

        $this->assertSame(0, DB::table('wallet_webhooks')->count());
    }

    public function test_signature_of_other_body_is_rejected(): void
    {
        $body = $this->body();

        $this->postSigned($body, 'sha256=' . hash_hmac('sha256', $body . ' ', self::SECRET))
            ->assertStatus(401);

        $this->assertSame(0, DB::table('wallet_webhooks')->count());
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postSigned($this->body(), null)->assertStatus(401);

        $this->assertSame(0, DB::table('wallet_webhooks')->count());
    }

    public function test_empty_configured_secret_rejects_everything(): void
    {
        config(['services.wallet_listener.hmac_secret' => '']);
        $body = $this->body();

        // A signature made with an empty key must not pass either
        $this->postSigned($body, 'sha256=' . hash_hmac('sha256', $body, ''))
            ->assertStatus(401);

        $this->assertSame(0, DB::table('wallet_webhooks')->count());
    }

    public function test_watch_registration_sends_hmac_secret(): void
    {
        config(['services.wallet_listener.url' => 'http://listener.test']);
        Http::fake(['listener.test/*' => Http::response(['ok' => true])]);

        (new WalletListenerService())->sendWallet(Wallet::where('address', self::ADDRESS)->first());

        Http::assertSent(static fn($request) => $request['webhooks'][0]['secret'] === self::SECRET);
    }
}
