<?php

namespace App\Livewire;

// app/Http/Livewire/TransferToCentralCash.php

use App\Helpers\UserLogHelper;
use Livewire\Component;
use App\Models\AgentAccount;
use App\Models\MainCashRegister;
use App\Models\Transaction;
use App\Models\Transfert;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TransferToCentralCash extends Component
{
    public $currency;
    public $amount = 0;
    public $currencies = ['USD', 'CDF'];

    protected $rules = [
        'currency' => 'required|in:USD,CDF',
        'amount' => 'required|numeric|min:0.01',
    ];

    public function fillForm($currency, $amount)
    {
        $this->currency = $currency;
        $this->amount = $amount;
    }

    public function mount()
    {
        Gate::authorize('ajouter-transfert-caisse', User::class);
    }

    public function submit()
    {
        $this->validate();

        // Récupérer la caisse de l'agent
        $agentAccount = AgentAccount::firstOrCreate(
            ['user_id' => Auth::id(), 'currency' => $this->currency],
            ['balance' => 0]
        );

        if ($agentAccount->balance < $this->amount) {
            $this->addError('amount', "Solde insuffisant dans votre caisse.");
            return;
        }

        // Récupérer ou créer la caisse centrale
        $mainCash = MainCashRegister::firstOrCreate(
            ['currency' => $this->currency],
            ['balance' => 0]
        );

        // Enregistrer la demande de virement (status pending par défaut)
        $transfer = Transfert::create([
            'from_agent_account_id' => $agentAccount->id,
            'to_main_cash_register_id' => $mainCash->id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'status' => 'pending',
        ]);

        // On peut enregistrer une transaction de type 'demande_virement' pour le suivi
        Transaction::create([
            'agent_account_id' => $agentAccount->id,
            'user_id' => Auth::id(),
            'type' => 'demande virement',
            'currency' => $this->currency,
            'amount' => $this->amount,
            'balance_after' => $agentAccount->balance, // Le solde ne change pas encore
            'description' => "Demande de virement de ".$this->amount." ".$this->currency." vers la caisse centrale. En attente de validation. #REF".$transfer->id,
        ]);

        UserLogHelper::log_user_activity(
            action: 'demande_virement_caisse',
            description: "Demande de virement de {$this->amount} {$this->currency} vers la caisse centrale. #REF{$transfer->id}"
        );

        notyf()->success('Demande de virement envoyée avec succès ! En attente de validation.');

        $this->reset(['amount']);
        // $this->redirect(route('transfer.receipt.generate', ['id' => $transfer->id]), navigate: false);
    }

    public function placeholder()
    {
        return view('livewire.placeholder');
    }

    public function render()
    {
        $agentAccounts = AgentAccount::where('user_id', Auth::id())->get();
        $myTransfers = Transfert::with('validator')->whereHas('fromAgentAccount', function($q) {
            $q->where('user_id', Auth::id());
        })->latest()->take(10)->get();

        return view('livewire.transfer-to-central-cash', compact('agentAccounts', 'myTransfers'));
    }
}
