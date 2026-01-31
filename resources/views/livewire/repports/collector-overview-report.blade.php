<div>
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Aperçu des rapports des agents collecteurs</h5>
                    <button class="btn btn-danger btn-sm" wire:click="exportOverviewPdf" wire:loading.attr="disabled">
                        <i class="bx bxs-file-pdf me-1"></i> Exporter PDF
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Collecteur</label>
                            <select class="form-select" wire:model.live="collectorId">
                                <option value="">Tous les collecteurs</option>
                                @foreach($collectors as $collector)
                                    <option value="{{ $collector->id }}">{{ $collector->name }} {{ $collector->postnom }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Devise</label>
                            <select class="form-select" wire:model.live="currency">
                                <option value="CDF">CDF</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Période</label>
                            <select class="form-select" wire:model.live="period">
                                <option value="day">Aujourd'hui</option>
                                <option value="week">Cette semaine</option>
                                <option value="month">Ce mois</option>
                                <option value="year">Cette année</option>
                                <option value="custom">Personnalisé</option>
                            </select>
                        </div>
                        @if($period === 'custom')
                            <div class="col-md-2">
                                <label class="form-label">Date Début</label>
                                <input type="date" class="form-control" wire:model.live="dateStart">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date Fin</label>
                                <input type="date" class="form-control" wire:model.live="dateEnd">
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Collecteur</th>
                                    <th class="text-end">Total Dépôts</th>
                                    <th class="text-end">Total Retraits</th>
                                    <th class="text-end">Reste en Compte</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="table-border-bottom-0">
                                @forelse($reportData as $row)
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $row['collector']->name }}
                                                {{ $row['collector']->postnom }}</span><br>
                                            <small class="text-muted">{{ $row['collector']->code }}</small>
                                        </td>
                                        <td class="text-end text-success fw-semibold">
                                            {{ number_format($row['total_deposits'], 2) }} {{ $currency }}
                                        </td>
                                        <td class="text-end text-danger fw-semibold">
                                            {{ number_format($row['total_withdrawals'], 2) }} {{ $currency }}
                                        </td>
                                        <td class="text-end text-primary fw-bold">
                                            {{ number_format($row['total_remaining'], 2) }} {{ $currency }}
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary"
                                                wire:click="showDetails({{ $row['collector']->id }})">
                                                <i class="bx bx-show me-1"></i> Détails
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="bx bx-info-circle fs-4 mb-2"></i><br>
                                            Aucune donnée trouvée pour cette période.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($reportData) > 0)
                                <tfoot class="table-light">
                                    <tr>
                                        <th class="fw-bold">TOTAL</th>
                                        <th class="text-end text-success fw-bold">
                                            {{ number_format(collect($reportData)->sum('total_deposits'), 2) }}
                                            {{ $currency }}
                                        </th>
                                        <th class="text-end text-danger fw-bold">
                                            {{ number_format(collect($reportData)->sum('total_withdrawals'), 2) }}
                                            {{ $currency }}
                                        </th>
                                        <th class="text-end text-primary fw-bold">
                                            {{ number_format(collect($reportData)->sum('total_remaining'), 2) }}
                                            {{ $currency }}
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Détails -->
    <div wire:ignore.self class="modal fade" id="modalCollectorDetails" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails pour {{ $selectedCollectorName }}</h5>
                    <button type="button" class="btn-close" wire:click="closeDetails" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bx bx-info-circle me-1"></i>
                        Période : <strong>{{ $period === 'custom' ? "$dateStart à $dateEnd" : $period }}</strong> |
                        Devise : <strong>{{ $currency }}</strong>
                    </div>
                    <div class="table-responsive text-nowrap">
                        <table class="table table-sm table-hover">
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
                                        <td>
                                            @if($detail['is_active'])
                                                <span class="badge bg-label-success">Actif</span>
                                            @else
                                                <span class="badge bg-label-secondary">Clôturé</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-success">{{ number_format($detail['total_deposits'], 2) }}
                                        </td>
                                        <td class="text-end text-danger">
                                            {{ number_format($detail['total_withdrawals'], 2) }}
                                        </td>
                                        <td
                                            class="text-end fw-bold {{ $detail['current_balance'] > 0 ? 'text-primary' : '' }}">
                                            {{ number_format($detail['current_balance'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="3">TOTAUX</td>
                                    <td class="text-end text-success">
                                        {{ number_format(collect($detailsData)->sum('total_deposits'), 2) }}
                                    </td>
                                    <td class="text-end text-danger">
                                        {{ number_format(collect($detailsData)->sum('total_withdrawals'), 2) }}
                                    </td>
                                    <td class="text-end text-primary">
                                        {{ number_format(collect($detailsData)->sum('current_balance'), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" wire:click="exportDetailsPdf"
                        wire:loading.attr="disabled">
                        <i class="bx bxs-file-pdf me-1"></i> Exporter PDF
                    </button>
                    <button type="button" class="btn btn-secondary" wire:click="closeDetails">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>