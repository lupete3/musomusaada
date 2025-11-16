<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails du Carnet</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 5px;
        }
        .title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th, .table td {
            border: 1px solid #000;
            padding: 4px;
        }
        th {
            background: #f1c206;
        }
        .header-table {
            width: 100%;
            margin-bottom: 10px;
        }
        .logo {
            width: 70px;
        }
        .section-title {
            margin-top: 12px;
            font-weight: bold;
            font-size: 12px;
        }

        /* === NOUVEAU: structure en 3 colonnes === */
        .info-grid {
            width: 100%;
            margin-top: 10px;
            border: 1px solid #ddd;
            padding: 8px;
        }
        .info-grid td {
            vertical-align: top;
            padding: 4px 8px;
            width: 33%;
        }
        .info-label {
            font-weight: bold;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 15%;">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.jpg'))) }}" class="logo">
            </td>
            <td style="text-align:center;">
                <h2 style="margin:0;">{{ strtoupper(config('app.name')) }}</h2>
                <small>{{ env('APP_ADRESS') }}</small><br>
                <small>{{ env('APP_PHONE') }} | {{ env('APP_EMAIL') }}</small>
            </td>
            <td style="width: 25%; font-size:10px; text-align:right;">
                <strong>Date :</strong> {{ now()->format('d/m/Y') }}<br>
                <strong>Heure :</strong> {{ now()->format('H:i') }}<br>
                <strong>Agent :</strong><br>
                {{ Auth::user()->name }} {{ Auth::user()->postnom }}
            </td>
        </tr>
    </table>
    <hr style="margin: 5px 0; border-bottom: 2px solid #ed8d0f;">

    <h3 class="title">DÉTAIL DU CARNET D'ADHÉSION</h3>

    <!-- === Informations structurées en colonnes === -->
    <table class="info-grid">
        <tr>
            <td>
                <div><span class="info-label">Code Carnet :</span> {{ $card->code }}</div>
                <div><span class="info-label">Membre :</span>
                    {{ $member->code }} - {{ $member->name }} {{ $member->postnom }} {{ $member->prenom }}
                </div>
                <div><span class="info-label">Agent :</span>
                    {{ optional($card->agent)->name }}
                    {{ optional($card->agent)->postnom }}
                    {{ optional($card->agent)->prenom }}
                </div>
            </td>

            <td>
                <div><span class="info-label">Prix du carnet :</span>
                    {{ number_format($card->price, 2) }} {{ $card->currency }}
                </div>
                <div><span class="info-label">Mise :</span>
                    {{ number_format($card->subscription_amount, 2) }} {{ $card->currency }}
                </div>
                <div><span class="info-label">Status :</span>
                    {{ $card->is_active ? 'Active' : 'Terminée' }}
                </div>
            </td>

            <td>
                <div><span class="info-label">Date début :</span>
                    {{ \Carbon\Carbon::parse($card->start_date)->format('d/m/Y') }}
                </div>
                <div><span class="info-label">Date fin :</span>
                    {{ \Carbon\Carbon::parse($card->end_date)->format('d/m/Y') }}
                </div>
                <div><span class="info-label">Jours payés :</span>
                    {{ 31 - $unpaidCount }} / 31
                </div>
                <div><span class="info-label">Jours restants :</span>
                    {{ $unpaidCount }} / 31
                </div>
            </td>
        </tr>
    </table>

    <!-- Totaux -->
    <table class="info-grid" style="margin-top:5px;">
        <tr>
            <td>
                <span class="info-label">Total épargné :</span>
                {{ number_format($card->total_saved, 2) }} {{ $card->currency }}
            </td>
            <td>
                <span class="info-label">Total restant :</span>
                {{ number_format($card->total_remaining, 2) }} {{ $card->currency }}
            </td>
            <td>
                <span class="info-label">Solde :</span>
                {{ number_format($card->total_saved - $card->subscription_amount, 2) }} {{ $card->currency }}
            </td>
        </tr>
    </table>

    <!-- Historique -->
    <div class="section-title">Historique des Contributions Payées</div>

    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date de paiement</th>
                <th>Montant</th>
                <th>Solde</th>
            </tr>
        </thead>
        <tbody>
            @php
                $solde = 0;
            @endphp

            @foreach($paidContributions as $index => $contribution)
                @php
                    $solde += $contribution->amount;
                @endphp

                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($contribution->updated_at)->format('d/m/Y') }}</td>
                    <td>{{ number_format($contribution->amount, 2) }} {{ $card->currency }}</td>
                    <td>{{ number_format($solde, 2) }} {{ $card->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top:20px; text-align:center;">
        Rapport généré le {{ now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
