<?php

namespace App\Livewire\Repports;

use App\Models\MembershipCard;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class CollectorOverviewReport extends Component
{
    use WithPagination;
    protected $paginationTheme = 'bootstrap';

    public $collectorId = '';
    public $currency = 'CDF';
    public $period = 'month';
    public $dateStart;
    public $dateEnd;

    public $selectedCollectorIdForDetails = null;
    public $detailsData = [];
    public $selectedCollectorName = '';

    public function mount()
    {
        $this->dateStart = now()->startOfMonth()->format('Y-m-d');
        $this->dateEnd = now()->endOfMonth()->format('Y-m-d');
    }

    public function updated($field)
    {
        if (in_array($field, ['collectorId', 'currency', 'period', 'dateStart', 'dateEnd'])) {
            $this->resetPage();
        }
    }

    public function getCollectorsProperty()
    {
        // On récupère les utilisateurs qui ont au moins une carte de membre (collecteurs)
        return User::whereHas('membershipCards')->get();
    }

    public function getReportDataProperty()
    {
        $query = User::whereHas('membershipCards');

        if ($this->collectorId) {
            $query->where('id', $this->collectorId);
        }

        $collectors = $query->get();

        $data = [];

        foreach ($collectors as $collector) {
            $cardIds = MembershipCard::where('user_id', $collector->id)
                ->where('currency', $this->currency)
                ->pluck('id');

            // 1. Total des dépôts (mise_quotidienne) dans la période
            // On filtre par account_id is null pour ne compter que le côté caisse/système et éviter le double comptage avec le côté membre
            $depositQuery = Transaction::whereIn('membership_card_id', $cardIds)
                ->where('type', 'mise_quotidienne')
                ->whereNull('account_id')
                ->where('currency', $this->currency);

            $depositQuery = $this->applyPeriodFilter($depositQuery);
            $totalDeposits = $depositQuery->sum('amount');

            // 2. Total des montants retirés (retrait_carte_adhesion) dans la période
            $withdrawalQuery = Transaction::whereIn('membership_card_id', $cardIds)
                ->where('type', 'retrait_carte_adhesion')
                ->whereNull('account_id')
                ->where('currency', $this->currency);

            $withdrawalQuery = $this->applyPeriodFilter($withdrawalQuery);
            $totalWithdrawals = $withdrawalQuery->sum('amount');

            // 3. Montant restant dans les comptes (Current balance of active cards)
            // On considère les cartes actives du collecteur
            $activeCards = MembershipCard::where('user_id', $collector->id)
                ->where('currency', $this->currency)
                ->where('is_active', true)
                ->with([
                    'contributions' => function ($q) {
                        $q->where('is_paid', true);
                    }
                ])
                ->get();

            $totalRemaining = $activeCards->sum(function ($card) {
                // Total payé - première mise (maison)
                return max(0, $card->contributions->sum('amount') - $card->subscription_amount);
            });

            $data[] = [
                'collector' => $collector,
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'total_remaining' => $totalRemaining,
            ];
        }

        return $data;
    }

    public function showDetails($collectorId)
    {
        $this->selectedCollectorIdForDetails = $collectorId;
        $collector = User::findOrFail($collectorId);
        $this->selectedCollectorName = $collector->name . ' ' . $collector->postnom;

        $this->detailsData = $this->getCollectorDetails($collectorId);

        $this->dispatch('openModal', name: 'modalCollectorDetails');
    }

    public function getCollectorDetails($collectorId)
    {
        $cards = MembershipCard::where('user_id', $collectorId)
            ->where('currency', $this->currency)
            ->with(['member', 'contributions'])
            ->get();

        $data = [];

        foreach ($cards as $card) {
            // Dépôts pour CETTE carte dans la période
            $depositQuery = Transaction::where('membership_card_id', $card->id)
                ->where('type', 'mise_quotidienne')
                ->whereNull('account_id')
                ->where('currency', $this->currency);
            $depositQuery = $this->applyPeriodFilter($depositQuery);
            $totalDeposits = $depositQuery->sum('amount');

            // Retraits pour CETTE carte dans la période
            $withdrawalQuery = Transaction::where('membership_card_id', $card->id)
                ->where('type', 'retrait_carte_adhesion')
                ->whereNull('account_id')
                ->where('currency', $this->currency);
            $withdrawalQuery = $this->applyPeriodFilter($withdrawalQuery);
            $totalWithdrawals = $withdrawalQuery->sum('amount');

            // Solde actuel de la carte (total payé - première mise si active)
            $totalPaid = $card->contributions->where('is_paid', true)->sum('amount');
            $currentBalance = 0;
            if ($card->is_active) {
                $currentBalance = max(0, $totalPaid - $card->subscription_amount);
            }

            $data[] = [
                'card_code' => $card->code,
                'member_name' => $card->member->name . ' ' . $card->member->postnom,
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'current_balance' => $currentBalance,
                'is_active' => $card->is_active,
            ];
        }

        return $data;
    }

    public function closeDetails()
    {
        $this->selectedCollectorIdForDetails = null;
        $this->detailsData = [];
        $this->dispatch('closeModal', name: 'modalCollectorDetails');
    }

    public function exportOverviewPdf()
    {
        $reportData = $this->getReportDataProperty();

        $pdf = Pdf::loadView('pdf.collectors-overview', [
            'reportData' => $reportData,
            'currency' => $this->currency,
            'period' => $this->period,
            'dateStart' => $this->dateStart,
            'dateEnd' => $this->dateEnd,
        ])->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'rapport_general_collecteurs.pdf');
    }

    public function exportDetailsPdf()
    {
        if (!$this->selectedCollectorIdForDetails)
            return;

        $collector = User::findOrFail($this->selectedCollectorIdForDetails);
        $collectorName = $collector->name . ' ' . $collector->postnom;
        $detailsData = $this->getCollectorDetails($this->selectedCollectorIdForDetails);

        $pdf = Pdf::loadView('pdf.collector-details', [
            'collectorName' => $collectorName,
            'detailsData' => $detailsData,
            'currency' => $this->currency,
            'period' => $this->period,
            'dateStart' => $this->dateStart,
            'dateEnd' => $this->dateEnd,
        ])->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'details_collecteur_' . str_replace(' ', '_', $collectorName) . '.pdf');
    }

    private function applyPeriodFilter($query)
    {
        if ($this->period === 'custom' && $this->dateStart && $this->dateEnd) {
            return $query->whereBetween('created_at', [
                Carbon::parse($this->dateStart)->startOfDay(),
                Carbon::parse($this->dateEnd)->endOfDay()
            ]);
        }

        $now = now();
        switch ($this->period) {
            case 'day':
                return $query->whereDate('created_at', $now->toDateString());
            case 'week':
                return $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
            case 'month':
                return $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
            case 'year':
                return $query->whereYear('created_at', $now->year);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.repports.collector-overview-report', [
            'collectors' => $this->collectors,
            'reportData' => $this->reportData,
        ]);
    }
}
