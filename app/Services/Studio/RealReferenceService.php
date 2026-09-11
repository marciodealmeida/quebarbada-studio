<?php

namespace App\Services\Studio;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Sleep;

class RealReferenceService
{
    public function __construct(private HttpFactory $http) {}

    /** @param array<string, mixed> $scene
     * @return array{candidates: list<array<string, mixed>>, decision: string, reason: string}
     */
    public function discover(array $scene): array
    {
        $query = trim(($scene['factual_reference'] ?? '').' '.implode(' ', $scene['clip_search_terms']));
        Sleep::sleep(1);
        $pages = $this->http->withUserAgent('QueSinistroStudio/1.0 (visual-reference-discovery)')->acceptJson()->connectTimeout(5)->timeout(30)->get('https://commons.wikimedia.org/w/api.php', [
            'action' => 'query', 'generator' => 'search', 'gsrsearch' => $query, 'gsrnamespace' => 6, 'gsrlimit' => 8,
            'prop' => 'imageinfo|info', 'inprop' => 'url', 'iiprop' => 'url|extmetadata', 'format' => 'json',
        ])->throw()->json('query.pages', []);
        $candidates = collect($pages)->map(fn (array $page): array => $this->candidate($page, $scene))->values()->all();
        $approved = collect($candidates)->where('approved', true)->sortByDesc('visual_score')->first();

        if ($approved) {
            return ['candidates' => $candidates, 'decision' => 'real_reference', 'reason' => 'Referência real com licença comercial verificável e relevância suficiente.'];
        }

        $decision = $scene['asset_provider'] === 'openai-image' ? 'ai_reconstruction' : 'stock';

        return ['candidates' => $candidates, 'decision' => $decision, 'reason' => $decision === 'ai_reconstruction' ? 'A cena pede reconstrução ilustrativa; referências encontradas não são reutilizáveis com segurança.' : 'Nenhuma referência real atingiu licença e relevância verificáveis; usar stock coerente.'];
    }

    /** @param array<string, mixed> $page
     * @param  array<string, mixed>  $scene
     * @return array<string, mixed>
     */
    private function candidate(array $page, array $scene): array
    {
        $info = $page['imageinfo'][0] ?? [];
        $metadata = $info['extmetadata'] ?? [];
        $value = fn (string $key): string => trim(strip_tags((string) data_get($metadata, "{$key}.value", '')));
        $license = $value('LicenseShortName');
        $licenseUrl = $value('LicenseUrl');
        $title = (string) ($page['title'] ?? '');
        $publicDomain = (bool) preg_match('/^(CC0|PDM|PD|public domain)/i', $license);
        $commercialLicense = ($publicDomain || (bool) preg_match('/^CC BY(?:-SA)?/i', $license)) && ! str_contains(mb_strtoupper($license), 'NC');
        $licenseUrl = $licenseUrl ?: ($commercialLicense ? ($page['fullurl'] ?? '') : '');
        $restrictedSubject = (bool) preg_match('/max headroom|doctor who|wgn|wttw/i', $title);
        $haystack = mb_strtolower($title.' '.$value('ImageDescription'));
        $required = collect($scene['required_elements'] ?? [])->filter(fn (string $element): bool => str_contains($haystack, mb_strtolower($element)))->count();
        $requiredEvents = $scene['required_event_visuals'] ?? [];
        $eventMatches = collect($requiredEvents)->filter(fn (string $event): bool => str_contains($haystack, mb_strtolower($event)))->count();
        $eventCoverageScore = $requiredEvents === [] ? 100 : (int) round($eventMatches * 100 / count($requiredEvents));
        $importantScene = (int) ($scene['importance'] ?? 3) >= 4;
        $visualScore = min(100, 45 + $required * 15 + ($commercialLicense ? 20 : 0));
        $reason = match (true) {
            ! $commercialLicense || blank($licenseUrl) => 'Licença comercial verificável ausente ou incompleta.',
            $restrictedSubject => 'Arquivo relacionado a programa, emissora ou personagem protegido; não reutilizar automaticamente.',
            $importantScene && $eventCoverageScore < 80 => 'A referência não mostra diretamente o evento narrado.',
            $visualScore < 80 => 'Relevância visual insuficiente para representar esta cena factual.',
            default => 'Licença e relevância verificadas.',
        };

        return ['source' => 'Wikimedia Commons', 'source_url' => $page['fullurl'] ?? null, 'asset_url' => $info['url'] ?? null, 'title' => $title, 'author' => $value('Artist'), 'license' => $license, 'license_url' => $licenseUrl, 'public_domain' => $publicDomain, 'attribution_required' => ! $publicDomain, 'attribution_text' => $value('Credit').' — '.$value('Artist').' — '.$license, 'factual_relevance' => $required > 0 ? 'partial' : 'low', 'visual_score' => $visualScore, 'event_coverage_score' => $eventCoverageScore, 'approved' => $commercialLicense && ! $restrictedSubject && $visualScore >= 80 && (! $importantScene || $eventCoverageScore >= 80), 'rejection_reason' => $reason];
    }
}
