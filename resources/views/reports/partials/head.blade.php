{{-- Shared report PDF header. dompdf-friendly, inline styles only. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 20px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        td, th { padding: 4px 6px; vertical-align: top; text-align: left; }
        th { border-bottom: 1px solid #ccc; color: #555; }
        td.num, th.num { text-align: right; }
        tbody tr { border-bottom: 1px solid #f0f0f0; }
        .meta { color: #666; font-size: 11px; margin: 0 0 2px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">{{ $tenant->name }}</p>
    <p class="meta">Generated {{ \Illuminate\Support\Carbon::parse($generatedAt)->format('d/m/Y H:i') }}</p>
