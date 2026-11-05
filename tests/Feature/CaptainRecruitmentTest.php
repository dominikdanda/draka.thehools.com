<?php

namespace Tests\Feature;

use App\Models\Captain;
use App\Models\Player;
use Tests\TestCase;

class CaptainRecruitmentTest extends TestCase
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
        config(['thehools.captains.starting_limit' => 1]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->travelTo(now()->startOfSecond());
    }

    public function test_first_captain_is_recruited_for_free_and_selected(): void
    {
        $player = Player::factory()->create();
        app(\App\Services\Player\PlayerRegenerationService::class)->apply($player);
        $stats = $player->stats->getAttributes();
        $resources = $player->resources->getAttributes();
        $this->actingAs($player)->get(route('captains.index'))->assertOk()->assertSee('Zrekrutuj kapitana');
        $this->post(route('captains.store'), ['name' => '  Łukasz Kowalski  '])->assertRedirect(route('start'));
        $captain = $player->captains()->sole();
        $this->assertSame('Łukasz Kowalski', $captain->name);
        $this->assertNull($captain->dismissed_at);
        $this->assertSame($captain->id, session('current_captain_id'));
        $this->get(route('captains.index'))->assertOk()->assertSee('Łukasz Kowalski')
            ->assertSee('Aktualnie wybrany kapitan')->assertDontSee('Zrekrutuj kapitana');
        $this->assertSame($stats, $player->stats->fresh()->getAttributes());
        $this->assertSame($resources, $player->resources->fresh()->getAttributes());
    }

    public function test_invalid_names_are_rejected_and_errors_are_visible(): void
    {
        $this->actingAs(Player::factory()->create());
        foreach (['', '  ', 'A', str_repeat('a', 51), '<script>alert(1)</script>', ['array']] as $name) {
            $this->from(route('captains.index'))->post(route('captains.store'), ['name' => $name])
                ->assertRedirect(route('captains.index'))->assertSessionHasErrors('name');
            $response = $this->get(route('captains.index'))->assertOk()
                ->assertSee('aria-invalid="true"', false);
            if (is_array($name)) {
                $response->assertSee('name="name" value=""', false)
                    ->assertSee('Imię kapitana musi być tekstem.');
            }
        }
        $this->assertDatabaseCount('captains', 0);
    }

    public function test_rejected_string_name_is_preserved_and_html_escaped(): void
    {
        $name = '<script>alert("name")</script>';
        $this->actingAs(Player::factory()->create())
            ->from(route('captains.index'))->post(route('captains.store'), ['name' => $name])
            ->assertRedirect(route('captains.index'))->assertSessionHasErrors('name');

        $this->get(route('captains.index'))->assertOk()
            ->assertSee('name="name" value="'.e($name).'"', false)
            ->assertDontSee($name, false);
        $this->assertDatabaseCount('captains', 0);
    }

    public function test_repeat_recruitment_cannot_exceed_the_limit(): void
    {
        $player = Player::factory()->create();
        $this->actingAs($player)->post(route('captains.store'), ['name' => 'First']);
        $first = $player->captains()->sole();
        $this->from(route('captains.index'))->post(route('captains.store'), ['name' => 'Second'])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('captains', 1);
        $this->assertSame($first->id, session('current_captain_id'));
        $this->assertSame('First', $first->fresh()->name);
        $this->get(route('captains.index'))->assertSee('Osiągnięto limit aktywnych kapitanów.');
    }

    public function test_limit_is_taken_from_configuration(): void
    {
        config(['thehools.captains.starting_limit' => 0]);
        $this->actingAs(Player::factory()->create())->post(route('captains.store'), ['name' => 'Captain'])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('captains', 0);
    }

    public function test_selection_requires_ownership_and_active_status(): void
    {
        $player = Player::factory()->create();
        $own = Captain::factory()->for($player)->create();
        $other = Captain::factory()->create();
        $dismissed = Captain::factory()->for($player)->dismissed()->create();
        $this->actingAs($player)->post(route('captains.select'), ['captain_id' => $own->id])
            ->assertRedirect(route('captains.index'))->assertSessionHas('current_captain_id', $own->id);
        foreach ([$other->id, $dismissed->id, 999999, 'invalid', -1, null] as $id) {
            $this->post(route('captains.select'), ['captain_id' => $id])->assertSessionHasErrors('captain_id');
            $this->assertSame($own->id, session('current_captain_id'));
        }
        $this->assertDatabaseCount('captains', 3);
    }

    public function test_session_selection_persists_between_pages(): void
    {
        $player = Player::factory()->create();
        $captain = Captain::factory()->for($player)->create();
        $this->actingAs($player)->get(route('captains.index'))->assertSee('Aktualnie wybrany kapitan');
        $this->post(route('captains.select'), ['captain_id' => $captain->id])->assertSessionHas('current_captain_id', $captain->id);
        $this->get(route('start'))->assertOk();
        $this->get(route('career.index'))->assertOk();
        $this->get(route('captains.index'))->assertSee('Aktualnie wybrany kapitan');
        $this->assertSame($captain->id, session('current_captain_id'));
    }

    public function test_invalid_or_stale_session_selection_is_cleared(): void
    {
        $player = Player::factory()->create();
        $other = Captain::factory()->create();
        $dismissed = Captain::factory()->for($player)->dismissed()->create();
        foreach ([$other->id, $dismissed->id, 999999] as $id) {
            $this->actingAs($player)->withSession(['current_captain_id' => $id])->get(route('captains.index'))
                ->assertOk()->assertSessionMissing('current_captain_id')->assertDontSee($other->name);
        }
    }

    public function test_dismissed_history_is_preserved_and_does_not_use_active_slots(): void
    {
        $player = Player::factory()->create();
        $dismissed = Captain::factory()->for($player)->dismissed()->create(['name' => 'HistoricalCaptain']);
        $before = $dismissed->fresh()->getAttributes();
        $this->actingAs($player)->get(route('captains.index'))->assertSee('Zrekrutuj kapitana')->assertDontSee('HistoricalCaptain');
        $this->post(route('captains.store'), ['name' => 'ActiveCaptain'])->assertRedirect(route('start'));
        $this->assertEquals($before, $dismissed->fresh()->getAttributes());
        $this->assertSame(1, $player->captains()->active()->count());
        $this->assertDatabaseCount('captains', 2);
    }

    public function test_unauthenticated_requests_cannot_access_or_recruit_or_select(): void
    {
        $this->get(route('captains.index'))->assertRedirect(config('thehools.lobby_url'));
        $this->post(route('captains.store'), ['name' => 'GuestCaptain'])->assertRedirect(config('thehools.lobby_url'));
        $this->post(route('captains.select'), ['captain_id' => 1])->assertRedirect(config('thehools.lobby_url'));
        $this->getJson(route('captains.index'))->assertUnauthorized();
        $this->postJson(route('captains.store'), ['name' => 'GuestCaptain'])->assertUnauthorized();
        $this->postJson(route('captains.select'), ['captain_id' => 1])->assertUnauthorized();
        $this->assertDatabaseCount('captains', 0);
    }

    public function test_navigation_keeps_dashboard_and_career_and_adds_available_captains(): void
    {
        $response = $this->actingAs(Player::factory()->withCaptain()->create())->get(route('start'))->assertOk();
        foreach (['start', 'career.index', 'captains.index'] as $route) {
            $response->assertSee(route($route), false);
        }
        $response->assertSee('Kapitanowie');
        foreach (['partials.nav-links', 'partials.bottom-nav'] as $view) {
            $markup = view($view, ['activeMenu' => 'captains'])->render();
            $this->assertStringContainsString(route('captains.index'), $markup);
            $this->assertStringContainsString('Kapitanowie', $markup);
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$markup);
            $link = (new \DOMXPath($document))->query('//a[@href="'.route('captains.index').'"]')->item(0);
            $this->assertNotNull($link);
            $this->assertStringNotContainsString('module-unavailable', $link->getAttribute('class'));
        }
        $this->get(route('career.index'))->assertOk();
        $this->assertTrue(collect(config('thehools.main_menu'))->firstWhere('key', 'captains')['available']);
    }
}
