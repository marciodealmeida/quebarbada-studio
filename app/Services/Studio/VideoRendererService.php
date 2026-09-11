<?php

namespace App\Services\Studio;

use App\Models\Video;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VideoRendererService
{
    public function duration(string $path): float
    {
        $result = Process::timeout(30)->run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $path]);
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        return (float) trim($result->output());
    }

    /** @return array{path: string, duration: float, size: int} */
    public function render(Video $video, string $audioPath, string $directory): array
    {
        $scenes = $video->scenes()->get();
        $audioDuration = $this->duration($audioPath);
        $tail = (float) config('studio.video.tail_seconds');
        $totalDuration = $audioDuration + $tail;
        $plannedDuration = max(1, $scenes->sum('duration_seconds'));
        $sceneDurations = $scenes->map(fn ($scene): float => $audioDuration * $scene->duration_seconds / $plannedDuration);
        $ass = $this->subtitles($video->narration_final_text, $audioDuration);
        $assPath = "{$directory}/subtitles.ass";
        Storage::disk('local')->put($assPath, $ass);
        $musicPath = Storage::disk('local')->path("{$directory}/music.wav");
        Process::timeout(60)->run(['ffmpeg', '-y', '-f', 'lavfi', '-i', "aevalsrc=0.012*sin(2*PI*55*t)+0.007*sin(2*PI*82.41*t)+0.003*sin(2*PI*110*t):s=48000:d={$totalDuration}", '-ac', '2', $musicPath])->throw();
        $soundPath = Storage::disk('local')->path("{$directory}/sound-design.wav");
        Process::timeout(60)->run(['ffmpeg', '-y', '-f', 'lavfi', '-i', "aevalsrc=0.018*sin(2*PI*36*t)*between(mod(t\\,15)\\,0\\,0.24)+0.004*random(0):s=48000:d={$totalDuration}", '-ac', '2', $soundPath])->throw();
        $command = ['ffmpeg', '-y'];
        $filter = [];
        foreach ($scenes as $index => $scene) {
            $path = Storage::disk('local')->path($scene->asset_metadata['path']);
            $command = $this->isStillImage($path) ? [...$command, '-loop', '1', '-i', $path] : [...$command, '-stream_loop', '-1', '-i', $path];
            $sceneDuration = $sceneDurations[$index] + ($index === $scenes->count() - 1 ? $tail : 0);
            $filter[] = "[{$index}:v]{$this->visualFilter($path, $scene->motion_design, $sceneDuration)}[v{$index}]";
        }
        $command = [...$command, '-i', $audioPath, '-i', $musicPath, '-i', $soundPath];
        $filter[] = implode('', array_map(fn (int $index): string => "[v{$index}]", array_keys($scenes->all())))."concat=n={$scenes->count()}:v=1:a=0[v]";
        $filter[] = "[v]ass='".str_replace(':', '\\:', Storage::disk('local')->path($assPath))."',fade=t=out:st={$audioDuration}:d={$tail}[sub]";
        $musicInput = $scenes->count() + 1;
        $soundInput = $scenes->count() + 2;
        $filter[] = "[{$musicInput}:a]volume='if(between(t,{$audioDuration}-7,{$audioDuration}),0.025,0.075)',afade=t=in:st=0:d=1.2,afade=t=out:st={$audioDuration}:d={$tail}[music]";
        $filter[] = "[{$soundInput}:a]volume=0.22,afade=t=out:st={$audioDuration}:d={$tail}[sfx]";
        $filter[] = "[{$scenes->count()}:a]apad=pad_dur={$tail},afade=t=out:st={$audioDuration}:d={$tail}[voice]";
        $filter[] = '[voice][music][sfx]amix=inputs=3:weights=1 1 1:duration=longest[a]';
        $output = Storage::disk('local')->path("{$directory}/final.mp4");
        $temporaryOutput = Storage::disk('local')->path("{$directory}/final.tmp.mp4");
        if (is_file($output)) {
            throw new RuntimeException('O arquivo final já existe; a renderização não pode sobrescrevê-lo.');
        }
        $result = Process::timeout(600)->run([...$command, '-filter_complex', implode(';', $filter), '-map', '[sub]', '-map', '[a]', '-t', (string) $totalDuration, '-c:v', 'libx264', '-preset', 'medium', '-crf', '19', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', $temporaryOutput]);
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        $this->validateOutput($temporaryOutput);
        if (! rename($temporaryOutput, $output)) {
            throw new RuntimeException('Não foi possível promover o MP4 validado para o caminho final.');
        }

        return ['path' => "{$directory}/final.mp4", 'duration' => $this->duration($output), 'size' => filesize($output) ?: 0];
    }

    private function visualFilter(string $path, string $motionDesign, float $duration): string
    {
        if ($this->isStillImage($path)) {
            $motion = match ($motionDesign) {
                'slow_pan' => "zoompan=z='min(zoom+0.00035,1.10)':x='iw-iw/zoom':y='(ih-ih/zoom)/2':d=".(int) ceil($duration * 30).':s=1080x1920:fps=30',
                'signal_glitch' => "zoompan=z='min(zoom+0.00045,1.12)':d=".(int) ceil($duration * 30).":s=1080x1920:fps=30,eq=brightness='if(between(t,1.1,1.18),0.08,0)'",
                default => "zoompan=z='min(zoom+0.0004,1.12)':d=".(int) ceil($duration * 30).':s=1080x1920:fps=30',
            };

            return "scale=1280:2276:force_original_aspect_ratio=increase,crop=1280:2276,{$motion},trim=duration={$duration},setpts=PTS-STARTPTS,vignette,setsar=1";
        }

        return "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=30,trim=duration={$duration},setpts=PTS-STARTPTS,vignette,setsar=1";
    }

    private function isStillImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
    }

    public function subtitles(string $narrationFinalText, float $audioDuration): string
    {
        $header = "[Script Info]\nScriptType: v4.00+\nPlayResX: 1080\nPlayResY: 1920\n\n[V4+ Styles]\nFormat: Name,Fontname,Fontsize,PrimaryColour,OutlineColour,BackColour,Bold,Italic,Alignment,MarginL,MarginR,MarginV,Outline,Shadow\nStyle: Main,Arial,78,&H00FFFFFF,&H00101010,&HAA000000,1,0,5,80,80,0,5,2\n\n[Events]\nFormat: Layer,Start,End,Style,Name,MarginL,MarginR,MarginV,Effect,Text\n";
        $blocks = preg_split('/(?<=[.!?])\s+/u', trim($narrationFinalText)) ?: [];
        $wordTotal = max(1, count(preg_split('/\s+/u', trim($narrationFinalText)) ?: []));
        $elapsedWords = 0;
        foreach ($blocks as $block) {
            $blockWords = count(preg_split('/\s+/u', trim($block)) ?: []);
            $start = $this->time($audioDuration * $elapsedWords / $wordTotal);
            $elapsedWords += $blockWords;
            $end = $this->time($audioDuration * $elapsedWords / $wordTotal);
            $text = str_replace(["\n", "\r", ','], [' ', '', '\\,'], trim($block));
            $header .= "Dialogue: 0,{$start},{$end},Main,,0,0,0,,{$text}\n";
        }

        return $header;
    }

    private function time(float $seconds): string
    {
        return sprintf('0:%02d:%05.2f', floor($seconds / 60), fmod($seconds, 60));
    }

    private function validateOutput(string $path): void
    {
        $probe = Process::timeout(30)->run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-show_entries', 'stream=codec_name,codec_type,width,height', '-of', 'json', $path]);
        if ($probe->failed()) {
            throw new RuntimeException($probe->errorOutput());
        }
        $metadata = json_decode($probe->output(), true);
        $video = collect($metadata['streams'] ?? [])->firstWhere('codec_type', 'video');
        $audio = collect($metadata['streams'] ?? [])->firstWhere('codec_type', 'audio');
        $duration = (float) data_get($metadata, 'format.duration', 0);
        if ($duration < config('studio.video.min_seconds') || $duration > config('studio.video.max_seconds') || data_get($video, 'codec_name') !== 'h264' || data_get($audio, 'codec_name') !== 'aac' || data_get($video, 'width') !== 1080 || data_get($video, 'height') !== 1920) {
            throw new RuntimeException('O MP4 temporário não atende aos requisitos técnicos.');
        }
        Process::timeout(180)->run(['ffmpeg', '-v', 'error', '-i', $path, '-f', 'null', '-'])->throw();
    }

    /** @return array{resolution: string, enabled: bool} */
    public function configuration(): array
    {
        return ['resolution' => config('studio.video.resolution'), 'enabled' => true];
    }
}
