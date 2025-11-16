<div>
    <!-- FILTRES -->
    <div class="card mb-4">
        <div class="card-body">

            <div class="row g-3">

                <!-- Agent -->
                <div class="col-md-3">
                    <label class="form-label">Agent</label>
                    <select wire:model.lazy="agent_id" class="form-select">
                        <option value="">Tous</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }} {{ $agent->postnom }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Type -->
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select wire:model.lazy="type" class="form-select">
                        <option value="">Tous</option>
                        <option value="carte">Ventes Carnets</option>
                        <option value="carnet">Commissions Carnets</option>
                    </select>
                </div>

                <!-- Mois -->
                <div class="col-md-2">
                    <label class="form-label">Mois</label>
                    <select wire:model.lazy="month" class="form-select">
                        <option value="">Tous</option>
                        @for($m=1; $m<=12; $m++)
                            <option value="{{ $m }}">{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Année -->
                <div class="col-md-2">
                    <label class="form-label">Année</label>
                    <select wire:model.lazy="year" class="form-select">
                        <option value="">Toutes</option>
                        @for($y=2023; $y<=now()->year; $y++)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Date start -->
                <div class="col-md-3">
                    <label class="form-label">Date début</label>
                    <input type="date" wire:model.lazy="date_start" class="form-control">
                </div>

                <!-- Date end -->
                <div class="col-md-3">
                    <label class="form-label">Date fin</label>
                    <input type="date" wire:model.lazy="date_end" class="form-control">
                </div>

            </div>
        </div>
    </div>

    <!-- ======================================= -->
    <!-- 🔥 SYNTHÈSE DES AGENTS -->
    <!-- ======================================= -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Synthèse des commissions par agent</h5>
            <button wire:click="exportPdf" class="btn btn-info" wire:loading.attr="disabled">
                <span wire:loading class="spinner-border spinner-border-sm me-2" role="status"></span>
                Exporter PDF
            </button>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Agent</th>
                        <th>Total carnets vendus</th>
                        <th>Total commissions carnets</th>
                        <th>Total général</th>
                        <th>Part Agent (40%)</th>
                        <th>Part Bureau (60%)</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($synthese as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row['agent']->name }} {{ $row['agent']->postnom }}</td>
                            <td>{{ number_format($row['total_carnets'], 2) }}</td>
                            <td>{{ number_format($row['total_commissions'], 2) }}</td>
                            <td><strong>{{ number_format($row['total_general'], 2) }}</strong></td>
                            <td class="text-success fw-bold">{{ number_format($row['agent_part'], 2) }}</td>
                            <td class="text-danger fw-bold">{{ number_format($row['bureau_part'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot class="table-light">
                    <tr>
                        <th colspan="2">TOTAL GÉNÉRAL</th>
                        <th>{{ number_format($footerTotals['total_carnets'], 2) }}</th>
                        <th>{{ number_format($footerTotals['total_commissions'], 2) }}</th>
                        <th>{{ number_format($footerTotals['total_general'], 2) }}</th>
                        <th>{{ number_format($footerTotals['agent_part'], 2) }}</th>
                        <th>{{ number_format($footerTotals['bureau_part'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Commissions des agents</h5>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Agent</th>
                        <th>Type</th>
                        <th>Montant</th>
                        <th>Membre concerné</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>
                @forelse ($commissions as $index => $c)
                    <tr>
                        <td>{{ $commissions->firstItem() + $index }}</td>
                        <td>{{ $c->agent->name }} {{ $c->agent->postnom }}</td>
                        <td>
                            <span class="badge bg-info">
                                {{ ucfirst(($c->type == 'carte') ? 'ventes carnet' : 'commission carnet') }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ number_format($c->amount,2) }}</strong>
                        </td>
                        <td>
                            @if($c->member)
                                {{ $c->member->name }} {{ $c->member->postnom }} {{ $c->member->prenom }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $c->commission_date }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Aucune donnée trouvée.</td>
                    </tr>
                @endforelse
                </tbody>

                <tfoot class="table-light">
                    <tr>
                        <th colspan="3">Total carnets vendus</th>
                        <th>{{ number_format($totalCarte,2) }}</th>
                        <th colspan="2"></th>
                    </tr>
                    <tr>
                        <th colspan="3">Total commissions carnets</th>
                        <th>{{ number_format($totalCarnet,2) }}</th>
                        <th colspan="2"></th>
                    </tr>
                    <tr class="table-primary">
                        <th colspan="3">TOTAL GÉNÉRAL</th>
                        <th>{{ number_format($totalGeneral,2) }}</th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card-footer">
            {{ $commissions->links() }}
        </div>
    </div>
</div>
