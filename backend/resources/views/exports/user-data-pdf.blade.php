<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="utf-8">
    <title>潘朵拉月曆 — 個人資料匯出</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #333; }
        h1 { font-size: 18pt; margin-bottom: 4px; }
        h2 { font-size: 13pt; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-top: 18px; }
        .cover { text-align: center; margin: 80px 0 40px; }
        .cover h1 { font-size: 24pt; margin-bottom: 8px; }
        .meta { color: #666; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9.5pt; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; }
        .empty { color: #999; font-style: italic; }
        .pagebreak { page-break-after: always; }
        .footer { color: #aaa; font-size: 9pt; margin-top: 24px; }
    </style>
</head>
<body>

<div class="cover">
    <h1>潘朵拉月曆</h1>
    <p class="meta">個人週期記錄匯出</p>
    <p class="meta">使用者：{{ $user->display_name ?? $user->name ?? $user->id }}</p>
    <p class="meta">
        @if ($from && $to)
            期間：{{ $from->toDateString() }} ~ {{ $to->toDateString() }}
        @else
            全部記錄
        @endif
    </p>
    <p class="meta">產生時間：{{ $generated_at->toDateTimeString() }}</p>
</div>
<div class="pagebreak"></div>

<h2>週期記錄（{{ $cycles->count() }} 筆）</h2>
@if ($cycles->isEmpty())
    <p class="empty">無記錄</p>
@else
    <table>
        <thead><tr>
            <th>起始日</th><th>結束日</th><th>天數</th><th>量</th><th>備註</th>
        </tr></thead>
        <tbody>
        @foreach ($cycles as $c)
            <tr>
                <td>{{ $c->start_date?->toDateString() }}</td>
                <td>{{ $c->end_date?->toDateString() ?? '—' }}</td>
                <td>{{ $c->lengthInDays() ?? '—' }}</td>
                <td>{{ $c->peak_flow ?? '—' }}</td>
                <td>{{ $c->notes ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>症狀記錄（{{ $symptoms->count() }} 筆）</h2>
@if ($symptoms->isEmpty())
    <p class="empty">無記錄</p>
@else
    <table>
        <thead><tr>
            <th>日期</th><th>標籤</th><th>情緒</th><th>體溫</th><th>備註</th>
        </tr></thead>
        <tbody>
        @foreach ($symptoms as $s)
            <tr>
                <td>{{ $s->logged_on?->toDateString() }}</td>
                <td>{{ implode(', ', $s->tags ?? []) }}</td>
                <td>{{ $s->mood ?? '—' }}</td>
                <td>{{ $s->basal_temperature ?? '—' }}</td>
                <td>{{ $s->note ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>朵朵 check-in（{{ $dodo_checkins->count() }} 筆）</h2>
@if ($dodo_checkins->isEmpty())
    <p class="empty">無記錄</p>
@else
    <table>
        <thead><tr>
            <th>日期</th><th>情緒</th><th>相位</th><th>cycle day</th>
        </tr></thead>
        <tbody>
        @foreach ($dodo_checkins as $d)
            <tr>
                <td>{{ $d->checked_on?->toDateString() }}</td>
                <td>{{ $d->mood ?? '—' }}</td>
                <td>{{ $d->phase_at_checkin ?? '—' }}</td>
                <td>{{ $d->cycle_day_at_checkin ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>基礎體溫 BBT（{{ $bbt->count() }} 筆）</h2>
@if ($bbt->isEmpty())
    <p class="empty">無記錄</p>
@else
    <table>
        <thead><tr>
            <th>日期</th><th>溫度（°C）</th><th>備註</th>
        </tr></thead>
        <tbody>
        @foreach ($bbt as $b)
            <tr>
                <td>{{ $b->measured_on?->toDateString() }}</td>
                <td>{{ $b->temperature_c }}</td>
                <td>{{ $b->note ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<p class="footer">
    本份資料僅供您本人或交給醫療專業人員參考。Pandora Calendar 不提供醫療診斷或治療建議。
</p>

</body>
</html>
