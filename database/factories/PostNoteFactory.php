<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PostNote> */
class PostNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->sentence(),
            'reactions' => [],
        ];
    }

    public function reply(PostNote $parent): static
    {
        return $this->state(fn () => [
            'post_id' => $parent->post_id,
            'parent_id' => $parent->id,
        ]);
    }
}
