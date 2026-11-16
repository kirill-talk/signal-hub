<?php

namespace App\Services;

use App\Models\FilterRule;

class NoiseFilterService
{
    /**
     * Evaluate an entry against the user's active filter rules.
     *
     * @return array{is_noise: bool, is_highlighted: bool}
     */
    public function evaluate(
        int $userId,
        ?int $sourceId,
        ?int $topicId,
        string $title,
        ?string $summary = null,
    ): array {
        $rules = FilterRule::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($query) use ($sourceId): void {
                $query->whereNull('source_id');
                if ($sourceId !== null) {
                    $query->orWhere('source_id', $sourceId);
                }
            })
            ->where(function ($query) use ($topicId): void {
                $query->whereNull('topic_id');
                if ($topicId !== null) {
                    $query->orWhere('topic_id', $topicId);
                }
            })
            ->get();

        $includeRules = $rules->where('type', 'include');
        $excludeRules = $rules->where('type', 'exclude');
        $highlightRules = $rules->where('type', 'highlight');

        $isNoise = false;

        if ($includeRules->isNotEmpty()) {
            $matchedInclude = $includeRules->contains(
                fn(FilterRule $rule): bool => $this->matchesRule($rule, $title, $summary)
            );

            if (!$matchedInclude) {
                $isNoise = true;
            }
        }

        if (
            !$isNoise && $excludeRules->contains(
                fn(FilterRule $rule): bool => $this->matchesRule($rule, $title, $summary)
            )
        ) {
            $isNoise = true;
        }

        $isHighlighted = false;

        if (
            !$isNoise && $highlightRules->contains(
                fn(FilterRule $rule): bool => $this->matchesRule($rule, $title, $summary)
            )
        ) {
            $isHighlighted = true;
        }

        return [
            'is_noise' => $isNoise,
            'is_highlighted' => $isHighlighted,
        ];
    }

    public function matchesRule(FilterRule $rule, string $title, ?string $summary = null): bool
    {
        $cleanSummary = strip_tags((string) $summary);

        $haystack = match ($rule->match_field) {
            'title' => $title,
            'summary' => $cleanSummary,
            default => $title . ' ' . $cleanSummary,
        };

        foreach ($rule->keywords ?? [] as $keyword) {
            $needle = trim((string) $keyword);

            if ($needle !== '' && mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}