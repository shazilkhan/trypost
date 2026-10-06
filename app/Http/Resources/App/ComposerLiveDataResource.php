<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the composer reads fresh on every open: the instants already held on
 * each channel, Pinterest boards and TikTok creator info, plus the composer
 * bundle when the client's once-prop key is stale (the user changed it without
 * a page visit, e.g. a channel time zone or a signature saved over useHttp).
 */
class ComposerLiveDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'composer' => $this->when(data_get($this->resource, 'composer') !== null, fn (): mixed => data_get($this->resource, 'composer')),
            'composerKey' => $this->when(data_get($this->resource, 'composerKey') !== null, fn (): mixed => data_get($this->resource, 'composerKey')),
            'takenSlots' => (object) data_get($this->resource, 'takenSlots', []),
            'pinterestBoards' => (object) data_get($this->resource, 'pinterestBoards', []),
            'tiktokCreatorInfos' => (object) data_get($this->resource, 'tiktokCreatorInfos', []),
        ];
    }
}
