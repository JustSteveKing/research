<?php

namespace Database\Seeders;

use App\Enums\KnowledgeState;
use App\Enums\QuestionOrigin;
use App\Enums\QuestionStatus;
use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Models\RunEvent;
use App\Models\Source;
use App\Models\Thread;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * One finished thread, for screenshots and for looking around the app without
 * spending credits.
 *
 * Every claim, source and domain below is invented. The point is to show what
 * the workspace does with a thread that has been researched, distilled and
 * disagreed with — not to assert anything about batteries.
 *
 *     php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $thread = Thread::query()->create([
            'title' => 'Solid-state battery production',
            'seed_question' => 'What is the state of solid-state battery production?',
            'mode' => ResearchMode::Fast,
            'credit_budget' => 2000,
            'max_depth' => 3,
            'auto_follow_up' => true,
        ]);

        $thread->forceFill([
            'status' => ThreadStatus::Idle,
            'knowledge_state' => KnowledgeState::Idle,
            'credits_spent' => 500,
            'brief' => $this->brief(),
            'brief_generated_at' => now()->subMinutes(4),
            'coverage' => $this->coverage(),
            'coverage_mapped_at' => now()->subMinutes(3),
            'last_opened_at' => now()->subDay(),
        ])->save();

        $runs = $this->runs($thread);
        $sources = $this->sources($thread);
        $facts = $this->facts($thread, $sources);

        Contradiction::query()->create([
            'fact_id' => $facts['pilot_line']->id,
            'other_fact_id' => $facts['pilot_line_rival']->id,
            'similarity' => 0.81,
            'note' => 'Both describe the same pilot line, one at 2 GWh and one at 8 GWh. The larger figure counts announced capacity rather than installed.',
        ]);

        $this->followUps($thread);
        $this->events($runs[0]);
        $this->rail();
    }

    /**
     * A couple of quieter threads, so the rail looks like somewhere work has
     * been happening rather than a first run.
     */
    protected function rail(): void
    {
        $others = [
            ['Grid-scale storage economics', 'What does grid-scale storage actually cost to operate?', 4, 12],
            ['Sodium-ion cell chemistry', 'Where is sodium-ion competitive with lithium iron phosphate?', 2, 7],
        ];

        foreach ($others as [$title, $question, $sourceCount, $factCount]) {
            $other = Thread::query()->create([
                'title' => $title,
                'seed_question' => $question,
                'mode' => ResearchMode::Fast,
                'credit_budget' => 2000,
                'max_depth' => 3,
                'auto_follow_up' => false,
            ]);

            $other->forceFill([
                'credits_spent' => 250,
                'last_opened_at' => now()->subDays(3),
                'updated_at' => now()->subDays(2),
            ])->save();

            $sources = Source::factory()->count($sourceCount)->create(['thread_id' => $other->id]);

            Fact::factory()->count($factCount)->create([
                'thread_id' => $other->id,
                'source_id' => $sources->random()->id,
            ]);
        }
    }

    /**
     * @return array<int, ResearchRun>
     */
    protected function runs(Thread $thread): array
    {
        $seed = ResearchRun::query()->create([
            'thread_id' => $thread->id,
            'query' => $thread->seed_question,
            'mode' => ResearchMode::Fast,
            'depth' => 0,
            'status' => RunStatus::Completed,
            'credits_cost' => ResearchMode::Fast->creditCost(),
            'report' => $this->report(),
            'started_at' => now()->subMinutes(22),
            'completed_at' => now()->subMinutes(18),
            'metadata' => ['totalPagesAnalyzed' => 34],
        ]);

        $followUp = ResearchRun::query()->create([
            'thread_id' => $thread->id,
            'parent_run_id' => $seed->id,
            'query' => 'Which solid electrolyte chemistries have reached pilot production?',
            'mode' => ResearchMode::Fast,
            'depth' => 1,
            'status' => RunStatus::Completed,
            'credits_cost' => ResearchMode::Fast->creditCost(),
            'report' => "Sulfide and oxide electrolytes dominate pilot lines. Sulfides conduct\nbest at room temperature but need dry-room handling; oxides tolerate air\nand sinter poorly. Polymer hybrids remain the cheapest to process.",
            'started_at' => now()->subMinutes(12),
            'completed_at' => now()->subMinutes(9),
            'metadata' => ['totalPagesAnalyzed' => 21],
        ]);

        return [$seed, $followUp];
    }

    /**
     * @return array<string, Source>
     */
    protected function sources(Thread $thread): array
    {
        $rows = [
            'review' => [
                'url' => 'https://solidstatereview.org/2026/pilot-lines',
                'title' => 'Pilot lines for solid-state cells: a 2026 survey',
                'summary' => 'Surveys eighteen announced pilot lines and separates installed capacity from announced capacity.',
                'relevance' => 'high',
                'reliability' => 'high',
                'published_at' => now()->subMonths(2),
                'cited_count' => 6,
            ],
            'digest' => [
                'url' => 'https://batterytechdigest.com/solid-state-manufacturing-cost',
                'title' => 'Why solid-state cells still cost more per kWh',
                'summary' => 'Breaks the cost gap into dry-room overhead, stack pressure hardware and low yield.',
                'relevance' => 'high',
                'reliability' => 'medium',
                'published_at' => now()->subMonths(5),
                'cited_count' => 4,
            ],
            'journal' => [
                'url' => 'https://cellchem-journal.org/articles/sulfide-interface-stability',
                'title' => 'Interface stability in sulfide electrolytes under cycling',
                'summary' => 'Measures capacity fade attributable to interfacial resistance growth across 800 cycles.',
                'relevance' => 'medium',
                'reliability' => 'high',
                'published_at' => now()->subMonths(14),
                'cited_count' => 3,
            ],
        ];

        $sources = [];

        foreach ($rows as $key => $row) {
            $sources[$key] = Source::query()->create([
                'thread_id' => $thread->id,
                'domain' => parse_url($row['url'], PHP_URL_HOST),
                'claims' => [],
                ...$row,
            ]);
        }

        return $sources;
    }

    /**
     * @param  array<string, Source>  $sources
     * @return array<string, Fact>
     */
    protected function facts(Thread $thread, array $sources): array
    {
        $rows = [
            'pilot_line' => [
                'statement' => 'The largest installed solid-state pilot line runs at roughly 2 GWh a year.',
                'source' => 'review',
                'confidence' => 0.86,
                'tags' => ['manufacturing', 'capacity'],
            ],
            'pilot_line_rival' => [
                'statement' => 'The largest solid-state pilot line has a stated capacity of 8 GWh a year.',
                'source' => 'digest',
                'confidence' => 0.62,
                'tags' => ['manufacturing', 'capacity'],
            ],
            'cost' => [
                'statement' => 'Cell cost remains three to four times that of an equivalent liquid-electrolyte cell, mostly from yield rather than materials.',
                'source' => 'digest',
                'confidence' => 0.79,
                'tags' => ['cost'],
            ],
            'dry_room' => [
                'statement' => 'Sulfide electrolytes require dry-room handling below 1% relative humidity, which dominates plant overhead.',
                'source' => 'review',
                'confidence' => 0.91,
                'tags' => ['manufacturing', 'chemistry'],
                'pinned' => true,
            ],
            'pressure' => [
                'statement' => 'Most working prototypes still need external stack pressure of several megapascals to keep interfaces in contact.',
                'source' => 'journal',
                'confidence' => 0.83,
                'tags' => ['engineering'],
            ],
            'fade' => [
                'statement' => 'Capacity fade over 800 cycles tracks interfacial resistance growth more closely than lithium inventory loss.',
                'source' => 'journal',
                'confidence' => 0.74,
                'tags' => ['degradation'],
                'stale' => true,
            ],
            'oxide' => [
                'statement' => 'Oxide electrolytes tolerate ambient air but sinter poorly, which caps electrode thickness.',
                'source' => 'review',
                'confidence' => 0.8,
                'tags' => ['chemistry'],
            ],
            'timeline' => [
                'statement' => 'No manufacturer has published a dated commitment to volume production beyond pilot scale.',
                'source' => 'review',
                'confidence' => 0.68,
                'tags' => ['timeline'],
            ],
        ];

        $facts = [];

        foreach ($rows as $key => $row) {
            $facts[$key] = Fact::query()->create([
                'thread_id' => $thread->id,
                'source_id' => $sources[$row['source']]->id,
                'statement' => $row['statement'],
                'tags' => $row['tags'],
                'confidence' => $row['confidence'],
                'pinned_at' => ($row['pinned'] ?? false) ? now()->subHours(2) : null,
                'asserted_at' => ($row['stale'] ?? false)
                    ? now()->subDays((int) config('research.stale_after_days') + 40)
                    : now()->subMinutes(15),
            ]);
        }

        return $facts;
    }

    protected function followUps(Thread $thread): void
    {
        $rows = [
            ['What yield are pilot lines actually reporting?', 0.88, 'Cost is attributed to yield, but no figure was found.'],
            ['How much stack pressure do pouch-format prototypes need?', 0.74, 'Pressure is named as a constraint without a range for pouch cells.'],
            ['Which suppliers make the dry-room equipment?', 0.61, 'The supply chain behind the dominant cost was not covered.'],
        ];

        foreach ($rows as [$question, $score, $rationale]) {
            FollowUpQuestion::query()->create([
                'thread_id' => $thread->id,
                'question' => $question,
                'origin' => QuestionOrigin::Ollama,
                'status' => QuestionStatus::Pending,
                'score' => $score,
                'rationale' => $rationale,
            ]);
        }
    }

    protected function events(ResearchRun $run): void
    {
        $events = [
            ['start', 'Run started'],
            ['planning', 'Planning searches'],
            ['searching', 'Searching the web'],
            ['analyzing', 'Analysing 34 pages'],
            ['outlining', 'Outlining the report'],
            ['writing', 'Writing'],
            ['complete', 'Run complete'],
        ];

        foreach ($events as $sequence => [$event, $message]) {
            RunEvent::query()->create([
                'research_run_id' => $run->id,
                'sequence' => $sequence,
                'event' => $event,
                'message' => $message,
                'created_at' => now()->subMinutes(22 - $sequence),
            ]);
        }
    }

    protected function brief(): string
    {
        return <<<'MARKDOWN'
        Solid-state cells are being built at pilot scale and not beyond it. The
        constraint is manufacturing rather than chemistry: the electrolytes work,
        and the plants that make them do not yet run at yields anyone will
        publish.

        Two costs dominate. Sulfide electrolytes need dry-room handling below 1%
        relative humidity, which sets plant overhead before a single cell is
        made. Interfaces need several megapascals of stack pressure to stay in
        contact, which pushes hardware into the pack rather than the cell.

        Capacity figures should be read carefully — announced capacity and
        installed capacity differ by roughly fourfold in the sources here, and
        that gap is the open disagreement below.
        MARKDOWN;
    }

    protected function report(): string
    {
        return <<<'MARKDOWN'
        ## Where production stands

        Eighteen pilot lines have been announced. Installed capacity is far
        smaller than announced capacity, and the two are often reported
        interchangeably.

        ## What limits scale

        Yield, not materials, accounts for most of the cost gap against
        liquid-electrolyte cells. Dry-room overhead and stack-pressure hardware
        follow.

        ## What is unresolved

        No dated commitment to volume production has been published, and
        cycle-life data past 800 cycles is thin.
        MARKDOWN;
    }

    /**
     * @return list<array{topic: string, why: string|null, facts_count: int, status: string}>
     */
    protected function coverage(): array
    {
        return [
            ['topic' => 'Manufacturing readiness', 'why' => 'The question is about production, not chemistry.', 'facts_count' => 4, 'status' => 'covered'],
            ['topic' => 'Cost per kWh', 'why' => 'Decides whether pilot scale can become volume.', 'facts_count' => 3, 'status' => 'covered'],
            ['topic' => 'Electrolyte chemistries', 'why' => 'Different chemistries hit different manufacturing walls.', 'facts_count' => 2, 'status' => 'thin'],
            ['topic' => 'Cycle life and degradation', 'why' => 'Production only matters if the cells last.', 'facts_count' => 1, 'status' => 'thin'],
            ['topic' => 'Supply chain', 'why' => 'Dry-room and separator equipment gate plant build-out.', 'facts_count' => 0, 'status' => 'open'],
        ];
    }
}
