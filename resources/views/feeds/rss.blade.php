{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $feedTitle }}</title>
        <link>{{ $feedLink }}</link>
        <description>{{ $feedDescription }}</description>
        <language>en-us</language>
        <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
        <atom:link href="{{ url()->current() }}" rel="self" type="application/rss+xml" />

        @foreach ($entries as $entry)
            <item>
                <title>{{ $entry->is_highlighted ? '[Priority] '.$entry->title : $entry->title }}</title>
                <link>{{ $entry->url }}</link>
                <guid isPermaLink="false">{{ $entry->external_id_hash }}</guid>
                <pubDate>{{ ($entry->published_at ?? $entry->created_at)->toRssString() }}</pubDate>
                @if ($entry->topic)
                    <category>{{ $entry->topic->name }}</category>
                @endif
                @if ($entry->summary)
                    <description><![CDATA[{!! str_replace(']]>', ']]]]><![CDATA[>', $entry->summary) !!}]]></description>
                @endif
            </item>
        @endforeach
    </channel>
</rss>
