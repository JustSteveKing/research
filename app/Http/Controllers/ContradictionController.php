<?php

namespace App\Http\Controllers;

use App\Models\Contradiction;
use App\Models\Thread;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContradictionController extends Controller
{
    /**
     * Settle a flagged disagreement. The pair is kept so the same two facts
     * are never re-flagged; resolving only stops it being shown as open.
     */
    public function update(Request $request, Thread $thread, Contradiction $contradiction): RedirectResponse
    {
        abort_unless(
            $contradiction->fact->thread_id === $thread->id || $contradiction->otherFact->thread_id === $thread->id,
            404,
        );

        $resolved = $request->boolean('resolved', true);

        $contradiction->forceFill(['resolved_at' => $resolved ? now() : null])->save();

        return Toast::attach(back(), $resolved ? 'Marked as settled' : 'Reopened');
    }
}
