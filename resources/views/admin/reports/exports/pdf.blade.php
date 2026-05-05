<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        .header {
            background: #111827;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: bold;
        }

        .header p {
            margin: 6px 0 0;
            font-size: 12px;
            color: #e5e7eb;
        }

        .section {
            padding: 18px 22px;
        }

        .section-title {
            background: #f3f4f6;
            border-left: 4px solid #2563eb;
            padding: 8px 10px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th {
            background: #1f2937;
            color: #ffffff;
            padding: 8px;
            border: 1px solid #d1d5db;
            text-align: left;
            font-size: 11px;
        }

        td {
            padding: 7px;
            border: 1px solid #d1d5db;
            font-size: 11px;
        }

        .meta-table th {
            width: 180px;
            background: #f9fafb;
            color: #111827;
        }

        .summary-box {
            display: inline-block;
            width: 30%;
            margin: 0 1% 10px 0;
            padding: 10px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            vertical-align: top;
        }

        .summary-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
            margin-top: 4px;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            left: 22px;
            right: 22px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>{{ $report->title }}</h1>
    <p>{{ $report->report_uid }} | {{ $reportTypes[$report->report_type] ?? $report->report_type }}</p>
</div>

<div class="section">
    <div class="section-title">Report Information</div>

    <table class="meta-table">
        <tr>
            <th>Report UID</th>
            <td>{{ $report->report_uid }}</td>
        </tr>
        <tr>
            <th>Report Type</th>
            <td>{{ $reportTypes[$report->report_type] ?? $report->report_type }}</td>
        </tr>
        <tr>
            <th>Date Range</th>
            <td>{{ $report->date_from ?? 'Start' }} to {{ $report->date_to ?? 'Today' }}</td>
        </tr>
        <tr>
            <th>Group By</th>
            <td>{{ $report->group_by ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Generated At</th>
            <td>{{ optional($report->generated_at)->format('d M Y, h:i A') }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Summary</div>

    @forelse (($payload['summary'] ?? []) as $key => $value)
        <div class="summary-box">
            <div class="summary-label">{{ str_replace('_', ' ', $key) }}</div>
            <div class="summary-value">
                @if (is_array($value) || is_object($value))
                    {{ json_encode($value) }}
                @elseif (is_numeric($value))
                    {{ number_format($value) }}
                @else
                    {{ $value }}
                @endif
            </div>
        </div>
    @empty
        <p>No summary data found.</p>
    @endforelse
</div>

<div class="section">
    <div class="section-title">Data</div>

    @php
        $rows = collect($payload['rows'] ?? [])->map(fn($row) => (array) $row)->values()->toArray();
    @endphp

    @if (count($rows))
        <table>
            <thead>
                <tr>
                    @foreach (array_keys($rows[0]) as $heading)
                        <th>{{ ucwords(str_replace('_', ' ', $heading)) }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>
                                @if (is_array($value) || is_object($value))
                                    {{ json_encode($value) }}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No data found.</p>
    @endif
</div>

<div class="footer">
    Generated by {{ config('app.name') }} | {{ now()->format('d M Y, h:i A') }}
</div>

</body>
</html>