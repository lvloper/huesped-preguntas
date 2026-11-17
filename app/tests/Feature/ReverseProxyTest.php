<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseProxyTest extends TestCase
{
    use RefreshDatabase;

    private function proxy(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->withHeaders([
            'Host' => 'aging.huesped.org.ar',
            'X-Forwarded-Host' => 'eventos.huesped.org.ar',
            'X-Forwarded-Prefix' => '/aging-panel',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
        ]);
    }

    public function test_login_assets_and_form_use_public_prefix(): void
    {
        $this->proxy();
        $this->get('https://aging.huesped.org.ar/login')->assertOk()
            ->assertSee('action="https://eventos.huesped.org.ar/aging-panel/login"', false)
            ->assertSee('https://eventos.huesped.org.ar/aging-panel/images/fundacion-huesped.png', false);
        $this->get('https://aging.huesped.org.ar/')->assertRedirect('https://eventos.huesped.org.ar/aging-panel/panel');
    }

    public function test_panel_and_form_backend_preserve_prefix(): void
    {
        $this->proxy();
        $this->actingAs(new User(['email' => User::PANEL_EMAIL]))->get('https://aging.huesped.org.ar/panel')->assertOk()
            ->assertSee('data-panel-url="https://eventos.huesped.org.ar/aging-panel/panel"', false)
            ->assertSee('https://eventos.huesped.org.ar/aging-panel/panel.js', false);
        $this->get('https://aging.huesped.org.ar/formulario')->assertOk()->assertSee('data-aging-backend-url="https://eventos.huesped.org.ar/aging-panel"', false);
    }

    public function test_production_uses_canonical_url_when_upstream_overwrites_host(): void
    {
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://eventos.huesped.org.ar/aging-panel']);
        (new AppServiceProvider($this->app))->boot();
        $this->withHeaders(['Host' => 'aging.huesped.org.ar'])->get('https://aging.huesped.org.ar/login')
            ->assertOk()->assertSee('action="https://eventos.huesped.org.ar/aging-panel/login"', false);
    }

    public function test_login_discards_upstream_intended_url_and_redirects_once(): void
    {
        $this->proxy();
        $this->withHeader('X-Forwarded-Host', 'aging.huesped.org.ar');
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://eventos.huesped.org.ar/aging-panel']);
        (new AppServiceProvider($this->app))->boot();
        User::factory()->create(['email' => User::PANEL_EMAIL]);
        $this->withSession(['_token' => 'proxy-test', 'url.intended' => 'https://aging.huesped.org.ar/aging-panel/panel'])
            ->post('https://aging.huesped.org.ar/login', ['_token' => 'proxy-test', 'email' => User::PANEL_EMAIL, 'password' => 'password'])
            ->assertRedirect('https://eventos.huesped.org.ar/aging-panel/panel')
            ->assertSessionMissing('url.intended');
        $this->assertAuthenticated();
    }

    public function test_untrusted_forwarding_headers_are_ignored(): void
    {
        $this->proxy();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->get('https://aging.huesped.org.ar/login')->assertOk()
            ->assertDontSee('action="https://eventos.huesped.org.ar/aging-panel/login"', false);
    }
}
