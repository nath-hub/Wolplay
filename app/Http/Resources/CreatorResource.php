<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestVideo = $this->publishedVideos()->latest('published_at')->first();

        return [
            'id' => $this->id,
            'pseudo' => $this->pseudo,
            'level' => $this->level ?? 1,
            'banner' => $this->banner_url ?? '',
            'avatar' => $this->avatar_url ?? '',
            'disciplines' => $this->disciplines->pluck('name')->toArray(),
            'videoCount' => $this->video_count ?? 0,
            'lastPublishedAt' => $this->publishedVideos()->latest('published_at')->first()?->published_at?->toISOString(),
            'latestVideo' => $latestVideo ? [
                'id' => $latestVideo->id,
                'youtubeId' => $latestVideo->platform === 'youtube' ? $latestVideo->platform_video_id : null,
                'title' => $latestVideo->title,
                'sourceType' => $latestVideo->platform,
                'createdAt' => $latestVideo->published_at?->toISOString(),
                'position' => 1, // Assuming default
            ] : null,
            'isPremium' => $this->plan === 'premium',
        ];
    }
}