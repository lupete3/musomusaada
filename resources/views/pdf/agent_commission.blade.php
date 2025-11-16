<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commissions Agents</title>
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
    <h2 class="title">Commission des Agents</h2>

    <!-- Totaux en cartes -->
    <div class="card">
        <h5>Totaux</h5>
        <table class="table">
            <tr>
                <th>Total carnets vendus</th>
                <th>Total commissions carnets</th>
                <th>Total général</th>
            </tr>
            <tr>
                <td>{{ number_format($totalCarte,2) }}</td>
                <td>{{ number_format($totalCarnet,2) }}</td>
                <td>{{ number_format($totalGeneral,2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Synthèse des agents -->
    <div class="card">
        <h5>Synthèse par agent</h5>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Agent</th>
                    <th>Total carnets vendus</th>
                    <th>Total commissions carnets</th>
                    <th>Total général</th>
                    <th>Part Agent</th>
                    <th>Part Bureau</th>
                </tr>
            </thead>
            <tbody>
                @foreach($synthese as $i => $row)
                <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $row['agent']->name }} {{ $row['agent']->postnom }}</td>
                    <td>{{ number_format($row['total_carnets'],2) }}</td>
                    <td>{{ number_format($row['total_commissions'],2) }}</td>
                    <td>{{ number_format($row['total_general'],2) }}</td>
                    <td class="text-success">{{ number_format($row['agent_part'],2) }}</td>
                    <td class="text-danger">{{ number_format($row['bureau_part'],2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">TOTAL GÉNÉRAL</th>
                    <th>{{ number_format($footerTotals['total_carnets'],2) }}</th>
                    <th>{{ number_format($footerTotals['total_commissions'],2) }}</th>
                    <th>{{ number_format($footerTotals['total_general'],2) }}</th>
                    <th>{{ number_format($footerTotals['agent_part'],2) }}</th>
                    <th>{{ number_format($footerTotals['bureau_part'],2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Tableau détaillé -->
    <div class="card">
        <h5>Détails des commissions</h5>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Agent</th>
                    <th>Type</th>
                    <th>Montant</th>
                    <th>Membre</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($commissions as $index => $c)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $c->agent->name }} {{ $c->agent->postnom }}</td>
                    <td>{{ ucfirst(($c->type=='carte')?'ventes carnet':'commission carnet') }}</td>
                    <td>{{ number_format($c->amount,2) }}</td>
                    <td>{{ $c->member? $c->member->name.' '.$c->member->postnom.' '.$c->member->prenom : '-' }}</td>
                    <td>{{ $c->commission_date }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="text-align:center; margin-top:10px;">
        Rapport généré le {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
