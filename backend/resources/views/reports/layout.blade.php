<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report->title ?? 'Report' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11px; margin: 0; }
        .doc { padding: 4px 2px; }
        h1, h2, h3 { margin: 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #555; }
        .upper { text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #333; padding: 4px 6px; vertical-align: top; }
        table.grid th { background: #f1f1f1; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
        .title { font-size: 14px; font-weight: bold; text-align: center; margin: 10px 0 2px; text-transform: uppercase; }
        .subtitle { font-size: 11px; text-align: center; margin-bottom: 8px; }
        .sigs { width: 100%; margin-top: 28px; }
        .sigs td { vertical-align: bottom; padding: 0 12px; width: 50%; }
        .sig-name { font-weight: bold; border-top: 1px solid #333; padding-top: 2px; text-transform: uppercase; }
        .sig-title { font-size: 10px; color: #333; }
        .sig-label { font-size: 10px; margin-bottom: 26px; }
        .page-break { page-break-after: always; }
        .totals td { font-weight: bold; background: #f6f6f6; }
        .meta { font-size: 10px; }
        .meta td { padding: 2px 4px; }
        .section { font-weight: bold; text-transform: uppercase; font-size: 10px; letter-spacing: .3px;
                   background: #e7edf5; border: 1px solid #c9d4e2; padding: 3px 6px; margin: 10px 0 4px; }
    </style>
</head>
<body>
    <div class="doc">
        @yield('body')
    </div>
</body>
</html>
