<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 10px;
            color: #333;
        }
        .container {
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header .logo {
            display: table-cell;
            width: 100px;
            vertical-align: middle;
        }
        .header .logo img {
            max-width: 80px;
        }
        .header .report-title-section {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }
        .report-title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .report-subtitle {
            font-size: 12px;
            color: #555;
            margin: 0;
        }
        .summary-section {
            width: 100%;
            margin-bottom: 25px;
            display: table;
        }
        .summary-box {
            display: table-cell;
            width: 48%;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-left: 3px solid #4CAF50;
            padding: 15px;
            vertical-align: middle;
        }
        .summary-box .label {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .summary-box .value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table thead th {
            background-color: #4CAF50;
            color: #ffffff;
            text-align: left;
            font-weight: bold;
            padding: 8px;
            font-size: 11px;
        }
        .data-table tbody td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">

        <div class="header">
            <div class="logo">
                <img src="{{ public_path('images/fajmalogo.png') }}" alt="Logo Fajma Wallet">
            </div>
            <div class="report-title-section">
                <h1 class="report-title">{{ $title }}</h1>
                @if(isset($subtitle))
                <p class="report-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="summary-section">
            <div class="summary-box">
                <div class="label">Nombre total de transactions</div>
                <div class="value">{{ $total_count }}</div>
            </div>
            <div style="display: table-cell; width: 4%;"></div> <div class="summary-box">
                <div class="label">Montant Total</div>
                <div class="value">{{ number_format($total_amount, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Montant</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data as $transaction)
                    <tr>
                        <td>{{ $transaction->transaction_uid }}</td>
                        <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i') }}</td>
                        <td>{{ $transaction->user?->first_name }} {{ $transaction->user?->last_name }}</td>
                        <td>{{ $transaction->transactionType?->display_name }}</td>
                        <td>{{ number_format($transaction->amount, 0, ',', ' ') }} FCFA</td>
                        <td>{{ $transaction->paymentStatus?->display_name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">Aucune transaction à afficher pour cette période.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            Rapport généré par FAJMA Wallet Santé le {{ date('d/m/Y') }} à {{ date('H:i:s') }}
        </div>

    </div>
</body>
</html>
