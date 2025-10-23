<?php

namespace App\Livewire\Agent;

use Livewire\Component;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AgentDashboard extends Component
{
    public $filter = 'day'; // Valeur par défaut
    public $user_id;
    public $isShowTransaction = false;
    public $transactions = [];

    protected $queryString = ['filter'];

    public $startDate;
    public $endDate;
    public $periodLabel;

    public function mount()
    {
        $this->filter = 'day';
    }

    protected function applyDateFilter($query)
    {
        $now = now();

        switch ($this->filter) {
            case 'day':
                $this->periodLabel = 'Aujourd\'hui (' . $now->format('d/m/Y') . ')';
                return $query->whereDate('created_at', $now->toDateString());

            case 'week':
                $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                $endOfWeek   = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
                $this->periodLabel = 'Semaine du ' . $startOfWeek->format('d/m/Y') . ' au ' . $endOfWeek->format('d/m/Y');
                return $query->whereBetween('created_at', [$startOfWeek, $endOfWeek]);

            case 'month':
                $this->periodLabel = 'Mois de ' . $now->translatedFormat('F Y');
                return $query->whereMonth('created_at', $now->month)
                            ->whereYear('created_at', $now->year);

            case 'year':
                $this->periodLabel = 'Année ' . $now->year;
                return $query->whereYear('created_at', $now->year);

            case 'custom':
                if ($this->startDate && $this->endDate) {
                    $start = Carbon::parse($this->startDate)->startOfDay();
                    $end   = Carbon::parse($this->endDate)->endOfDay();
                    $this->periodLabel = 'Du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y');
                    return $query->whereBetween('created_at', [$start, $end]);
                }
                $this->periodLabel = 'Intervalle personnalisé (en attente)';
                return $query;

            default:
                $this->periodLabel = 'Toutes les transactions';
                return $query;
        }
    }


    public function showTransactions($userId, $filter = 'day', $start = null, $end = null)
    {
        $this->user_id = $userId;
        $this->filter = $filter;
        $this->startDate = $start;
        $this->endDate = $end;
        $this->isShowTransaction = true;

        $query = Transaction::where('user_id', $this->user_id);
        $query = $this->applyDateFilter($query);

        $this->transactions = $query->orderByDesc('created_at')->get();
    }

    public function render()
    {
        $user = Auth::user();

        if ($user->can('afficher-caisse-agent')) {
            // Récupère tous les utilisateurs ayant des comptes liés
            $users = User::whereHas('roles', function ($q) {
            $q->where('name', '!=', 'membre');
            $q->where('name', '!=', 'super it');
        })->get();
        } else {
            // Récupère uniquement le recouvreur connecté avec ses comptes
            $agentAccounts = User::where('id', $user->id)
                ->with(['agentAccounts' => function ($query) {
                    $query->orderBy('currency');
                }])
                ->get();
            $users = User::where('id', $user->id)->get();
        }

        // Calculer le solde par utilisateur et par devise
        $balances = [];

        $typesPositifs = [
            'dépôt',
            'mise_quotidienne',
            'vente_carte_adhesion'
        ];

        $typesNegatifs = [
            'retrait',
            'retrait_carte_adhesion'
        ];

        foreach ($users as $user) {
            $query = Transaction::where('user_id', $user->id);
            $query = $this->applyDateFilter($query);

            $totals = $query
                ->select(
                    'currency',
                    DB::raw("
                        SUM(
                            CASE
                                WHEN type IN ('" . implode("','", $typesPositifs) . "') THEN amount
                                WHEN type IN ('" . implode("','", $typesNegatifs) . "') THEN -amount
                                ELSE 0
                            END
                        ) as balance
                    ")
                )
                ->groupBy('currency')
                ->pluck('balance', 'currency')
                ->toArray();

            $balances[$user->id] = $totals;
        }

        return view('livewire.agent.agent-dashboard', [
            'users' => $users,
            'balances' => $balances,
        ]);
    }
}
