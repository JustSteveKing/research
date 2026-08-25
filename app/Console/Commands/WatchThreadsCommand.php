<?php

namespace App\Console\Commands;

use App\Services\Research\ThreadWatcher;
use Illuminate\Console\Command;

class WatchThreadsCommand extends Command
{
    protected $signature = 'research:watch';

    protected $description = 'Re-research every watched thread whose cadence has elapsed';

    public function handle(ThreadWatcher $watcher): int
    {
        $watched = $watcher->sweep();

        if ($watched->isEmpty()) {
            $this->components->info('No thread is due a watch run.');

            return self::SUCCESS;
        }

        foreach ($watched as $thread) {
            $this->components->twoColumnDetail(
                $thread->title,
                "queued · {$thread->mode->creditCost()} cr",
            );
        }

        return self::SUCCESS;
    }
}
