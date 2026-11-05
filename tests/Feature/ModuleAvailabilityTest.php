<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAvailable;
use App\Models\Player;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleAvailabilityTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['db']->purge('sqlite');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->actingAs(Player::factory()->withCaptain()->create());
    }

    public function test_working_modules_remain_accessible_and_unmarked(): void
    {
        foreach (['start' => 'start', 'career' => 'career.index', 'profile' => 'profile.index'] as $key => $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Ten moduł jest jeszcze w przygotowaniu.');
            $markup = view('partials.nav-links', ['activeMenu' => $key])->render();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$markup);
            $xpath = new \DOMXPath($document);
            $link = $xpath->query('//a[@href="'.route($route).'"]')->item(0);
            $this->assertNotNull($link);
            $this->assertStringNotContainsString('module-unavailable', $link->getAttribute('class'));
            $this->assertStringNotContainsString('Wkrótce', $link->textContent);
        }
    }

    public function test_sidebar_marks_every_unavailable_module(): void
    {
        $markup = view('partials.nav-links', ['activeMenu' => 'start'])->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$markup);
        $xpath = new \DOMXPath($document);
        foreach (config('thehools.main_menu') as $item) {
            if ($item['available']) {
                continue;
            }
            $link = $xpath->query('//a[@href="'.route($item['route']).'"]')->item(0);
            $this->assertNotNull($link);
            $this->assertStringContainsString('module-unavailable', $link->getAttribute('class'));
            $this->assertStringContainsString('Wkrótce', $link->textContent);
        }
    }

    public function test_unavailable_module_links_and_direct_urls_show_shared_message(): void
    {
        foreach (config('thehools.main_menu') as $item) {
            if (! $item['available']) {
                $this->get(route($item['route']))->assertOk()->assertViewIs('placeholder')
                    ->assertViewHas('activeMenu', $item['key'])
                    ->assertSee('Ten moduł jest jeszcze w przygotowaniu.');
            }
        }
    }

    public function test_mobile_navigation_uses_the_same_availability(): void
    {
        $markup = view('partials.bottom-nav', ['activeMenu' => 'career'])->render();
        $this->assertSame(2, substr_count($markup, 'module-unavailable'));
        $this->assertSame(2, substr_count($markup, 'Wkrótce'));
        $this->assertStringContainsString(route('career.index'), $markup);
        $this->assertStringContainsString(route('start'), $markup);
    }

    public function test_guard_stops_unfinished_handlers_including_nested_write_routes(): void
    {
        $called = false;
        Route::middleware(['web', 'auth', EnsureModuleAvailable::class])->group(function () use (&$called) {
            Route::match(['GET', 'POST'], '/test-unfinished-module', function () use (&$called) {
                $called = true;

                return 'Unfinished functionality';
            })->name('duel.unfinished');
        });
        $this->get('/test-unfinished-module')->assertOk()->assertDontSee('Unfinished functionality');
        $this->post('/test-unfinished-module')->assertStatus(503)->assertSee('Ten moduł jest jeszcze w przygotowaniu.');
        $this->assertFalse($called);
    }
}
