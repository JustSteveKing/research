<?php

use App\Jobs\DistillRunJob;
use App\Jobs\ProposeFollowUpsJob;
use App\Jobs\RunResearchJob;
use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\ResearchRun;
use App\Models\Thread;

it('keeps web research and local model work on separate queues', function () {
    $run = ResearchRun::factory()->create();

    expect((new RunResearchJob($run))->queue)->toBe('research')
        ->and((new DistillRunJob($run))->queue)->toBe('knowledge')
        ->and((new ProposeFollowUpsJob($run))->queue)->toBe('knowledge')
        ->and((new SynthesizeThreadBriefJob(Thread::factory()->create()))->queue)->toBe('knowledge');
});

it('gives every job a timeout the worker and retry_after can outlast', function () {
    $run = ResearchRun::factory()->create();

    $workers = collect(config('nativephp.queue_workers'));
    $retryAfter = (int) config('queue.connections.database.retry_after');

    $jobs = [
        'research' => new RunResearchJob($run),
        'knowledge' => new DistillRunJob($run),
    ];

    foreach ($jobs as $queue => $job) {
        $worker = $workers->first(fn (array $config): bool => in_array($queue, $config['queues'], true));

        expect($job->timeout)->toBeLessThan($worker['timeout'])
            ->and($worker['timeout'])->toBeLessThan($retryAfter);
    }
});
