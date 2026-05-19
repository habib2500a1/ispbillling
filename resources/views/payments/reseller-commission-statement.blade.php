<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Commission statement</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;} table{width:100%;border-collapse:collapse;} td,th{border:1px solid #ccc;padding:6px;}</style>
</head>
<body>
    <h2>{{ $company }} — Reseller commission</h2>
    <p>Reseller: {{ $commission->reseller?->name }} ({{ $commission->reseller?->code }})</p>
    <p>Date: {{ $commission->earned_at?->format('Y-m-d H:i') ?? '—' }}</p>
    <table>
        <tr><th>Gross payment</th><td>{{ number_format((float) $commission->gross_amount, 2) }} BDT</td></tr>
        <tr><th>Commission</th><td>{{ number_format((float) $commission->commission_amount, 2) }} BDT</td></tr>
        <tr><th>Status</th><td>{{ $commission->status }}</td></tr>
        <tr><th>Customer</th><td>{{ $commission->customer?->name ?? '—' }}</td></tr>
        <tr><th>Payment ID</th><td>{{ $commission->payment_id }}</td></tr>
    </table>
</body>
</html>
