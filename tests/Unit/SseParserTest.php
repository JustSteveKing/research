<?php

use App\Support\SseParser;

function collectEvents(SseParser $parser, string $stream, int $chunkSize): array
{
    $events = [];
    $collect = function (string $event, array $data) use (&$events): void {
        $events[] = [$event, $data];
    };

    foreach (str_split($stream, $chunkSize) as $chunk) {
        $parser->push($chunk, $collect);
    }

    $parser->finish($collect);

    return $events;
}

it('parses events regardless of chunk boundaries', function (int $chunkSize) {
    $stream = "event: start\ndata: {\"message\":\"Starting\"}\n\n"
        ."event: searching:end\ndata: {\"resultCount\":12}\n\n";

    $events = collectEvents(new SseParser, $stream, $chunkSize);

    expect($events)->toHaveCount(2)
        ->and($events[0][0])->toBe('start')
        ->and($events[0][1]['message'])->toBe('Starting')
        ->and($events[1][0])->toBe('searching:end')
        ->and($events[1][1]['resultCount'])->toBe(12);
})->with([1, 3, 17, 4096]);

it('ignores comments and keepalives', function () {
    $stream = ": keepalive\n\nevent: start\ndata: {\"a\":1}\n\n";

    expect(collectEvents(new SseParser, $stream, 8))->toHaveCount(1);
});

it('joins multi-line data payloads', function () {
    $stream = "event: complete\ndata: {\"report\":\n data: \"hi\"}\n\n";

    $events = collectEvents(new SseParser, "event: complete\ndata: {\"report\":\ndata: \"hi\"}\n\n", 5);

    expect($events[0][1]['report'])->toBe('hi');
});

it('falls back to the payload event name when no event field is sent', function () {
    $events = collectEvents(new SseParser, "data: {\"event\":\"iteration:start\",\"iteration\":2}\n\n", 6);

    expect($events[0][0])->toBe('iteration:start');
});

it('keeps unparsable payloads as raw data', function () {
    $events = collectEvents(new SseParser, "event: error\ndata: not json\n\n", 4);

    expect($events[0][0])->toBe('error')
        ->and($events[0][1]['raw'])->toBe('not json');
});

it('emits a trailing event that was never terminated by a blank line', function () {
    $events = collectEvents(new SseParser, "event: complete\ndata: {\"report\":\"done\"}", 7);

    expect($events)->toHaveCount(1)
        ->and($events[0][1]['report'])->toBe('done');
});

it('drops the [DONE] sentinel', function () {
    expect(collectEvents(new SseParser, "data: [DONE]\n\n", 4))->toBeEmpty();
});
