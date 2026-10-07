<?php

declare(strict_types=1);

use App\Broadcasting\UserAiVideoGenerationChannel;
use App\Models\User;

test('user can join their own video generation channel', function () {
    $user = User::factory()->create();
    $channel = new UserAiVideoGenerationChannel;

    expect($channel->join($user, $user, 'some-uuid'))->toBeTrue();
});

test('user cannot join another users video generation channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $channel = new UserAiVideoGenerationChannel;

    expect($channel->join($user, $other, 'some-uuid'))->toBeFalse();
});
