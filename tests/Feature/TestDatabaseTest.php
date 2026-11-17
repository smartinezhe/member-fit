<?php

use Illuminate\Support\Facades\DB;

it('runs against the dedicated testing database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('member_fit_testing');
});
