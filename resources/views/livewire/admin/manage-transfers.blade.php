<div>
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a class="mb-0 d-inline-block fs-6 lh-1" href="{{ route('dashboard') }}">{{ __('Tableau de bord') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                <h1 class="mb-0 d-inline-block fs-6 lh-1">{{ __('Validation des virements agents') }}</h1>
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h5 class="card-title mb-0">Liste des demandes de virement</h5>
                        <div class="d-flex align-items-center gap-2">
                            <select wire:model.live="status" class="form-select">
                                <option value="pending">En attente</option>
                                <option value="validated">Validés</option>
                                <option value="rejected">Rejetés</option>
                                <option value="">Tous</option>
                            </select>
                            <input type="text" wire:model.live="search" class="form-control" placeholder="Rechercher agent...">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agent</th>
                                <th>Montant</th>
                                <th>Devise</th>
                                <th>Statut</th>
                                <th>Traité par</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold">{{ $transfer->fromAgentAccount->user->name }}</span>
                                            <small class="text-muted">Solde actuel: {{ number_format($transfer->fromAgentAccount->balance, 2) }} {{ $transfer->currency }}</small>
                                        </div>
                                    </td>
                                    <td><span class="fw-bold">{{ number_format($transfer->amount, 2) }}</span></td>
                                    <td>{{ $transfer->currency }}</td>
                                    <td>
                                        @if($transfer->status === 'pending')
                                            <span class="badge bg-warning">En attente</span>
                                        @elseif($transfer->status === 'validated')
                                            <span class="badge bg-success">Validé</span>
                                        @else
                                            <span class="badge bg-danger" title="{{ $transfer->rejection_reason }}">Rejeté</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($transfer->validator)
                                            {{ $transfer->validator->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($transfer->status === 'pending')
                                            <button wire:click="validateTransfer({{ $transfer->id }})" 
                                                wire:confirm="Voulez-vous vraiment valider ce virement ?"
                                                class="btn btn-sm btn-success">
                                                Valider
                                            </button>
                                            <button wire:click="confirmRejection({{ $transfer->id }})" 
                                                class="btn btn-sm btn-danger">
                                                Rejeter
                                            </button>
                                        @elseif($transfer->status === 'validated')
                                            <a href="{{ route('transfer.receipt.generate', $transfer->id) }}" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="bx bx-printer"></i> Reçu
                                            </a>
                                        @else
                                            <span class="text-muted">Traité</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">Aucun virement trouvé.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $transfers->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Rejection -->
    <div wire:ignore.self class="modal fade" id="modalRejectionTransfer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rejeter le virement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Raison du rejet</label>
                        <textarea wire:model="rejection_reason" class="form-control" rows="3" placeholder="Expliquez pourquoi le virement est rejeté..."></textarea>
                        @error('rejection_reason') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" wire:click="rejectTransfer" class="btn btn-danger">Confirmer le rejet</button>
                </div>
            </div>
        </div>
    </div>
</div>
