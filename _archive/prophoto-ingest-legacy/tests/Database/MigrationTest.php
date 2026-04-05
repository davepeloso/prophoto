<?php

use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    skipIfDisabled('migrations');
});

test('proxy images table exists', function () {
    expect(Schema::hasTable('ingest_proxy_images'))->toBeTrue();
});

test('images table exists', function () {
    expect(Schema::hasTable('ingest_images'))->toBeTrue();
});

test('settings table exists', function () {
    expect(Schema::hasTable('ingest_settings'))->toBeTrue();
});

test('proxy images table has required columns', function () {
    expect(Schema::hasColumns('ingest_proxy_images', [
        'id',
        'uuid',
        'filename',
        'temp_path',
    ]))->toBeTrue();
});
