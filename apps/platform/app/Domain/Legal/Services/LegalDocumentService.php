<?php

namespace App\Domain\Legal\Services;

use App\Domain\Legal\Models\LegalAcceptance;
use App\Domain\Legal\Models\LegalDocument;
use App\Domain\Legal\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LegalDocumentService
{
    /** @return EloquentCollection<int, LegalDocument> */
    public function publishedDocuments(): EloquentCollection
    {
        $this->syncConfiguredDocuments();

        return LegalDocument::query()
            ->with('publishedVersion')
            ->where('is_public', true)
            ->whereHas('publishedVersion')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function publishedDocument(string $slug): ?LegalDocument
    {
        $this->syncConfiguredDocuments();

        return LegalDocument::query()
            ->with('publishedVersion')
            ->where('slug', $slug)
            ->where('is_public', true)
            ->whereHas('publishedVersion')
            ->first();
    }

    /** @return EloquentCollection<int, LegalDocumentVersion> */
    public function requiredVersionsFor(User $user): EloquentCollection
    {
        $this->syncConfiguredDocuments();

        $acceptedVersionIds = LegalAcceptance::query()
            ->where('user_id', $user->id)
            ->pluck('legal_document_version_id')
            ->all();

        return LegalDocumentVersion::query()
            ->with('document')
            ->where('is_published', true)
            ->where('is_material', true)
            ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->whereIn('id', function ($query): void {
                $query->selectRaw('max(id)')
                    ->from('legal_document_versions')
                    ->where('is_published', true)
                    ->where('is_material', true)
                    ->groupBy('legal_document_id');
            })
            ->when($acceptedVersionIds !== [], fn ($query) => $query->whereNotIn('id', $acceptedVersionIds))
            ->orderBy('effective_at')
            ->get();
    }

    public function userRequiresAcceptance(User $user): bool
    {
        return $this->requiredVersionsFor($user)->isNotEmpty();
    }

    /** @param array<int, int> $versionIds */
    public function acceptVersions(User $user, array $versionIds, ?Request $request = null): void
    {
        $required = $this->requiredVersionsFor($user);
        $requiredIds = $required->pluck('id')->sort()->values()->all();
        $providedIds = collect($versionIds)->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();

        if ($requiredIds !== $providedIds) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'acceptance' => 'You must accept every required legal document version before continuing.',
            ]);
        }

        DB::transaction(function () use ($user, $required, $request): void {
            foreach ($required as $version) {
                LegalAcceptance::query()->updateOrCreate(
                    ['user_id' => $user->id, 'legal_document_version_id' => $version->id],
                    [
                        'accepted_at' => now(),
                        'ip_address' => $this->captureRequestMetadata() ? $request?->ip() : null,
                        'user_agent' => $this->captureRequestMetadata() ? substr((string) $request?->userAgent(), 0, 1000) : null,
                    ]
                );
            }
        });
    }

    public function syncConfiguredDocuments(): void
    {
        foreach (array_values(config('legal.documents', [])) as $index => $documentConfig) {
            // no-op, this loop only exists to normalize numeric configs safely
        }

        foreach (config('legal.documents', []) as $slug => $documentConfig) {
            $document = LegalDocument::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $documentConfig['title'],
                    'description' => $documentConfig['description'] ?? null,
                    'is_public' => true,
                    'sort_order' => array_search($slug, array_keys(config('legal.documents', [])), true) + 1,
                ]
            );

            LegalDocumentVersion::query()->updateOrCreate(
                ['legal_document_id' => $document->id, 'version' => $documentConfig['version']],
                [
                    'summary' => $documentConfig['summary'] ?? null,
                    'body' => $documentConfig['body'],
                    'published_at' => $this->parseDate($documentConfig['published_at'] ?? null),
                    'effective_at' => $this->parseDate($documentConfig['effective_at'] ?? null),
                    'is_published' => true,
                    'is_material' => (bool) ($documentConfig['material'] ?? true),
                    'metadata' => ['source' => 'config/legal.php'],
                ]
            );
        }
    }

    private function parseDate(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    private function captureRequestMetadata(): bool
    {
        return (bool) config('legal.capture_request_metadata', true);
    }
}

