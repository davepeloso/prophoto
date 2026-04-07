<?php

beforeEach(function () {
    skipIfDisabled('unit');
});

test('example unit test', function () {
    expect(true)->toBeTrue();
});

test('can access package config', function () {
    $config = config('ingest');

    expect($config)->toBeArray();
});
