<?php

use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Jobs\RunResearchJob;
use App\Models\ResearchRun;
use App\Models\Thread;
use App\Services\Research\SourceRecorder;
use App\Services\Tabstack\FakeTabstackClient;
use App\Services\Tabstack\TabstackClient;
use App\Services\Tabstack\TabstackException;
use App\Support\DesktopNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * Swap in a client that fails a given number of times before replaying the
 * recorded stream, so a retry can be observed without the network.
 */
function flakyTabstack(TabstackException $failure, int $failures = 1): object
{
    $client = new class($failure, $failures) implements TabstackClient
    {
        public int $calls = 0;

        public function __construct(protected TabstackException $failure, protected int $failures) {}

        public function research(string $query, ResearchMode $mode, Closure $onEvent, bool $noCache = false): void
        {
            $this->calls++;

            if ($this->calls <= $this->failures) {
                $onEvent('start', ['message' => 'Starting research']);

                throw $this->failure;
            }

            (new FakeTabstackClient(database_path('fixtures/research-complete.sse')))
                ->research($query, $mode, $onEvent, $noCache);
        }
    };

    app()->instance(TabstackClient::class, $client);

    return $client;
}

/**
 * Run the job the way a queue worker does: handle() throwing is the signal to
 * retry, which dispatchSync would instead turn straight into a failure.
 */
function runResearchJob(ResearchRun $run): void
{
    (new RunResearchJob($run))->handle(
        app(TabstackClient::class),
        app(SourceRecorder::class),
        app(DesktopNotifier::class),
    );
}

it('retries a dropped connection instead of burning the run', function () {
    fakeOllama();

    $client = flakyTabstack(TabstackException::transport(new RuntimeException('Connection reset by peer')));
    $run = ResearchRun::factory()->create();

    // First attempt throws, so the worker hands it back; the second wins.
    expect(fn () => runResearchJob($run))->toThrow(TabstackException::class);

    expect($run->fresh()->status)->toBe(RunStatus::Streaming)
        ->and($run->fresh()->error)->toBeNull();

    runResearchJob($run);

    expect($client->calls)->toBe(2)
        ->and($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('does not retry a request Tabstack rejected', function () {
    fakeOllama();

    $client = flakyTabstack(TabstackException::response(401, 'Unauthorized'), failures: 5);
    $run = ResearchRun::factory()->create();

    runResearchJob($run);

    expect($client->calls)->toBe(1)
        ->and($run->fresh()->status)->toBe(RunStatus::Failed)
        ->and($run->fresh()->error)->toContain('401');
});

it('treats an error event as final, however it is worded', function () {
    fakeTabstack('research-error');

    $run = ResearchRun::factory()->create();

    runResearchJob($run);

    expect($run->fresh()->status)->toBe(RunStatus::Failed);
});

it('clears the partial event trail before replaying a stream', function () {
    fakeOllama();

    $client = flakyTabstack(TabstackException::transport(new RuntimeException('Connection reset by peer')));
    $run = ResearchRun::factory()->create();

    rescue(fn () => runResearchJob($run));

    $afterFirst = $run->fresh()->events()->count();

    runResearchJob($run);

    expect($afterFirst)->toBeGreaterThan(0)
        ->and($client->calls)->toBe(2)
        ->and($run->fresh()->events()->where('event', 'start')->count())->toBe(1);
});

it('holds the credit reservation across a retry rather than settling twice', function () {
    fakeOllama();

    flakyTabstack(TabstackException::transport(new RuntimeException('Connection reset by peer')));

    $thread = Thread::factory()->create(['credit_budget' => 2000, 'credits_reserved' => 250]);
    $run = ResearchRun::factory()->for($thread)->create(['credits_cost' => 250]);

    rescue(fn () => runResearchJob($run));

    expect($thread->fresh()->credits_reserved)->toBe(250)
        ->and($thread->fresh()->credits_spent)->toBe(0);

    runResearchJob($run);

    expect($thread->fresh()->credits_reserved)->toBe(0)
        ->and($thread->fresh()->credits_spent)->toBe(250);
});

it('waits its turn rather than streaming two runs at once', function () {
    Queue::fake();

    $lock = Cache::lock('tabstack-research', 60);
    $lock->get();

    $run = ResearchRun::factory()->create();

    runResearchJob($run);

    // Still queued: the job put itself back instead of opening a second stream.
    expect($run->fresh()->status)->toBe(RunStatus::Queued)
        ->and($run->fresh()->started_at)->toBeNull();

    $lock->release();
});
