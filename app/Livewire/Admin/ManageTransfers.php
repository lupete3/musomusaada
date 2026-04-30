<?php

namespace App\Livewire\Admin;

use App\Helpers\UserLogHelper;
use App\Models\AgentAccount;
use App\Models\MainCashRegister;
use App\Models\Transaction;
use App\Models\Transfert;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ManageTransfers extends Component
{
    use WithPagination;
    protected $paginationTheme = 'bootstrap';

    public $search = '';
    public $status = 'pending';
    public $rejection_reason = '';
    public $selected_transfer_id;

    public function mount()
    {
        // On réutilise une permission existante ou on en créera une. 
        // 'afficher-caisse-centrale' semble approprié pour ceux qui gèrent la caisse.
        Gate::authorize('afficher-caisse-centrale', User::class);
    }

    public function validateTransfer($id)
    {
        Gate::authorize('afficher-caisse-centrale', User::class);

        DB::beginTransaction();
        try {
            $transfer = Transfert::findOrFail($id);

            if ($transfer->status !== 'pending') {
                notyf()->error('Ce transfert a déjà été traité.');
                return;
            }

            $agentAccount = $transfer->fromAgentAccount;
            $mainCash = $transfer->toMainCashRegister;

            if ($agentAccount->balance < $transfer->amount) {
                notyf()->error("L'agent n'a plus assez de solde pour ce transfert.");
                return;
            }

            // Mise à jour des soldes
            $agentAccount->decrement('balance', $transfer->amount);
            $mainCash->increment('balance', $transfer->amount);

            // Mise à jour du transfert
            $transfer->update([
                'status' => 'validated',
                'validated_by' => Auth::id(),
            ]);

            // Transaction pour l'agent (Sortie)
            Transaction::create([
                'agent_account_id' => $agentAccount->id,
                'user_id' => $agentAccount->user_id,
                'type' => 'virement_caisse_sortant',
                'currency' => $transfer->currency,
                'amount' => $transfer->amount,
                'balance_after' => $agentAccount->balance,
                'description' => "Virement vers caisse centrale validé par ".Auth::user()->name.". #REF".$transfer->id,
            ]);

            // Transaction pour la caisse centrale (Entrée)
            Transaction::create([
                'user_id' => Auth::id(),
                'type' => 'virement vers caisse centrale',
                'currency' => $transfer->currency,
                'amount' => $transfer->amount,
                'balance_after' => $mainCash->balance,
                'description' => "Réception virement de l'agent ".$agentAccount->user->name.". #REF".$transfer->id,
            ]);

            UserLogHelper::log_user_activity(
                action: 'validation_virement_caisse',
                description: "Validation du virement #{$transfer->id} de {$transfer->amount} {$transfer->currency} par ".Auth::user()->name
            );

            DB::commit();
            notyf()->success('Virement validé avec succès !');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            notyf()->error('Une erreur est survenue.');
        }
    }

    public function confirmRejection($id)
    {
        $this->selected_transfer_id = $id;
        $this->dispatch('openModal', name: 'modalRejectionTransfer');
    }

    public function rejectTransfer()
    {
        Gate::authorize('afficher-caisse-centrale', User::class);

        $this->validate([
            'rejection_reason' => 'required|string|min:5'
        ]);

        $transfer = Transfert::findOrFail($this->selected_transfer_id);
        
        if ($transfer->status !== 'pending') {
            notyf()->error('Ce transfert a déjà été traité.');
            return;
        }

        $transfer->update([
            'status' => 'rejected',
            'validated_by' => Auth::id(),
            'rejection_reason' => $this->rejection_reason,
        ]);

        UserLogHelper::log_user_activity(
            action: 'rejet_virement_caisse',
            description: "Rejet du virement #{$transfer->id} pour la raison : {$this->rejection_reason}"
        );

        $this->reset(['rejection_reason', 'selected_transfer_id']);
        $this->dispatch('closeModal', name: 'modalRejectionTransfer');
        notyf()->warning('Virement rejeté.');
    }

    public function render()
    {
        $transfers = Transfert::with(['fromAgentAccount.user', 'toMainCashRegister', 'validator'])
            ->when($this->status, function($query) {
                $query->where('status', $this->status);
            })
            ->when($this->search, function($query) {
                $query->whereHas('fromAgentAccount.user', function($q) {
                    $q->where('name', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.manage-transfers', compact('transfers'));
    }
}
