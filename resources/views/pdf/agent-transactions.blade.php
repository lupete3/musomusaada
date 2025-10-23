<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Rapport des Transactions</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 5px;
            color: #000;
        }

        .footer {
            text-align: center;
            margin-top: 50px
        }

        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table td,
        .table th {
            border: 1px solid #000;
            padding: 3px;
            font-size: 9px;
        }

        th {
            background-color: #f1c206;
        }

        .section-title {
            margin-top: 10px;
            font-weight: bold;
            text-align: center;
            font-size: 11px;
        }

        .recap {
            margin-top: 15px;
            background-color: #f7f7f7;
            border: 1px solid #ccc;
            padding: 8px;
        }

        .recap table td {
            padding: 4px;
        }

        .logo {
            width: 80px;
        }
    </style>
</head>

<body>

    {{-- HEADER --}}
    <div class="header" style="padding-bottom: 5px;">
        <table style="width:100%;">
            <tr>
                <td style="width: 15%;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.jpg'))) }}"
                        class="logo" alt="Logo">
                </td>
                <td style="width: 60%; text-align:center;">
                    <h2 style="margin: 0; font-size: 14px;">{{ strtoupper(config('app.name')) }}</h2>
                    <p style="margin: 0;">Adresse : {{ env('APP_ADRESS') }}</p>
                    <p style="margin: 0;">Tel : {{ env('APP_PHONE') }} – Email : {{ env('APP_EMAIL') }}</p>
                </td>
                <td style="width: 25%; text-align:right; font-size: 9px;">
                    <strong>Date :</strong> {{ now()->format('d/m/Y') }}<br>
                    <strong>Heure :</strong> {{ now()->format('H:i') }}<br>
                    <strong>Agent :</strong><br>
                    {{ $user->name ?? 'N/A' }} {{ $user->postnom ?? '' }} {{ $user->prenom ?? '' }}
                </td>
            </tr>
        </table>
        <hr style="margin: 10px 0; border-bottom: 2px solid #ed8d0f;">
        <h3 class="text-center" style="text-decoration: underline; margin-bottom: 2px;">RAPPORT DES TRANSACTIONS</h3>
        <p class="text-center"><strong>{{ $periodLabel }}</strong></p>
    </div>

    {{-- RÉSUMÉ --}}
    <div class="recap">
        <table width="100%">
            <tr>
                @foreach ($totalsByCurrency as $currency => $totals)
                    <td class="">
                        <li>Total dépôts : {{ number_format($totals['total_deposits'], 2, ',', ' ') }}
                            {{ $currency }}</li>
                        <li>Total retraits : {{ number_format($totals['total_withdrawals'], 2, ',', ' ') }}
                            {{ $currency }}</li>
                        <li>Solde : {{ number_format($totals['balance'], 2, ',', ' ') }} {{ $currency }}</li>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    {{-- TABLE DES TRANSACTIONS --}}
    <h4 class="section-title">Détails des Transactions</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Montant</th>
                <th>Devise</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transactions as $t)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ ucfirst($t->type) }}</td>
                    <td>{{ number_format($t->amount, 2) }}</td>
                    <td>{{ $t->currency }}</td>
                    <td>{{ $t->description }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- FOOTER --}}
    <div class="footer">
        Rapport généré le {{ now()->format('d/m/Y H:i') }} par {{ Auth::user()->name }}
        {{ Auth::user()->postnom ?? '' }}<br>
        {{ strtoupper(config('app.name')) }}
    </div>

</body>

</html>
