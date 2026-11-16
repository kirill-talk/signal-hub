<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signal-to-Noise Hub Digest</title>
</head>
<body style="margin:0;padding:0;background-color:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background-color:#1e293b;border:1px solid #334155;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px;background:linear-gradient(135deg,#4f46e5,#0284c7);">
                            <p style="margin:0 0 6px 0;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c7d2fe;">
                                Signal-to-Noise Hub
                            </p>
                            <h1 style="margin:0;font-size:22px;font-weight:700;color:#ffffff;line-height:1.3;">
                                Your Curated Digest ({{ $totalCount }} {{ \Illuminate\Support\Str::plural('item', $totalCount) }})
                            </h1>
                            <p style="margin:8px 0 0 0;font-size:13px;color:#e0e7ff;">
                                Hello {{ $user->name }}, here are your unread high-signal updates.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 32px;">
                            @foreach ($groupedEntries as $topicName => $entries)
                                <div style="margin-bottom:28px;">
                                    <h2 style="margin:0 0 14px 0;padding-bottom:8px;border-bottom:1px solid #334155;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#818cf8;">
                                        {{ $topicName }} ({{ $entries->count() }})
                                    </h2>

                                    @foreach ($entries as $entry)
                                        <div style="margin-bottom:16px;padding:14px 16px;background-color:#0f172a;border:1px solid #334155;border-radius:12px;">
                                            @if ($entry->is_highlighted)
                                                <span style="display:inline-block;margin-bottom:6px;padding:2px 8px;border-radius:9999px;background-color:#451a03;border:1px solid #d97706;color:#fcd34d;font-size:10px;font-weight:700;text-transform:uppercase;">
                                                    Priority
                                                </span>
                                            @endif
                                            <p style="margin:0 0 6px 0;font-size:15px;font-weight:600;line-height:1.4;">
                                                <a href="{{ $entry->url }}" style="color:#ffffff;text-decoration:none;">
                                                    {{ $entry->title }}
                                                </a>
                                            </p>
                                            <p style="margin:0 0 8px 0;font-size:12px;color:#94a3b8;">
                                                {{ $entry->source?->name ?? 'Feed Source' }}
                                                &bull;
                                                {{ ($entry->published_at ?? $entry->created_at)->format('M j, Y H:i') }}
                                            </p>
                                            @if ($entry->summary)
                                                <p style="margin:0;font-size:13px;line-height:1.55;color:#cbd5e1;">
                                                    {{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($entry->summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 160) }}
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach

                            <div style="text-align:center;padding-top:8px;">
                                <a href="{{ route('feed.index') }}" style="display:inline-block;padding:12px 24px;border-radius:10px;background-color:#4f46e5;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;">
                                    Open Feed Hub
                                </a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px;background-color:#0f172a;border-top:1px solid #334155;text-align:center;font-size:12px;color:#64748b;">
                            Manage digest schedule &amp; timezone in <a href="{{ route('settings.index') }}" style="color:#818cf8;text-decoration:none;">Settings</a>.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
