<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\AgentCommission;
use Barryvdh\DomPDF\Facade\Pdf;

class AgentCommissionOverview extends Component
{
    use WithPagination;

    public $agent_id;
    public $type;
    public $month;
    public $year;
    public $date_start;
    public $date_end;

    protected $paginationTheme = 'bootstrap';

    public function updating($field)
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = AgentCommission::with('agent')
            ->when($this->agent_id, fn($q) => $q->where('agent_id', $this->agent_id))
            ->when($this->type, fn($q) => $q->where('type', $this->type))
            ->when($this->month, fn($q) => $q->whereMonth('commission_date', $this->month))
            ->when($this->year, fn($q) => $q->whereYear('commission_date', $this->year))
            ->when($this->date_start && $this->date_end, fn($q) =>
                $q->whereBetween('commission_date', [$this->date_start, $this->date_end])
            );

        // Totaux footer
        $totalCarte = (clone $query)->where('type', 'carte')->sum('amount');
        $totalCarnet = (clone $query)->where('type', 'carnet')->sum('amount');
        $totalGeneral = $totalCarte + $totalCarnet;

        $commissions = $query->orderBy('commission_date', 'desc')->paginate(15);

        if($this->agent_id) {
            $agents = User::where('id', $this->agent_id)->get();
        } else {
            $agents = User::where('role', '!=', 'membre')->get();
        }

        // ======================================
        // 🔥 SYNTHÈSE PAR AGENT
        // ======================================
        $synthese = [];

        foreach ($agents as $agent) {

            // Filtrer leurs commissions dans le contexte des filtres appliqués
            $agentQuery = (clone $query)->where('agent_id', $agent->id);

            $totalCartesVendus = (clone $agentQuery)->where('type', 'carte')->sum('amount');
            $commissionCarnets = (clone $agentQuery)->where('type', 'carnet')->sum('amount');
            $totalGeneralAgent = $totalCartesVendus + $commissionCarnets;

            $agentPart = $commissionCarnets * 0.40;
            $bureauPart = $commissionCarnets * 0.60;

            $synthese[] = [
                'agent' => $agent,
                'total_carnets' => $totalCartesVendus,
                'total_commissions' => $commissionCarnets,
                'total_general' => $totalGeneralAgent,
                'agent_part' => $agentPart,
                'bureau_part' => $bureauPart,
            ];
        }

        // 🔥 Totaux synthèse
        $footerTotals = [
            'total_carnets'      => collect($synthese)->sum('total_carnets'),
            'total_commissions'  => collect($synthese)->sum('total_commissions'),
            'total_general'      => collect($synthese)->sum('total_general'),
            'agent_part'         => collect($synthese)->sum('agent_part'),
            'bureau_part'        => collect($synthese)->sum('bureau_part'),
        ];

        return view('livewire.agent-commission-overview', [
            'agents' => $agents,
            'commissions' => $commissions,
            'totalCarte' => $totalCarte,
            'totalCarnet' => $totalCarnet,
            'totalGeneral' => $totalGeneral,
            'synthese' => $synthese,
            'footerTotals' => $footerTotals,
        ]);
    }


    public function exportPdf()
    {
        $query = AgentCommission::with('agent','member')
            ->when($this->agent_id, fn($q) => $q->where('agent_id', $this->agent_id))
            ->when($this->type, fn($q) => $q->where('type', $this->type))
            ->when($this->month, fn($q) => $q->whereMonth('commission_date', $this->month))
            ->when($this->year, fn($q) => $q->whereYear('commission_date', $this->year))
            ->when($this->date_start && $this->date_end, fn($q) =>
                $q->whereBetween('commission_date', [$this->date_start, $this->date_end])
            );

        $commissions = $query->orderBy('commission_date', 'desc')->get();

        if($this->agent_id) {
            $agents = User::where('id', $this->agent_id)->get();
        } else {
            $agents = User::where('role', '!=', 'membre')->get();
        }

        // Totaux pour les cartes du haut
        $totalCarte = (clone $query)->where('type','carte')->sum('amount');
        $totalCarnet = (clone $query)->where('type','carnet')->sum('amount');
        $totalGeneral = $totalCarte + $totalCarnet;

        // Synthèse par agent
        $synthese = [];
        foreach ($agents as $agent) {
            $agentQuery = (clone $query)->where('agent_id', $agent->id);
            $totalCartesVendus = (clone $agentQuery)->where('type','carte')->sum('amount');
            $commissionCarnets = (clone $agentQuery)->where('type','carnet')->sum('amount');
            $totalGeneralAgent = $totalCartesVendus + $commissionCarnets;

            $agentPart = $commissionCarnets * 0.40;
            $bureauPart = $commissionCarnets * 0.60;

            $synthese[] = [
                'agent' => $agent,
                'total_carnets' => $totalCartesVendus,
                'total_commissions' => $commissionCarnets,
                'total_general' => $totalGeneralAgent,
                'agent_part' => $agentPart,
                'bureau_part' => $bureauPart,
            ];
        }

        $footerTotals = [
            'total_carnets' => collect($synthese)->sum('total_carnets'),
            'total_commissions' => collect($synthese)->sum('total_commissions'),
            'total_general' => collect($synthese)->sum('total_general'),
            'agent_part' => collect($synthese)->sum('agent_part'),
            'bureau_part' => collect($synthese)->sum('bureau_part'),
        ];

        // Génération PDF avec la vue
        $pdf = Pdf::loadView('pdf.agent_commission', [
            'totalCarte' => $totalCarte,
            'totalCarnet' => $totalCarnet,
            'totalGeneral' => $totalGeneral,
            'commissions' => $commissions,
            'synthese' => $synthese,
            'footerTotals' => $footerTotals,
            'agents' => $agents,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end,
            'month' => $this->month,
            'year' => $this->year,
        ]);

        return response()->streamDownload(
            fn() => print($pdf->output()),
            'commissions_agents_'.now()->format('Y_m_d_H_i').'.pdf'
        );
    }


}
