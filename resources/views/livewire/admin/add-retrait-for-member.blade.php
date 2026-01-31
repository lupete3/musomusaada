<!-- Modal -->
<div class="modal fade" id="modalRetraitMembre" tabindex="-1" aria-labelledby="modalRetraitMembreLabel"
    aria-hidden="true" data-focus="false" wire:ignore.self>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="modalRetraitMembreLabel">{{ __("Effectuer un retrait") }}</h5>
                <button type="button" class="btn-close" aria-label="Close" wire:click='closeRetraitModal'></button>
            </div>

            {{-- <div class="modal-body row">
                <div class="col-md-12">
                    <select name="type" wire:model.lazy='type' class="form-control">
                        <option value="">Choisir type d'operation</option>
                        <option value="carte">Carte</option>
                        <option value="normal">Normal</option>
                    </select>
                </div>
            </div> --}}

            @if (!$confirming)
                @if ($operation_type == 'normal')
                    <form wire:submit.prevent="showConfirm">
                        <div class="modal-body row">
                            <div class="col-md-6 mb-3">
                                <label>Devise</label>
                                <select wire:model="currency" class="form-control">
                                    <option value="">Choisir devise</option>
                                    <option value="USD">USD</option>
                                    <option value="CDF">CDF</option>
                                </select>
                                @error('currency') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Montant</label>
                                <input type="number" step="0.01" wire:model="amount" class="form-control" />
                                @error('amount') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Montant à retenir</label>
                                <input type="number" step="0.01" wire:model="a_retenir" class="form-control" />
                                @error('a_retenir') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click='closeRetraitModal'>{{ __('Fermer') }}</button>
                            <button type="submit" class="btn btn-primary">
                                {{ __('Vérifier') }}
                            </button>
                        </div>
                    </form>
                @endif

                @if ($operation_type == 'carte')
                    <form wire:submit.prevent="showConfirm">
                        <div class="modal-body row">
                            <div class="col-md-12 mb-3">
                                <label>Choisir une carte</label>
                                <select wire:model.live="card_id" class="form-control">
                                    <option value="">Sélectionner une carte</option>
                                    @foreach ($activeCards as $card)
                                        <option value="{{ $card->id }}">
                                            Carte #{{ $card->code }} | {{ $card->currency }} - Total épargné :
                                            {{ number_format($card->total_saved, 2) }}
                                            (Fin: {{ \Carbon\Carbon::parse($card->end_date)->format('d/m/Y') }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('card_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click='closeRetraitModal'>{{ __('Fermer') }}</button>
                            <button type="submit" class="btn btn-primary">
                                {{ __('Vérifier') }}
                            </button>
                        </div>
                    </form>
                @endif
            @else
                {{-- Écran de Récapitulatif --}}
                <div class="modal-body">
                    <div class="alert alert-danger border-danger" style="background-color: #fff5f5;">
                        <h6 class="alert-heading font-bold mb-3 text-danger-700">Récapitulatif du Retrait</h6>
                        <div class="space-y-3">
                            <div class="d-flex justify-content-between border-bottom pb-2">
                                <span class="text-muted small text-uppercase">Type :</span>
                                <span class="font-weight-bold">{{ $operation_type == 'normal' ? 'Retrait Simple' : 'Clôture de Carnet' }}</span>
                            </div>
                            
                            @if($operation_type == 'normal')
                                <div class="d-flex justify-content-between border-bottom pb-2">
                                    <span class="text-muted small text-uppercase">Montant Brut :</span>
                                    <span class="font-weight-bold">{{ number_format($amount, 2) }} {{ $currency }}</span>
                                </div>
                                <div class="d-flex justify-content-between border-bottom pb-2 text-danger">
                                    <span class="text-muted small text-uppercase">Frais (À retenir) :</span>
                                    <span class="font-weight-bold">- {{ number_format($a_retenir, 2) }} {{ $currency }}</span>
                                </div>
                                <div class="d-flex justify-content-between pt-2">
                                    <span class="font-weight-bold text-uppercase">Net à payer :</span>
                                    <span class="font-weight-bold text-lg text-primary">{{ number_format($amount, 2) }} {{ $currency }}</span>
                                </div>
                                <div class="alert alert-info py-1 px-2 mt-2 small">
                                    Note: Le client recevra {{ number_format($amount, 2) }} {{ $currency }}, mais {{ number_format($amount + $a_retenir, 2) }} {{ $currency }} seront déduits de son solde.
                                </div>
                            @else
                                @if($selectedCard)
                                    <div class="d-flex justify-content-between border-bottom pb-2">
                                        <span class="text-muted small text-uppercase">Carnet :</span>
                                        <span class="font-weight-bold">#{{ $selectedCard->code }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-2">
                                        <span class="text-muted small text-uppercase">Total Épargné :</span>
                                        <span class="font-weight-bold text-success">{{ number_format($selectedCard->total_saved, 2) }} {{ $selectedCard->currency }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-2 text-danger">
                                        <span class="text-muted small text-uppercase">Frais (1 Mise) :</span>
                                        <span class="font-weight-bold text-danger">- {{ number_format($selectedCard->subscription_amount, 2) }} {{ $selectedCard->currency }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between pt-2">
                                        <span class="font-weight-bold text-uppercase text-primary">Net à verser au compte :</span>
                                        <span class="font-weight-bold text-lg text-primary">{{ number_format($selectedCard->total_saved - $selectedCard->subscription_amount, 2) }} {{ $selectedCard->currency }}</span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" wire:click="cancelConfirmation">
                         Modifier
                    </button>
                    <button type="button" class="btn btn-primary px-4" wire:click="{{ $operation_type == 'normal' ? 'submitRetrait' : 'submitRetraitCarte' }}" wire:loading.attr="disabled">
                        <span wire:loading class="spinner-border spinner-border-sm me-2" role="status"></span>
                        Confirmer le Retrait
                    </button>
                </div>
            @endif


        </div>

    </div>
</div>


<!-- Table des adhésions (inchangée) -->