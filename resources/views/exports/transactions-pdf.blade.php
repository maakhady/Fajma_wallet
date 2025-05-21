<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 12px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #f2f2f2;
            text-align: left;
            font-weight: bold;
            padding: 5px;
            border: 1px solid #ddd;
        }
        td {
            padding: 5px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }
        .summary {
            margin-top: 15px;
            font-weight: bold;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $title }}</div>
        <div class="subtitle">{{ $subtitle }}</div>
    </div>
    
    <table>
        <thead>
            <tr>
                @if(!empty($data))
                    @foreach(array_keys($data[0]) as $key)
                        <th>{{ $key }}</th>
                    @endforeach
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="summary">
        Nombre total de transactions: {{ $total_count }}<br>
        Montant total: {{ number_format($total_amount, 2, ',', ' ') }} FCFA
    </div>
    
    <div class="footer">
        Rapport généré via FAJMA Wallet Santé - {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>