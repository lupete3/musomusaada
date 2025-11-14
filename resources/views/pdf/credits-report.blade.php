<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Rapport Global des Crédits</title>

<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
        margin: 5px;
        color: #000;
    }
    .header {
        text-align: center;
        margin-bottom: 30px;
    }
    .container {
        width: 100%;
    }
    .summary-table th, .summary-table td {
            text-align: center;
        }
    .info-row {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        padding: 15px;
        border-radius: 6px;
        background-color: #f9f9f9;
    }
    .info-row div {
        width: 48%;
    }
    .table {
        width: 100%; border-collapse: collapse; margin-top: 20px;
    }
    .table td, .table th {
        border: 1px solid #000; padding: 2px; font-size: 10px;
    }
    th {
        background-color: #f1c206;
    }
    .logo {
        width: 80px;
    }
    td:first-child, th:first-child {
        text-align: center;
    }
</style>

</head>

<body>

    <div class="header" style="padding-bottom: 5px;">
        <table style="width:100%;">
            <tr>
                <td style="width: 15%;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.jpg'))) }}" class="logo" alt="Logo">
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
                    {{ Auth::user()->name ?? 'N/A' }} {{ Auth::user()->postnom ?? '' }} {{ Auth::user()->prenom ?? '' }}
                </td>
            </tr>
        </table>
        <hr style="margin: 10px 0; border-bottom: 2px solid #ed8d0f;">
        <h3 class="text-center" style="text-decoration: underline; margin-bottom: 2px;">RAPPORT GLOBAL DES CRÉDITS</h3>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID Crédit</th>
                <th>Code Membre</th>
                <th>Nom Membre</th>
                <th>Date Crédit</th>
                <th>Date Début</th>
                <th>Date Fin</th>

                <th>Montant Crédit</th>
                <th>Total à Rembourser</th>
                <th>Déjà Payé</th>
                <th>Différence</th>
                <th>Reste</th>

                <th>Pénalité</th>
                <th>Agent Crédit</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($credits as $credit)
                @php
                    // Solde du compte du client
                    $balance = (float) ($credit->user->accounts->firstWhere('currency', $credit->currency)?->balance ?? 0);

                    // Total à rembourser : somme des total_due
                    $totalToRepay = $credit->repayments->sum('total_due');

                    // Déjà payé = solde du compte
                    $amountPaid = $balance;

                    // Différence
                    $diff = $amountPaid - $totalToRepay;

                    // Reste
                    $remaining = max(0, $totalToRepay - $amountPaid);
                @endphp

                <tr>
                <td>#{{ $credit->id }}</td>
                <td>{{ $credit->user->code }}</td>
                <td>{{ $credit->user->name.' '.$credit->user->postnom.' '.$credit->user->prenom }}</td>

                <td>{{ \Carbon\Carbon::parse($credit->created_at)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($credit->start_date)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($credit->due_date)->format('d/m/Y') }}</td>

                <!-- Montant crédit -->
                <td>{{ number_format($credit->amount, 2) }} {{ $credit->currency }}</td>

                <!-- Total à rembourser -->
                <td>{{ number_format($totalToRepay, 2) }} {{ $credit->currency }}</td>

                <!-- Déjà payé (balance) -->
                <td>{{ number_format($amountPaid, 2) }} {{ $credit->currency }}</td>

                <!-- Différence -->
                <td>
                    @if ($diff >= 0)
                        +{{ number_format($diff, 2) }} {{ $credit->currency }}
                    @else
                        {{ number_format($diff, 2) }} {{ $credit->currency }}
                    @endif
                </td>

                <!-- Reste -->
                <td>
                    {{ number_format($remaining, 2) }} {{ $credit->currency }}
                </td>

                <!-- Pénalité -->
                <td>{{ number_format($credit->repayments->sum('penalty'), 2) }} {{ $credit->currency }}</td>

                <!-- Agent crédit -->
                <td>{{ $credit->agent ? $credit->agent->name.' '.$credit->agent->postnom : 'N/A' }}</td>

                <!-- Status -->
                <td>{{ $credit->is_paid ? 'Remboursé' : 'En cours' }}</td>
            </tr>

            @endforeach
        </tbody>
    </table>

    <h3 class="section-title">Récapitulatif</h3>
    <table class="summary-table table">
        <thead>
            <tr>
                <th>Devise</th>
                <th>Total Crédits</th>
                <th>Remboursés</th>
                <th>En cours</th>
                <th>Intérêt</th>
                <th>Pénalités</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['USD', 'CDF'] as $curr)
                <tr>
                    <td>{{ $curr }}</td>
                    <td>{{ number_format($totals['totalByCurrency'][$curr] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['totalPaidByCurrency'][$curr] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['totalUnpaidByCurrency'][$curr] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['interestByCurrency'][$curr] ?? 0, 2) }}</td>
                    <td>{{ number_format($totals['penaltyByCurrency'][$curr] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
