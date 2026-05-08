<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'brand' => null, // Assuming no brand field
            'categories' => $this->category->pluck('name')->map(fn($name) => strtolower($name))->toArray(),
            'collectionCategory' => null, // Assuming not used
            'createdAt' => $this->published_at?->toISOString(),
            'creatorAvatar' => $this->creator->avatar_url ?? null,
            'creatorId' => $this->creator_id,
            'creatorPseudo' => $this->creator->pseudo,
            'disciplines' => $this->disciplines->pluck('name')->toArray(),
            'duration' => $this->duration ?? '00:00', // Assuming duration field exists
            'formats' => $this->formats->pluck('name')->toArray(),
            'position' => 1, // Assuming default position
            'routeId' => $this->slug ?? $this->id, // Assuming slug field or use id
            'sourceId' => $this->platform_video_id,
            'sourceType' => $this->platform,
            'tags' => $this->tags->pluck('name')->toArray(),
            'thumbnailUrl' => $this->thumbnail_url,
            'twitchClipSlug' => $this->platform === 'twitch' ? $this->platform_video_id : null,
            'twitchVideoId' => $this->platform === 'twitch' ? $this->platform_video_id : null,
            'views' => $this->formatViews($this->views ?? 0), // Assuming views field
            'viewsCache' => (string) ($this->views ?? 0),
            'youtubeId' => $this->platform === 'youtube' ? $this->platform_video_id : null,
        ];
    }

    private function formatViews(int $views): string
    {
        if ($views >= 1000000) {
            return number_format($views / 1000000, 1) . 'M';
        } elseif ($views >= 1000) {
            return number_format($views / 1000, 1) . 'k';
        }
        return (string) $views;
    }
}
