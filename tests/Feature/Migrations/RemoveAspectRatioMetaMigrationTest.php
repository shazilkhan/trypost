<?php

declare(strict_types=1);

use App\Models\PostPlatform;

beforeEach(function () {
    $this->migration = require database_path('migrations/2026_10_04_202400_remove_aspect_ratio_from_post_platforms_meta.php');
});

test('the aspect ratio is dropped from every target and the rest of its meta is kept', function () {
    $cropped = PostPlatform::factory()->create(['meta' => ['aspect_ratio' => '4:5', 'is_ai_generated' => true]]);
    $onlyRatio = PostPlatform::factory()->create(['meta' => ['aspect_ratio' => 'original']]);
    $untouched = PostPlatform::factory()->create(['meta' => ['link_preview' => false]]);
    $empty = PostPlatform::factory()->create(['meta' => null]);

    $this->migration->up();
    $this->migration->up();

    expect($cropped->fresh()->meta)->toEqual(['is_ai_generated' => true])
        ->and($onlyRatio->fresh()->meta)->toEqual([])
        ->and($untouched->fresh()->meta)->toEqual(['link_preview' => false])
        ->and($empty->fresh()->meta)->toBeNull();
});
