<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PrivateFeedController extends Controller
{
    public function show(string $token, ?string $topicSlug = null): Response
    {
        $user = User::query()
            ->where('feed_token', $token)
            ->firstOrFail();

        $topic = null;

        if ($topicSlug !== null) {
            $topic = $user->topics()
                ->where('slug', $topicSlug)
                ->firstOrFail();
        }

        $cacheKey = 'private-rss:' . $user->id . ':' . ($topic?->id ?? 'all');

        $xml = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $topic): string {
            $entries = $user->entries()
                ->where('is_noise', false)
                ->when($topic !== null, fn($query) => $query->where('topic_id', $topic->id))
                ->with(['source', 'topic'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $feedTitle = $topic !== null
                ? "Signal-to-Noise Hub — {$topic->name}"
                : 'Signal-to-Noise Hub — Signal Stream';

            $feedLink = route('feed.index');
            $feedDescription = $topic !== null
                ? "Curated high-signal entries for {$topic->name}."
                : 'Curated high-signal entries across all sources.';

            return view('feeds.rss', [
                'feedTitle' => $feedTitle,
                'feedLink' => $feedLink,
                'feedDescription' => $feedDescription,
                'entries' => $entries,
            ])->render();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}