<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu de transaction</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 5px;
        }
        .company-info {
            margin-bottom: 10px;
        }
        .receipt-info {
            margin: 20px 0;
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f9f9f9;
        }
        .transaction-details {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .transaction-details th, .transaction-details td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .transaction-details th {
            background-color: #f2f2f2;
        }
        .amount {
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('images/fajmalogo.png') }}" class="logo">
        <h1>{{ $company_name }}</h1>
        <div class="company-info">
            {{ $company_address }}<br>
            Tél: {{ $company_phone }}<br>
            Email: {{ $company_email }}
        </div>
    </div>

    <div class="receipt-info">
        <h2>REÇU DE TRANSACTION</h2>
        <p>N° du reçu: {{ $receipt_number }}</p>
        <p>Date du reçu: {{ $receipt_date }}</p>
    </div>

    <div>
        <h3>Détails de la transaction</h3>
        <table class="transaction-details">
            <tr>
                <th>ID Transaction</th>
                <td>{{ $transaction->transaction_uid }}</td>
            </tr>
            <tr>
                <th>Date de transaction</th>
                <td>{{ $date }}</td>
            </tr>
            <tr>
                <th>Client</th>
                <td>{{ $transaction->user->first_name }} {{ $transaction->user->last_name }}</td>
            </tr>
            <tr>
                <th>Type de transaction</th>
                <td>{{ $transaction->transactionType->display_name }}</td>
            </tr>
            <tr>
                <th>Carte</th>
                <td>{{ $transaction->card->card_number }}</td>
            </tr>
            @if($transaction->provider)
            <tr>
                <th>Prestataire</th>
                <td>{{ $transaction->provider->structure_name }}</td>
            </tr>
            @endif
            @if($transaction->paymentMean)
            <tr>
                <th>Moyen de paiement</th>
                <td>{{ $transaction->paymentMean->paymentType->display_name }}</td>
            </tr>
            @endif
            <tr>
                <th>Statut</th>
                <td>{{ $transaction->paymentStatus->display_name }}</td>
            </tr>
            <tr>
                <th>Montant</th>
                <td class="amount">{{ number_format($transaction->amount, 2, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <th>Solde précédent</th>
                <td>{{ number_format($transaction->previous_balance, 2, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <th>Solde actuel</th>
                <td>{{ number_format($transaction->current_balance, 2, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <th>Description</th>
                <td>{{ $transaction->description }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Ce reçu est généré automatiquement et ne nécessite pas de signature.</p>
        <p>FAJMA Health Wallet - Solution de paiement pour la santé</p>
    </div>
</body>
</html>