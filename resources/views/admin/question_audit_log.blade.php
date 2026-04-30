<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Question Audit Log') }}</title>
    <style>
        :root {
            --bg: #0f1115;
            --panel: #1a1d24;
            --border: #2a2f3a;
            --text: #e6e8ec;
            --muted: #9aa0aa;
            --accent: #4f8cff;
            --ok: #2ecc71;
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
        h1 { font-size: 20px; margin: 0 0 16px; }
        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 16px;
        }
        form.filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }
        form.filters label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        form.filters input, form.filters select {
            width: 100%;
            padding: 6px 8px;
            background: #0f1115;
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 4px;
            font: inherit;
        }
        form.filters .actions { grid-column: 1 / -1; display: flex; gap: 8px; align-items: center; }
        button, .btn {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 7px 14px;
            cursor: pointer;
            font: inherit;
            text-decoration: none;
            display: inline-block;
        }
        button.secondary, .btn.secondary { background: #3a3f4a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge.ok { background: rgba(46, 204, 113, 0.15); color: var(--ok); }
        .badge.ko { background: rgba(231, 76, 60, 0.15); color: var(--ko); }
        .muted { color: var(--muted); }
        .empty { text-align: center; padding: 32px; color: var(--muted); }
        .pagination {
            display: flex;
            gap: 4px;
            margin-top: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        .pagination .info { color: var(--muted); margin-left: auto; font-size: 12px; }
        code.endpoint { font-size: 12px; color: var(--accent); }
    </style>
</head>
<body>
    <h1>{{ __('Question Audit Log') }}</h1>

    <div class="panel">
        <form class="filters" method="GET" action="{{ url()->current() }}">
            @if(request()->query('token'))
                <input type="hidden" name="token" value="{{ request()->query('token') }}">
            @endif
            <div>
                <label for="f-user">{{ __('User (name or ID)') }}</label>
                <input type="text" id="f-user" name="user" value="{{ $filters['user'] }}" placeholder="{{ __('Search by user name or ID') }}">
            </div>
            <div>
                <label for="f-endpoint">{{ __('Endpoint') }}</label>
                <select id="f-endpoint" name="endpoint">
                    <option value="">{{ __('All') }}</option>
                    @foreach($endpoints as $ep)
                        <option value="{{ $ep }}" @selected($filters['endpoint'] === $ep)>{{ $ep }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-status">{{ __('Status') }}</label>
                <select id="f-status" name="status">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    <option value="accepted" @selected($filters['status'] === 'accepted')>{{ __('Accepted only') }}</option>
                    <option value="rejected" @selected($filters['status'] === 'rejected')>{{ __('Rejected only') }}</option>
                </select>
            </div>
            <div>
                <label for="f-from">{{ __('From') }}</label>
                <input type="date" id="f-from" name="from" value="{{ $filters['from'] }}">
            </div>
            <div>
                <label for="f-to">{{ __('To') }}</label>
                <input type="date" id="f-to" name="to" value="{{ $filters['to'] }}">
            </div>
            <div class="actions">
                <button type="submit">{{ __('Filter') }}</button>
                <a class="btn secondary" href="{{ url()->current() }}@if(request()->query('token'))?token={{ request()->query('token') }}@endif">{{ __('Reset') }}</a>
            </div>
        </form>
    </div>

    <div class="panel">
        @if($rows->isEmpty())
            <div class="empty">{{ __('No records found') }}</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Endpoint') }}</th>
                        <th class="num">{{ __('HTTP Status') }}</th>
                        <th>{{ __('Accepted') }}</th>
                        <th>{{ __('Created At') }}</th>
                        <th>{{ __('Responded At') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>
                                @if($row->caller_name)
                                    {{ $row->caller_name }}
                                    <div class="muted" style="font-size:11px;">#{{ $row->caller_user_id }}</div>
                                @elseif($row->caller_user_id)
                                    <span class="muted">#{{ $row->caller_user_id }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td><code class="endpoint">{{ $row->endpoint }}</code></td>
                            <td class="num">{{ $row->http_status ?? '—' }}</td>
                            <td>
                                @if($row->accepted)
                                    <span class="badge ok">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge ko">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td>{{ optional($row->created_at)->format('Y-m-d H:i:s') ?? '—' }}</td>
                            <td>{{ optional($row->responded_at)->format('Y-m-d H:i:s') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {!! $rows->links() !!}
                <span class="info">
                    {{ __('Showing :from–:to of :total', ['from' => $rows->firstItem(), 'to' => $rows->lastItem(), 'total' => $rows->total()]) }}
                </span>
            </div>
        @endif
    </div>
</body>
</html>
