<?php

namespace App\Services\Studio;

use App\Models\Video;
use App\Models\VideoScene;
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
        $perScene = $audioDuration / $scenes->count();
        $ass = $this->ass($scenes->all(), $perScene);
        $assPath = "{$directory}/subtitles.ass";
        Storage::disk('local')->put($assPath, $ass);
        $musicPath = Storage::disk('local')->path("{$directory}/music.wav");
        Process::timeout(60)->run(['ffmpeg', '-y', '-f', 'lavfi', '-i', "aevalsrc=0.018*sin(2*PI*55*t)+0.008*sin(2*PI*82.41*t):s=48000:d={$audioDuration}", '-ac', '2', $musicPath])->throw();
        $command = ['ffmpeg', '-y'];
        $filter = [];
        foreach ($scenes as $index => $scene) {
            $path = Storage::disk('local')->path($scene->asset_metadata['path']);
            $command = str_ends_with($path, '.png') ? [...$command, '-loop', '1', '-i', $path] : [...$command, '-stream_loop', '-1', '-i', $path];
            $filter[] = "[{$index}:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=30,trim=duration={$perScene},setpts=PTS-STARTPTS[v{$index}]";
        }
        $command = [...$command, '-i', $audioPath, '-i', $musicPath];
        $filter[] = implode('', array_map(fn (int $index): string => "[v{$index}]", array_keys($scenes->all())))."concat=n={$scenes->count()}:v=1:a=0[v]";
        $filter[] = "[v]ass='".str_replace(':', '\\:', Storage::disk('local')->path($assPath))."'[sub]";
        $filter[] = '['.($scenes->count() + 1).":a]volume='if(between(t,45,53),0.03,0.10)'[music]";
        $filter[] = "[{$scenes->count()}:a][music]amix=inputs=2:weights='1 1':duration=first[a]";
        $output = Storage::disk('local')->path("{$directory}/final.mp4");
        $temporaryOutput = Storage::disk('local')->path("{$directory}/final.tmp.mp4");
        $result = Process::timeout(600)->run([...$command, '-filter_complex', implode(';', $filter), '-map', '[sub]', '-map', '[a]', '-t', (string) $audioDuration, '-c:v', 'libx264', '-preset', 'medium', '-crf', '19', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', $temporaryOutput]);
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        $this->validateOutput($temporaryOutput);
        Process::timeout(30)->run(['mv', '-f', $temporaryOutput, $output])->throw();

        return ['path' => "{$directory}/final.mp4", 'duration' => $this->duration($output), 'size' => filesize($output) ?: 0];
    }

    /** @param list<VideoScene> $scenes */
    private function ass(array $scenes, float $duration): string
    {
        $header = "[Script Info]\nScriptType: v4.00+\nPlayResX: 1080\nPlayResY: 1920\n\n[V4+ Styles]\nFormat: Name,Fontname,Fontsize,PrimaryColour,OutlineColour,BackColour,Bold,Italic,Alignment,MarginL,MarginR,MarginV,Outline,Shadow\nStyle: Main,Arial,78,&H00FFFFFF,&H00101010,&HAA000000,1,0,5,80,80,0,5,2\n\n[Events]\nFormat: Layer,Start,End,Style,Name,MarginL,MarginR,MarginV,Effect,Text\n";
        foreach ($scenes as $index => $scene) {
            $start = $this->time($index * $duration);
            $end = $this->time(($index + 1) * $duration);
            $words = array_slice(explode(' ', str_replace(["\n", ','], [' ', '\\,'], $scene->narration)), 0, 7);
            $highlight = implode(' ', array_splice($words, 0, min(2, count($words))));
            $text = '{\\c&H00B8FFFF&}'.$highlight.'{\\c&H00FFFFFF&} '.implode(' ', $words);
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
        if ($duration < 65 || $duration > 80 || data_get($video, 'codec_name') !== 'h264' || data_get($audio, 'codec_name') !== 'aac' || data_get($video, 'width') !== 1080 || data_get($video, 'height') !== 1920) {
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
