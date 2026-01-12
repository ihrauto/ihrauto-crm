<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Report - {{ now()->format('Y-m-d') }}</title>
    <style>
        body { font-family: "Inter", sans-serif; line-height: 1.5; color: #111; max-width: 210mm; margin: 0 auto; padding: 20px; background: white; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1e1b4b; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #1e1b4b; font-size: 18pt; text-transform: uppercase; letter-spacing: 1px; }
        .meta { color: #666; font-size: 10pt; margin-top: 10px; }
        
        .section { margin-bottom: 30px; page-break-inside: avoid; }
        .section-title { font-size: 14pt; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 15px; color: #1e1b4b; }
        
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .card { background: white; border: 1px solid #ddd; padding: 15px; border-radius: 8px; page-break-inside: avoid; }
        .label { font-size: 9pt; color: #666; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .value { font-size: 20pt; font-weight: bold; color: #111; margin: 5px 0; }
        .subtext { font-size: 9pt; color: #555; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10pt; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #ccc; }
        th { font-size: 9pt; text-transform: uppercase; color: #1e1b4b; background: #f3f4f6; font-weight: bold; }
        tr { page-break-inside: avoid; }
        
        .print-footer { display: none; }
        .no-print { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }

        @media print {
            @page { size: A4; margin: 15mm; }
            body { padding: 0; max-width: 100%; margin: 0; }
            .no-print { display: none !important; }
            
            .header { margin-bottom: 20px; }
            .section { margin-bottom: 25px; }
            .card { border: 1px solid #000; } /* High contrast for print */
            
            /* Professional Pagination */
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            
            .print-footer {
                display: block;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 8pt;
                color: #888;
                padding-top: 10px;
                border-top: 1px solid #eee;
                background: white;
            }
            
            /* Compact active layout */
            .value { font-size: 18pt; }
        }
        
        .btn-print { display: inline-flex; align-items: center; background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-bottom: 20px; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: none; cursor: pointer; transition: background 0.2s; }
        .btn-print:hover { background: #4338ca; }
        .back-link { display: inline-block; margin-right: 20px; color: #666; text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link:hover { color: #111; text-decoration: underline; }
    </style>
</head>

<body>
    <div class="no-print">
        <a href="{{ route('management') }}" class="back-link">&larr; Back to Dashboard</a>
        <button onclick="window.print()" class="btn-print">Print / Save as PDF</button>
    </div>

    <div class="header">
        <h1>Management Business Report</h1>
        <div class="meta">Generated on {{ now()->format('F j, Y \a\t H:i') }} | User: {{ optional(auth()->user())->name ?? 'System User' }}</div>
    </div>

    <div class="section">
        <div class="section-title">Key Performance Indicators</div>
        <div class="grid">
            <div class="card">
                <div class="label">Monthly Revenue</div>
                <div class="value">${{ number_format($kpis['monthly_revenue']['current'], 0) }}</div>
                <div class="subtext">{{ $kpis['monthly_revenue']['growth'] }}% from last month</div>
            </div>
            <div class="card">
                <div class="label">Service Completion Rate</div>
                <div class="value">{{ $kpis['service_completion_rate']['rate'] }}%</div>
                <div class="subtext">{{ $kpis['service_completion_rate']['completed'] }} /
                    {{ $kpis['service_completion_rate']['total'] }} services</div>
            </div>
            <div class="card">
                <div class="label">Active Customers</div>
                <div class="value">{{ number_format($customer_analytics['active']) }}</div>
                <div class="subtext">{{ $customer_analytics['new_this_month'] }} new this month</div>
            </div>
            <div class="card">
                <div class="label">Storage Utilization</div>
                <div class="value">{{ $kpis['storage_utilization']['percentage'] }}%</div>
                <div class="subtext">{{ $kpis['storage_utilization']['used'] }} /
                    {{ $kpis['storage_utilization']['total'] }} slots</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Recent Performance (Last 7 Days)</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Check-ins</th>
                    <th>Completed</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($performance as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['checkins'] }}</td>
                        <td>{{ $day['completed'] }}</td>
                        <td>${{ number_format($day['revenue'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Storage & Tire Statistics</div>
        <div class="grid">
            <div class="card">
                <div class="label">Tire Inventory</div>
                <div class="value">{{ number_format($tire_analytics['stats']['total_stored']) }}</div>
                <div class="subtext">Total tires in storage</div>
            </div>
            <div class="card">
                <div class="label">Seasonal Breakdown</div>
                <div class="subtext" style="font-size: 0.9em; margin-top: 10px;">
                    Winter: <strong>{{ $tire_analytics['stats']['winter_tires'] }}</strong><br>
                    Summer: <strong>{{ $tire_analytics['stats']['summer_tires'] }}</strong><br>
                    All Season: <strong>{{ $tire_analytics['stats']['all_season_tires'] }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Pending Actions & Alerts</div>
        @if($alerts->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Action Required</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alerts as $alert)
                        <tr>
                            <td style="color: {{ $alert['type'] === 'warning' ? '#b91c1c' : '#047857' }}">
                                {{ ucfirst($alert['type']) }}</td>
                            <td>{{ $alert['message'] }}</td>
                            <td>{{ $alert['action'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="color: #666; font-style: italic;">No pending alerts.</p>
        @endif
    </div>

    <div
        style="margin-top: 50px; text-align: center; color: #999; font-size: 0.8em; border-top: 1px solid #eee; padding-top: 20px;">
        &copy; {{ date('Y') }} IHRAUTO CRM. Confidential Business Document.
    </div>

    <div class="print-footer">
        &copy; {{ date('Y') }} IHRAUTO CRM &bull; Confidential Business Document &bull; Page <span class="page-number"></span>
    </div>

    <script>
        // Auto-print prompt on load
        // window.onload = function() { window.print(); }
    </script>
</body>

</html>
```