<!-- Modal -->
<div class="modal fade" id="modalMembre" tabindex="-1" aria-labelledby="modalMembreLabel" aria-hidden="true"
    data-focus="false" wire:ignore.self>
    <div class="modal-dialog modal-dialog-centered modal-mb">
        <div class="modal-content">
            <form wire:submit.prevent="{{ $editModal ? 'update' : 'submitForm' }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalMembreLabel">{{ __("Information du client") }}
                    </h5>
                    <button type="button" class="btn-close" aria-label="Close" wire:click='closeModal'></button>
                </div>
                <div class="modal-body">
                        {{-- Create Form (Multi-step) --}}
                        <div class="mt-3">
                            <div class="@if ($currentStep != 1) d-none @endif">
                                <div class="row g-3">
                                    <!-- Nom -->
                                    <div class="col-md-6 mb-1">
                                        <label for="name" class="form-label">Nom</label>
                                        <input type="text" wire:model.lazy="name" id="name" class="form-control"
                                            placeholder="Nom" required autofocus />
                                        @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Postnom -->
                                    <div class="col-md-6 mb-1">
                                        <label for="postnom" class="form-label">Postnom</label>
                                        <input type="text" wire:model.live="postnom" id="postnom" class="form-control"
                                            placeholder="Postnom" required />
                                        @error('postnom') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Email -->
                                    <div class="col-md-12 mb-1">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" wire:model="email" id="email" class="form-control"
                                            placeholder="exemple@domaine.com" required readonly />
                                        @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>

                                </div>
                            </div>
                        </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click='closeModal'>{{ __('Fermer') }}</button>
                    @if (!$editModal)
                        <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                            <span wire:loading class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Enregistrer
                        </button>
                    @else
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading class="spinner-border spinner-border-sm me-2" role="status"></span>
                            {{ __('Mettre à jour') }}
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Table des adhésions (inchangée) -->
