<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `meta.aspect_ratio` made the Facebook and Instagram publishers crop each
     * image on the server. Images now publish at their own ratio, so the key
     * is dropped from every target; meta merges on update would keep it
     * forever otherwise.
     */
    public function up(): void
    {
        DB::table('post_platforms')
            ->whereNotNull('meta->aspect_ratio')
            ->select(['id', 'meta'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);

                    if (! is_array($meta)) {
                        continue;
                    }

                    unset($meta['aspect_ratio']);

                    DB::table('post_platforms')
                        ->where('id', $row->id)
                        ->update(['meta' => json_encode($meta)]);
                }
            });
    }
};
