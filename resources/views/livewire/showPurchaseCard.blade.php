<!-- Modal de modification -->
@if ($detailsModal)
    <div class="modal fade show d-block " tabindex="-1" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de la carte: {{ optional($card)->code ?? '' }}</h5>
                    <button type="button" class="btn-close" wire:click="$set('detailsModal', false)"></button>
                </div>
                <div class="modal-body">
                    @if ($card)
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Membre:</strong> {{ optional($detailsCard->member)->code ?? 'N/A' }}
                                    {{ optional($detailsCard->member)->name ?? 'N/A' }}
                                    {{ optional($detailsCard->member)->postnom ?? 'N/A' }}
                                    {{ optional($detailsCard->member)->prenom ?? 'N/A' }}</p>
                                <p><strong>Prix du carnet:</strong> {{ number_format($detailsCard->price, 2) }}
                                    {{ $detailsCard->currency }}</p>
                                <p><strong>Montant quotidien:</strong> {{ number_format($detailsCard->subscription_amount, 2) }}
                                    {{ $detailsCard->currency }}</p>
                                <p><strong>Date de début:</strong>
                                    {{ \Carbon\Carbon::parse($detailsCard->start_date)->format('d/m/Y') }}</p>
                                <p><strong>Date de fin:</strong>
                                    {{ \Carbon\Carbon::parse($detailsCard->end_date)->format('d/m/Y') }}</p>
                                <p><strong>Agent:</strong>
                                    {{ optional($detailsCard->agent)->name . ' ' . optional($detailsCard->agent)->postnom . ' ' . optional($detailsCard->agent)->prenom ?? 'N/A' }}
                                </p>
                                <p><strong>Status:</strong>
                                    @if ($detailsCard->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Terminée</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total épargné:</strong> {{ number_format($detailsCard->total_saved, 2) }}
                                    {{ $detailsCard->currency }}</p>
                                <p><strong>Total restant:</strong> {{ number_format($detailsCard->total_remaining, 2) }}
                                    {{ $detailsCard->currency }}</p>
                                <p><strong>Jours payés:</strong>
                                    {{ 31 - $detailsCard->getUnpaidContributionsAttribute()->count() }} / 31</p>
                                <p><strong>Jours restants:</strong>
                                    {{ $detailsCard->getUnpaidContributionsAttribute()->count() }} / 31</p>
                                <p><strong>Solde:</strong> {{ number_format($detailsCard->balance, 2) }}
                                    {{ $detailsCard->currency }}</p>
                            </div>

                        <div class="card-body">
                            <p><strong>Historique des contributions:</strong></p>

                            @forelse ($detailsCard->contributions->where('is_paid', '=', 1) as $contribution)
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <div class="d-flex justify-content-between border-bottom py-2">
                                        <div>
                                            <strong>{{ number_format($contribution->amount, 2) }}
                                                {{ $detailsCard->currency }}</strong><br>
                                        </div>
                                        <div>
                                            <span class="badge bg-light text-dark">
                                                {{ \Carbon\Carbon::parse($contribution->updated_at)->format('d/m/Y') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mt-3">Aucune transaction trouvée.</p>
                            @endforelse
                        </div>
                    @else
                        <p>Détails de la carte non disponibles.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
