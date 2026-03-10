<?php

beforeEach(function () {
    skipIfDisabled('feature');
});

test('example feature test', function () {
    expect(true)->toBeTrue();
});

test('application uses testing environment', function () {
    expect(app()->environment())->toBe('testing');
});
