<?php

namespace App\Models;

use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected $fillable = ['channel_id', 'status', 'content_type', 'topic', 'script', 'narration', 'resolution', 'estimated_duration_seconds', 'estimated_cost_usd', 'metadata', 'output_path', 'actual_duration_seconds', 'actual_cost_usd'];

    protected function casts(): array
    {
        return ['estimated_cost_usd' => 'decimal:2', 'actual_cost_usd' => 'decimal:2', 'metadata' => 'array'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(VideoScene::class)->orderBy('position');
    }
}
