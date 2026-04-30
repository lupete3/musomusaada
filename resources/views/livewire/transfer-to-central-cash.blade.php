<!-- resources/views/livewire/transfer-to-central-cash.blade.php -->
<div class="mt-0">
    @if (session()->has('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    <h3>Virément caisse centrale</h3>
    <p>Complérer le formulaire pour transférer de l'argent vers la caisse centrale</p>

    <div class="row">
        <div class="col-md-6">
            <div class="card mt-2">
                <div class="card-header bg-secondary text-white">Soldes actuels</div>
                <div class="card-body">
                    <table class="table table-bordered mt-4">
                        <thead>
                            <tr>
                                <th>Devise</th>
                                <th>Votre caisse <small class="text-muted">(Cliquez pour transférer)</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($agentAccounts as $acc)
                                <tr>
                                    <td>{{ $acc->currency }}</td>
                                    <td>
                                        <a href="javascript:void(0)" wire:click="fillForm('{{ $acc->currency }}', {{ $acc->balance }})" class="text-decoration-none">
                                            <strong class="fs-5 text-primary">{{ number_format($acc->balance, 2) }}</strong>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header bg-info text-white">Mes demandes de virement</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Reçu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myTransfers as $trans)
                                    <tr>
                                        <td>{{ $trans->created_at->format('d/m/Y') }}</td>
                                        <td>{{ number_format($trans->amount, 2) }} {{ $trans->currency }}</td>
                                        <td>
                                            @if($trans->status === 'pending')
                                                <span class="badge bg-warning">En attente</span>
                                            @elseif($trans->status === 'validated')
                                                <span class="badge bg-success">Validé</span>
                                                @if($trans->validator)
                                                    <br><small class="text-muted">par {{ $trans->validator->name . ' ' . ' ' . $trans->validator->postnom . ' ' . $trans->updated_at->format('d/m/Y H:i') }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-danger" title="{{ $trans->rejection_reason }}">Rejeté</span>
                                                @if($trans->validator)
                                                    <br><small class="text-muted">par {{ $trans->validator->name . ' ' . ' ' . $trans->validator->postnom . ' ' . $trans->updated_at->format('d/m/Y H:i') }}</small>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if($trans->status === 'validated')
                                                <a href="{{ route('transfer.receipt.generate', $trans->id) }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="Télécharger le reçu">
                                                    <i class="bx bx-download"></i>
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Aucune demande</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mt-2">
                <div class="card-header bg-primary text-white">Virement vers la caisse centrale</div>
                <div class="card-body">
                    <form wire:submit.prevent="submit" class="mt-2">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Devise</label>
                                <select wire:model="currency" class="form-control">
                                    <option value="">Choisir devise</option>
                                    @foreach($currencies as $curr)
                                        <option value="{{ $curr }}">{{ $curr }}</option>
                                    @endforeach
                                </select>
                                @error('currency') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Montant</label>
                                <input type="number" step="0.01" wire:model="amount" class="form-control" />
                                @error('amount') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success float-end">
                                    <span wire:loading class="spinner-border spinner-border-sm me-2"
                                    role="status"></span>
                                    Valider le virement</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
