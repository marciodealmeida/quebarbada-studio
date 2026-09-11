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

    protected $fillable = ['video_id', 'position', 'duration_seconds', 'narration', 'scene_type', 'factual_reference', 'narrated_event', 'visual_intent', 'visual_prompt', 'clip_search_terms', 'fallback_search_terms', 'required_elements', 'required_event_visuals', 'forbidden_elements', 'asset_provider', 'real_reference_candidate', 'real_reference_candidates', 'real_reference_decision', 'real_reference_reason', 'sound_effect', 'motion_design', 'importance', 'event_coverage_score', 'asset_metadata'];

    protected function casts(): array
    {
        return ['clip_search_terms' => 'array', 'fallback_search_terms' => 'array', 'required_elements' => 'array', 'required_event_visuals' => 'array', 'forbidden_elements' => 'array', 'real_reference_candidate' => 'boolean', 'real_reference_candidates' => 'array', 'asset_metadata' => 'array'];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
