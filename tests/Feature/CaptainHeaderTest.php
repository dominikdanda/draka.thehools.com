<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Captain;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaptainHeaderTest extends TestCase
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
        $this->travelTo(now()->startOfSecond());
    }

    private function header(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($document);
    }

    public function test_single_captain_name_is_in_shared_header_on_gameplay_pages(): void
    {
        $player = Player::factory()->withCaptain()->create();
        $captain = $player->captains()->sole();
        $this->actingAs($player);
        foreach (['start', 'career.index', 'captains.index'] as $route) {
            $response = $this->get(route($route))->assertOk();
            $xpath = $this->header($response->getContent());
            $name = $xpath->query('//header//div[@class="captain-selector"]/strong')->item(0);
            $this->assertNotNull($name);
            $this->assertSame($captain->name, $name->textContent);
            $this->assertSame(0, $xpath->query('//header//select[@name="captain_id"]')->length);
        }
    }

    public function test_selector_contains_only_owned_active_captains_and_current_selection(): void
    {
        $player = Player::factory()->create();
        $first = Captain::factory()->for($player)->create(['name' => 'FirstCaptain']);
        $second = Captain::factory()->for($player)->create(['name' => 'SecondCaptain']);
        Captain::factory()->for($player)->dismissed()->create(['name' => 'DismissedCaptain']);
        Captain::factory()->create(['name' => 'ForeignCaptain']);
        $response = $this->actingAs($player)->withSession(['current_captain_id' => $second->id])
            ->get(route('start'))->assertOk()->assertDontSee('DismissedCaptain')->assertDontSee('ForeignCaptain');
        $xpath = $this->header($response->getContent());
        $options = $xpath->query('//header//select[@name="captain_id"]/option');
        $this->assertSame(2, $options->length);
        $this->assertSame((string) $first->id, $options->item(0)->getAttribute('value'));
        $this->assertSame((string) $second->id, $options->item(1)->getAttribute('value'));
        $this->assertTrue($options->item(1)->hasAttribute('selected'));
        $this->assertSame(route('captains.select'), $xpath->query('//header//form')->item(0)->getAttribute('action'));
        $form = $xpath->query('//header//form')->item(0);
        $this->assertSame('POST', $form->getAttribute('method'));
        $token = $xpath->query('.//input[@name="_token"]', $form)->item(0);
        $this->assertNotNull($token);
        $this->assertSame(session()->token(), $token->getAttribute('value'));
        $button = $xpath->query('.//button[@data-captain-submit]', $form)->item(0);
        $this->assertNotNull($button);
        $this->assertSame('submit', $button->getAttribute('type'));
        $this->assertFalse($button->hasAttribute('hidden'));
        $this->assertFalse($button->hasAttribute('disabled'));
    }

    public function test_switching_returns_to_career_page_and_updates_header(): void
    {
        $player = Player::factory()->create();
        $captains = Captain::factory()->count(2)->for($player)->create();
        $campaign = Campaign::create(['slug' => 'classic', 'name' => 'Classic']);
        $chapter = $campaign->chapters()->create(['number' => 1, 'title' => 'Chapter']);
        $url = route('career.chapters.show', [$campaign, $chapter]);
        $path = parse_url($url, PHP_URL_PATH);
        $this->actingAs($player)->withSession(['current_captain_id' => $captains[0]->id])
            ->post(route('captains.select'), ['captain_id' => $captains[1]->id, 'return_to' => $path])
            ->assertRedirect($url)->assertSessionHas('current_captain_id', $captains[1]->id);
        $response = $this->get($url)->assertOk();
        $selected = $this->header($response->getContent())->query('//header//select/option[@selected]')->item(0);
        $this->assertSame((string) $captains[1]->id, $selected->getAttribute('value'));
    }

    public function test_foreign_and_dismissed_captains_are_rejected(): void
    {
        $player = Player::factory()->withCaptain()->create();
        $own = $player->captains()->sole();
        $foreign = Captain::factory()->create();
        $dismissed = Captain::factory()->for($player)->dismissed()->create();
        foreach ([$foreign->id, $dismissed->id] as $id) {
            $this->actingAs($player)->withSession(['current_captain_id' => $own->id])
                ->post(route('captains.select'), ['captain_id' => $id, 'return_to' => '/start'])
                ->assertSessionHasErrors('captain_id')->assertSessionHas('current_captain_id', $own->id);
        }
    }

    public function test_invalid_session_selection_is_resolved_by_existing_service(): void
    {
        $player = Player::factory()->withCaptain()->create();
        $own = $player->captains()->sole();
        $foreign = Captain::factory()->create();
        $dismissed = Captain::factory()->for($player)->dismissed()->create();
        foreach ([$foreign->id, $dismissed->id, 999999] as $id) {
            $response = $this->actingAs($player)->withSession(['current_captain_id' => $id])
                ->get(route('start'))->assertOk()->assertSessionHas('current_captain_id', $own->id);
            $this->assertSame($own->name, $this->header($response->getContent())
                ->query('//header//div[@class="captain-selector"]/strong')->item(0)->textContent);
        }
    }

    public function test_header_reuses_resolved_captain_context_without_extra_queries(): void
    {
        $player = Player::factory()->withCaptain()->create();
        $this->actingAs($player);
        foreach (['start', 'captains.index'] as $route) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get(route($route))->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertCount(1, array_filter($queries, fn ($query) => str_contains($query['query'], 'from "captains"')));
        }
    }

    public function test_return_destination_cannot_be_external_or_an_authentication_route(): void
    {
        $player = Player::factory()->withCaptain()->create();
        $captain = $player->captains()->sole();
        foreach (['https://evil.example/start', '//evil.example', '/login/'.str_repeat('a', 40), '/unknown', ['array']] as $path) {
            $this->actingAs($player)->post(route('captains.select'), ['captain_id' => $captain->id, 'return_to' => $path])
                ->assertRedirect(route('captains.index'));
        }
    }

    public function test_unauthenticated_header_and_selection_remain_protected(): void
    {
        $this->get(route('start'))->assertRedirect(config('thehools.lobby_url'));
        $this->get(route('career.index'))->assertRedirect(config('thehools.lobby_url'));
        $this->postJson(route('captains.select'), ['captain_id' => 1, 'return_to' => '/start'])->assertUnauthorized();
    }
}
