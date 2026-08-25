<?php

use App\Support\Vector;

it('scores identical vectors as 1 and orthogonal vectors as 0', function () {
    expect(Vector::cosine([1.0, 0.0], [1.0, 0.0]))->toBe(1.0)
        ->and(Vector::cosine([1.0, 0.0], [0.0, 1.0]))->toBe(0.0);
});

it('returns zero for mismatched or empty vectors', function () {
    expect(Vector::cosine([1.0, 2.0], [1.0]))->toBe(0.0)
        ->and(Vector::cosine([], []))->toBe(0.0)
        ->and(Vector::cosine([0.0, 0.0], [1.0, 1.0]))->toBe(0.0);
});

it('ranks candidates best first and honours the limit', function () {
    $ranked = Vector::topK([1.0, 0.0], [
        'far' => [0.0, 1.0],
        'near' => [0.9, 0.1],
        'mid' => [0.6, 0.6],
        'missing' => null,
    ], 2);

    expect(array_keys($ranked))->toBe(['near', 'mid']);
});

it('reports the best similarity across candidates', function () {
    expect(Vector::maxSimilarity([1.0, 0.0], [[0.0, 1.0], [0.95, 0.05]]))->toBeGreaterThan(0.99);
});
