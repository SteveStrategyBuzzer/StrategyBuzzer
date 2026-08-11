<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taxonomy Gaps — Zero-PASS Exhausted Subjects</title>
    <style>
        :root {
            --bg: #0f1115;
            --panel: #1a1d24;
            --border: #2a2f3a;
            --text: #e6e8ec;
            --muted: #9aa0aa;
            --accent: #4f8cff;
            --ok: #2ecc71;
            --warn: #f39c12;
            --ko: #e74c3c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 24px;
        }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: var(--muted); font-size: 13px; margin-bottom: 20px; }
        .nav { margin-bottom: 20px; }
        .nav a { color: var(--accent); text-decoration: none; font-size: 13px; }
        .nav a:hover { text-decoration: underline; }
        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .panel.alert-panel {
            border-color: var(--ko);
            background: rgba(231, 76, 60, 0.06);
        }
        .panel.ok-panel {
            border-color: var(--ok);
            background: rgba(46, 204, 113, 0.06);
        }
        .summary {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
        }
        .count-badge {
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
        }
        .count-badge.alert { color: var(--ko); }
        .count-badge.ok { color: var(--ok); }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge.warn { background: rgba(243, 156, 18, 0.18); color: var(--warn); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: var(--muted); }
        .empty { text-align: center; padding: 32px; color: var(--muted); }
        .error-box {
            background: rgba(231, 76, 60, 0.12);
            border: 1px solid var(--ko);
            border-radius: 6px;
            padding: 12px 16px;
            color: var(--ko);
            font-size: 13px;
            margin-bottom: 16px;
        }
        .table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .depth-chip {
            display: inline-block;
            background: #2a2f3a;
            border-radius: 4px;
            padding: 1px 6px;
            font-size: 12px;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        @media (max-width: 639px) {
            body { padding: 12px; }
            .panel { padding: 12px; }
            h1 { font-size: 18px; }
        }
    </style>
</head>
<body>

    <div class="nav">
        <a href="{{ url('/admin/questions/audit-log') }}@if(request()->query('token'))?token={{ request()->query('token') }}@endif">
            ← Audit Log
        </a>
    </div>

    <h1>Taxonomy Gaps</h1>
    <p class="subtitle">Subjects exhausted with <strong>zero PASS ideas</strong> — Gemini returned only FAILs for these entries. They will never yield questions without manual intervention.</p>

    @if($error)
        <div class="error-box">⚠ {{ $error }}</div>
    @endif

    @php $count = count($subjects); @endphp

    <div class="panel {{ $count > 0 ? 'alert-panel' : 'ok-panel' }}">
        <div class="summary">
            <span class="count-badge {{ $count > 0 ? 'alert' : 'ok' }}">{{ $count }}</span>
            <span>
                @if($count === 0)
                    <strong>No exhausted subjects.</strong> All taxonomy subjects have at least one PASS idea available.
                @elseif($count === 1)
                    <strong>1 exhausted subject</strong> has zero PASS ideas. <span class="badge warn">Needs attention</span>
                @else
                    <strong>{{ $count }} exhausted subjects</strong> have zero PASS ideas. <span class="badge warn">Needs attention</span>
                @endif
            </span>
        </div>
    </div>

    @if($count > 0)
    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Depth</th>
                        <th>Domain</th>
                        <th>Subdomain</th>
                        <th>Subject</th>
                        <th class="num">Fail Count</th>
                        <th>Exhausted At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjects as $row)
                        <tr>
                            <td><span class="depth-chip">D{{ $row->depth }}</span></td>
                            <td>{{ $row->domain_code ?? '—' }}</td>
                            <td>{{ $row->subdomain_name ?? '—' }}</td>
                            <td>
                                <strong>{{ $row->subject_name ?? '—' }}</strong>
                                @if(isset($row->subject_id))
                                    <div class="muted" style="font-size:11px;">#{{ $row->subject_id }}</div>
                                @endif
                            </td>
                            <td class="num" style="color: var(--ko); font-weight:600;">{{ (int) $row->fail_count }}</td>
                            <td class="muted">{{ $row->exhausted_at ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @elseif(!$error)
    <div class="panel">
        <div class="empty">✓ No gaps found — all subjects have at least one PASS idea.</div>
    </div>
    @endif

</body>
</html>
