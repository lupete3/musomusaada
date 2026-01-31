<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Détails du Collecteur</title>
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
            margin-bottom: 10px;
        }

        .table td,
        .table th {
            border: 1px solid;
            padding: 4px;
        }

        th {
            background-color: #f1c206;
        }

        .logo {
            width: 80px;
        }

        .fw-bold {
            font-weight: bold;
        }

        .badge {
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 8px;
        }
    </style>
</head>

<body>

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
                    {{ Auth::user()->name }} {{ Auth::user()->postnom }}
                </td>
            </tr>
        </table>
        <hr style="margin: 10px 0; border-bottom: 2px solid #ed8d0f;">
        <h3 class="text-center" style="text-decoration: underline; margin-bottom: 2px;">DÉTAILS DES CARNETS DU
            COLLECTEUR : {{ strtoupper($collectorName) }}</h3>
    </div>

    <div style="margin-bottom: 10px;">
        <strong>Période :</strong>
        @if ($period === 'custom')
            Du {{ \Carbon\Carbon::parse($dateStart)->format('d/m/Y') }} au
            {{ \Carbon\Carbon::parse($dateEnd)->format('d/m/Y') }}
        @else
            {{ ucfirst($period) }}
        @endif
        <br>
        <strong>Devise :</strong> {{ $currency }}
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Code Carnet</th>
                <th>Membre</th>
                <th>Statut</th>
                <th class="text-end">Dépôts (Période)</th>
                <th class="text-end">Retraits (Période)</th>
                <th class="text-end">Solde Actuel</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detailsData as $detail)
                <tr>
                    <td class="fw-bold">{{ $detail['card_code'] }}</td>
                    <td>{{ $detail['member_name'] }}</td>
                    <td>{{ $detail['is_active'] ? 'Actif' : 'Clôturé' }}</td>
                    <td class="text-end">{{ number_format($detail['total_deposits'], 2) }}</td>
                    <td class="text-end">{{ number_format($detail['total_withdrawals'], 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($detail['current_balance'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot style="background-color: #f8f9fa;">
            <tr class="fw-bold">
                <td colspan="3">TOTAUX</td>
                <td class="text-end">{{ number_format(collect($detailsData)->sum('total_deposits'), 2) }}</td>
                <td class="text-end">{{ number_format(collect($detailsData)->sum('total_withdrawals'), 2) }}</td>
                <td class="text-end">{{ number_format(collect($detailsData)->sum('current_balance'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Rapport généré le {{ now()->format('d/m/Y H:i') }} - {{ config('app.name') }}
    </div>

</body>

</html>