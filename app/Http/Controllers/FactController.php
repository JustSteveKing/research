<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFactRequest;
use App\Models\Fact;
use App\Models\Thread;
use App\Services\Ollama\OllamaClient;
use App\Services\Research\RunDispatcher;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

/**
 * Reader curation of the distilled knowledge. An edited or pinned fact is no
 * longer the model's, so KnowledgeRebuilder keeps it; a dismissed one stays in
 * the table precisely so distillation cannot add it back.
 */
class FactController extends Controller
{
    public function update(UpdateFactRequest $request, Thread $thread, Fact $fact, OllamaClient $ollama): RedirectResponse
    {
        abort_unless($fact->thread_id === $thread->id, 404);

        $validated = $request->validated();
        $changes = [];
        $message = 'Fact updated';

        if (array_key_exists('statement', $validated) && $validated['statement'] !== $fact->statement) {
            $changes['statement'] = $validated['statement'];
            $changes['edited_at'] = now();
            // Retrieval and duplicate detection both read the vector, so an
            // edited statement has to be re-embedded or it stops matching.
            $changes['embedding'] = $ollama->embedOne($validated['statement']) ?? $fact->embedding;
            $message = 'Fact rewritten';
        }

        if (array_key_exists('confidence', $validated)) {
            $changes['confidence'] = (float) $validated['confidence'];
            $changes['edited_at'] = now();
        }

        if (array_key_exists('pinned', $validated)) {
            $changes['pinned_at'] = $validated['pinned'] ? now() : null;
            $message = $validated['pinned'] ? 'Pinned · kept through rebuilds' : 'Unpinned';
        }

        if (array_key_exists('dismissed', $validated)) {
            $changes['dismissed_at'] = $validated['dismissed'] ? now() : null;
            $message = $validated['dismissed'] ? 'Dismissed · will not come back' : 'Restored';
        }

        if ($changes === []) {
            return back();
        }

        $fact->forceFill($changes)->save();

        return Toast::attach(back(), $message, 'knowledge');
    }

    /**
     * Research whether a fact still holds.
     *
     * Staleness marks the problem; this is the fix. The run is phrased as a
     * verification question rather than the bare statement so the report
     * comes back about whether it is still true, not about the topic at large.
     */
    public function recheck(Thread $thread, Fact $fact, RunDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($fact->thread_id === $thread->id, 404);

        $run = $dispatcher->dispatch(
            thread: $thread,
            query: "Is this still accurate as of today, and what has changed since? {$fact->statement}",
            depth: (int) $thread->runs()->max('depth'),
        );

        if ($run === null) {
            return back()->withErrors([
                'fact' => "Re-checking costs {$thread->mode->creditCost()} credits but only {$thread->availableCredits()} remain in the budget.",
            ]);
        }

        return Toast::attach(back(), "Re-checking · {$run->credits_cost} cr", 'signal');
    }

    /**
     * Delete a fact outright. Dismissing is nearly always the better move —
     * a deleted fact can be re-distilled by the next run.
     */
    public function destroy(Thread $thread, Fact $fact): RedirectResponse
    {
        abort_unless($fact->thread_id === $thread->id, 404);

        $fact->delete();

        return Toast::attach(back(), 'Fact deleted');
    }
}
