<?php

use App\Services\Settings\AppSettings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');

    fakeOllama();

    // A packaged app has neither, which is the state this covers.
    config(['services.tabstack.key' => null, 'research.offline' => false]);
});

it('asks for a key on the first window', function (): void {
    $this->get(route('threads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('setup.needs_tabstack_key', true),
        );
});

it('stops asking once a key is saved', function (): void {
    $this->patch(route('settings.update'), ['tabstack_api_key' => 'tbk_live']);

    $this->get(route('threads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('setup.needs_tabstack_key', false),
        );
});

it('stops asking once the prompt is skipped', function (): void {
    $this->post(route('settings.skip-setup'))->assertRedirect();

    expect(app(AppSettings::class)->tabstackKey())->toBeNull();

    $this->get(route('threads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('setup.needs_tabstack_key', false),
        );
});

it('does not ask while offline mode is replaying fixtures on purpose', function (): void {
    config(['research.offline' => true]);

    $this->get(route('threads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('setup.needs_tabstack_key', false),
        );
});
