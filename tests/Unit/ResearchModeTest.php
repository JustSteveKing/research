<?php

use App\Enums\ResearchMode;

it('prices runs the way tabstack bills them', function () {
    expect(ResearchMode::Fast->creditCost())->toBe(250)
        ->and(ResearchMode::Balanced->creditCost())->toBe(350);
});
