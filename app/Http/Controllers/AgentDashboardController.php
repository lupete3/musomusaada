<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AgentDashboardController extends Controller
{
    public function index()
    {
        return view('agent-dashboard');
    }

    /**
     * Export transactions for a specific user with date filter.
     *
     * @param int $userId
     * @param string $filter
     * @return \Illuminate\Http\Response
     */
    public function exportTransactions(Request $request, $userId, $filter = 'day')
    {
        $user = User::findOrFail($userId);
        $now = now();

        $query = Transaction::where('user_id', $userId);

        // Gestion des filtres temporels
        switch ($filter) {
            case 'day':
                $query->whereDate('created_at', $now->toDateString());
                $periodLabel = "Aujourd'hui (" . $now->format('d/m/Y') . ")";
                break;

            case 'week':
                $startOfWeek = $now->copy()->startOfWeek();
                $endOfWeek = $now->copy()->endOfWeek();
                $query->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
                $periodLabel = "Semaine du " . $startOfWeek->format('d/m/Y') . " au " . $endOfWeek->format('d/m/Y');
                break;

            case 'month':
                $query->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year);
                $periodLabel = "Mois de " . $now->translatedFormat('F Y');
                break;

            case 'year':
                $query->whereYear('created_at', $now->year);
                $periodLabel = "Année " . $now->year;
                break;

            case 'custom':
                $start = $request->input('startDate');
                $end = $request->input('endDate');
                $startDate = Carbon::parse($start)->startOfDay();
                $endDate = Carbon::parse($end)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
                $periodLabel = "Du " . $startDate->format('d/m/Y') . " au " . $endDate->format('d/m/Y');
                break;

            default:
                $periodLabel = "Toutes les transactions";
        }

        // Récupération des transactions filtrées
        $transactions = $query->orderByDesc('created_at')->get();
        $transactionCount = $transactions->count();

        // ✅ Totaux par devise (groupés)
        $totalsByCurrency = $transactions->groupBy('currency')->map(function ($group) {
            $totalDeposits = $group->whereIn('type', ['dépôt', 'mise_quotidienne', 'vente_carte_adhesion'])->sum('amount');
            $totalWithdrawals = $group->whereIn('type', ['retrait', 'retrait_carte_adhesion'])->sum('amount');
            $balance = $totalDeposits - $totalWithdrawals;

            return [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'balance' => $balance,
            ];
        });

        // ✅ PDF avec tous les paramètres
        $pdf = Pdf::loadView('pdf.agent-transactions', compact(
            'user',
            'transactions',
            'filter',
            'totalsByCurrency',
            'transactionCount',
            'periodLabel'
        ));

        return $pdf->download("transactions_{$user->id}_{$filter}.pdf");
    }

}
