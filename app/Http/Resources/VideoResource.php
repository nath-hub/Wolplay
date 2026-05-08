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
            'brand' => "", // Assuming no brand field
            'categories' => $this->category->pluck('name')->map(fn($name) => strtolower($name))->toArray(),
            'collectionCategory' => $this->category->first()?->name ?? '',
            'createdAt' => $this->published_at?->toISOString(),
            'creatorAvatar' => $this->creator->avatar_url ?? "",
            'creatorId' => $this->creator_id,
            'creatorPseudo' => $this->creator->pseudo,
            'disciplines' => $this->disciplines->pluck('name')->toArray(),
            'duration' => $this->duration ?? '00:00', // Assuming duration field exists
            'formats' => $this->formats->pluck('name')->toArray(),
            'position' => 1, // Assuming default position
            'routeId' => $this->slug ?? $this->id, // Assuming slug field or use id
            'sourceId' => $this->platform_video_id,
            'sourceType' => $this->platform,
            'tags' => $this->tags->pluck('label')->toArray(),
            'thumbnailUrl' => $this->embed_url,
            'twitchClipSlug' => $this->platform === 'twitch' ? $this->platform_video_id : "",
            'twitchVideoId' => $this->platform === 'twitch' ? $this->platform_video_id : "",
            'views' => $this->formatViews($this->views ?? 0), // Assuming views field
            'viewsCache' => (string) ($this->views ?? 0),
            'youtubeId' => $this->platform === 'youtube' ? $this->platform_video_id : "",
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
