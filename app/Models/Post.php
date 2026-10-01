<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Media\SyncOwnedMedia;
use App\Dto\MediaItem;
use App\Enums\Media\Type;
use App\Enums\Post\CreatedVia;
use App\Enums\Post\ScheduleMode;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Observers\PostObserver;
use App\Support\Media\MediaCopyBatch;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[ObservedBy([PostObserver::class])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'content',
        'media',
        'status',
        'schedule_mode',
        'created_via',
        'repurpose_item_id',
        'scheduled_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'schedule_mode' => ScheduleMode::class,
            'created_via' => CreatedVia::class,
            'media' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get media items as a collection of MediaItem DTOs.
     *
     * @return Collection<int, MediaItem>
     */
    protected function mediaItems(): Attribute
    {
        return Attribute::make(
            get: fn () => collect($this->media ?? [])->map(fn (array $item) => MediaItem::fromArray($item)),
        );
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ownedMedia(): HasMany
    {
        return $this->hasMany(Media::class, 'post_id')->orderBy('order');
    }

    public function postPlatforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class)->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PostNote::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceLabel::class);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Scheduled);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->scheduled()->where('scheduled_at', '<=', now());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Draft);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', [PostStatus::Published, PostStatus::PartiallyPublished]);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Failed);
    }

    /**
     * Posts carrying any of the labels, plus posts without labels when $untagged is set; no filter when both are empty.
     *
     * @param  list<string>  $labelIds
     */
    public function scopeMatchingLabelFilter(Builder $query, array $labelIds, bool $untagged = false): Builder
    {
        return $query->when($labelIds !== [] || $untagged, fn (Builder $filtered): Builder => $filtered->where(fn (Builder $inner): Builder => $inner
            ->when($labelIds !== [], fn (Builder $any): Builder => $any->whereHas('labels', fn (Builder $labels): Builder => $labels->whereIn('workspace_labels.id', $labelIds)))
            ->when($untagged, fn (Builder $none): Builder => $none->orWhereDoesntHave('labels'))));
    }

    public function markAsPublishing(): void
    {
        $this->update(['status' => PostStatus::Publishing]);
    }

    public function markAsPublished(): void
    {
        $this->update([
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function markAsPartiallyPublished(): void
    {
        $this->update([
            'status' => PostStatus::PartiallyPublished,
            'published_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => PostStatus::Failed]);
    }

    /**
     * MediaTypes accepted by this post — the intersection of what every
     * enabled platform allows. With no platform enabled, accept anything.
     *
     * @return array<Type>
     */
    public function allowedMediaTypes(): array
    {
        $platforms = $this->postPlatforms()
            ->enabled()
            ->with('socialAccount')
            ->get()
            ->pluck('socialAccount.platform')
            ->filter();

        return self::allowedMediaTypesFor($platforms);
    }

    /**
     * Media types acceptable across a set of platforms (intersection; empty = all).
     *
     * @param  Collection<int, Platform>  $platforms
     * @return array<Type>
     */
    public static function allowedMediaTypesFor(Collection $platforms): array
    {
        if ($platforms->isEmpty()) {
            return Type::cases();
        }

        $sets = $platforms
            ->map(fn (Platform $platform) => array_map(fn ($type) => $type->value, $platform->allowedMediaTypes()))
            ->all();

        return array_map(
            Type::from(...),
            array_values(array_intersect(...$sets)),
        );
    }

    /**
     * Append items (by `id` or `upload_token`) after the post's current media,
     * under a row lock so concurrent writers don't overwrite each other's
     * appends. Every item ends as a row this post owns.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function appendMedia(array $items, ?MediaCopyBatch $batch = null): void
    {
        $append = function (MediaCopyBatch $batch) use ($items): void {
            $fresh = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            SyncOwnedMedia::execute($fresh, [...($fresh->media ?? []), ...array_values($items)], $batch);

            $this->setRawAttributes($fresh->getAttributes(), true);
        };

        if ($batch !== null) {
            DB::transaction(fn () => $append($batch));

            return;
        }

        MediaCopyBatch::run($append);
    }
}
