<?php

namespace App\Models;

use Database\Factories\VideoSceneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoScene extends Model
{
    /** @use HasFactory<VideoSceneFactory> */
    use HasFactory;

    protected $fillable = ['video_id', 'position', 'duration_seconds', 'narration', 'visual_prompt', 'clip_search_terms', 'asset_metadata'];

    protected function casts(): array
    {
        return ['clip_search_terms' => 'array', 'asset_metadata' => 'array'];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
