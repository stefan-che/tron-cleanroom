<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The AMLBot webhook path comes from config (AML_WEBHOOK_PATH), not from a constant in code.
 * No DB required.
 */
class AmlWebhookRouteTest extends TestCase
{
    public function test_aml_webhook_route_uses_configured_path(): void
    {
        $route = Route::getRoutes()->getByName('aml.webhook');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['HEAD'])));
        $this->assertSame(
            trim((string) config('services.aml.webhook_path'), '/'),
            $route->uri()
        );
    }

    public function test_aml_webhook_path_is_never_empty(): void
    {
        $this->assertNotSame('', trim((string) config('services.aml.webhook_path'), '/'));
    }
}
