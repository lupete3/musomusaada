<?php

namespace App\Livewire;

use App\Helpers\UserLogHelper;
use Livewire\Component;
use App\Models\User;
use App\Models\Account;
use App\Models\AgentAccount;
use App\Models\MainCashRegister;
use App\Models\MembershipCard;
use App\Models\Transaction;
use App\Models\AgentCommission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;

class PurchaseMembershipCard extends Component
{
    use WithPagination;
    protected $paginationTheme = 'bootstrap';

    public $perPage = 10;
    public $search = '';
    public $searchCard;
    public $member_id;
    public $currency = 'CDF';
    public $price = 500;
    public $subscription_amount = 0;
    public $code;

    public $members = [];
    public $results = [];

    public $agent_id;
    public $agents;

    public $showConfirmationModal = false;
    public $selectedMemberName;

    public $editModal = false;
    public $editCardId;
    public $edit_code;
    public $edit_currency;
    public $edit_price;
    public $edit_subscription_amount;
    public $edit_agent_id;

    public $detailsModal = false;
    public $detailsCard;

    protected $rules = [
        'member_id' => 'required|exists:users,id',
        'currency' => 'required|in:USD,CDF',
        'price' => 'required|numeric|min:0.01',
        'subscription_amount' => 'required|numeric|min:0.01',
    ];

    public function mount()
    {
        Gate::authorize('afficher-carnet', User::class);

        $this->members = User::where('role', 'membre')->get();
        $this->agents = User::where('role', '!=','membre')->get();

        $this->agent_id = Auth::id(); // Agent connecté
    }

    public function updatedSearch()
    {
        $query = trim($this->search);

        if ($query !== '') {
            $terms = preg_split('/\s+/', $query);

            $users = User::where('role', 'membre')
                ->where(function ($mainQuery) use ($terms) {
                    foreach ($terms as $term) {
                        $mainQuery->where(function ($q) use ($term) {
                            $q->where('code', 'like', "%{$term}%")
                                ->orWhere('name', 'like', "%{$term}%")
                                ->orWhere('postnom', 'like', "%{$term}%")
                                ->orWhere('prenom', 'like', "%{$term}%")
                                ->orWhere('telephone', 'like', "%{$term}%");
                        });
                    }
                })
                ->limit(10)
                ->get(['id', 'code', 'name', 'postnom', 'prenom'])
                ->toArray();

            $this->results = $users;
        } else {
            $this->results = [];
        }
    }

    public function selectResult(int $id)
    {
        $user = User::find($id);
        if ($user) {
            $this->search = "{$user->name} {$user->postnom}";
            $this->results = [];

            $this->member_id = $user->id;
            $this->dispatch('userSelected', $user->id);
        }
    }


    public function submit()
    {
        Gate::authorize('ajouter-carnet', User::class);

        $this->validate();

        DB::beginTransaction();
        try {
            // Récupération du membre
            $member = User::findOrFail($this->member_id);

            // Dates
            $startDate = now();
            $endDate = $startDate->copy()->addDays(30);

            // Génération du code unique
            $this->code = $this->generateCardCode();

            // Création du carnet
            $card = MembershipCard::create([
                'code' => $this->code,
                'member_id' => $member->id,
                'currency' => $this->currency,
                'price' => $this->price,
                'subscription_amount' => $this->subscription_amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => true,
                'user_id' => Auth::id(), // agent vendeur
            ]);

            // Génération automatique des 31 mises
            for ($i = 0; $i < 31; $i++) {
                $card->contributions()->create([
                    'membership_card_id' => $card->id,
                    'contribution_date' => $startDate->copy()->addDays($i),
                    'amount' => $this->subscription_amount,
                    'is_paid' => false,
                ]);
            }

            /**
             * 📌 CREDIT CAISSE PRINCIPALE : le prix du carnet
             */
            $cash = MainCashRegister::firstOrCreate(
                ['currency' => $this->currency],
                ['balance' => 0]
            );
            $cash->increment('balance', $this->price);

            Transaction::create([
                'account_id' => null,
                'user_id' => Auth::id(),
                'type' => 'vente_carte_adhesion',
                'currency' => $this->currency,
                'amount' => $this->price,
                'balance_after' => $cash->balance,
                'description' => "Vente de carte à {$member->name} - Montant: {$this->price} {$this->currency}",
            ]);

            /**
             * 📌 COMMISSION AGENT : vente de carte
             */
            AgentCommission::create([
                'agent_id' => Auth::id(),
                'type' => 'carte',
                'amount' => $this->price, // ou montant fixe = 500
                'member_id' => $member->id,
                'commission_date' => now(),
            ]);

            // Crédite aussi le compte agent pour traçabilité
            $agentAccount = AgentAccount::firstOrCreate(
                ['user_id' => Auth::id(), 'currency' => $this->currency],
                ['balance' => 0]
            );
            $agentAccount->increment('balance', $this->price);

            Transaction::create([
                'agent_account_id' => $agentAccount->id,
                'user_id' => Auth::id(),
                'type' => 'commission_carte',
                'currency' => $this->currency,
                'amount' => $this->price,
                'balance_after' => $agentAccount->balance,
                'description' => "Commission vente carte pour {$member->name}",
            ]);

            UserLogHelper::log_user_activity(
                action: 'achat_carte_adhesion',
                description: "Achat de la carte #{$card->code} pour {$member->name} {$member->postnom} ({$member->code}), montant {$this->price} {$this->currency}"
            );

            DB::commit();

            $this->reset(['code','member_id','currency','price','subscription_amount']);
            $this->dispatch('$refresh');
            $this->resetPage();
            notyf()->success('Carte achetée avec succès !');
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e->getMessage());
            notyf()->error("Une erreur est survenue.");
        }
    }



    public function render()
    {
        $cards = MembershipCard::with('member')
            ->when($this->searchCard, function ($query) {
                $terms = explode(' ', $this->searchCard);

                $query->where(function ($mainQuery) use ($terms) {
                    foreach ($terms as $term) {
                        $mainQuery->where(function ($q) use ($term) {
                            $q->where('code', 'like', "%{$term}%")
                                ->orWhereHas('member', function ($sub) use ($term) {
                                    $sub->where('role', 'membre')
                                        ->where(function ($memberQuery) use ($term) {
                                            $memberQuery->where('code', 'like', "%{$term}%")
                                                ->orWhere('name', 'like', "%{$term}%")
                                                ->orWhere('postnom', 'like', "%{$term}%")
                                                ->orWhere('prenom', 'like', "%{$term}%");
                                        });
                                });
                        });
                    }
                });
            });

        return view('livewire.purchase-membership-card', [
            'members' => $this->members,
            'cards' => $cards->latest()->paginate($this->perPage),
        ]);
    }


    private function generateCardCode()
    {
        $year = now()->year;

        $lastCard = MembershipCard::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        if (!$lastCard) {
            $nextNumber = 1;
        } else {
            $lastNumber = (int) explode('/', $lastCard->code)[0];
            $nextNumber = $lastNumber + 1;
        }

        return str_pad($nextNumber, 4, '0', STR_PAD_LEFT) . '/' . $year;
    }


    public function editCard($cardId)
    {
        Gate::authorize('modifier-carnet', User::class);

        $card = MembershipCard::find($cardId);

        if (!$card) {
            notyf()->error('Carte introuvable.');
            return;
        }

        $this->editCardId = $card->id;
        $this->edit_code = $card->code;
        $this->edit_currency = $card->currency;
        $this->edit_subscription_amount = $card->subscription_amount;
        $this->edit_agent_id = $card->user_id;

        $this->editModal = true;
    }

    public function updateCard()
    {
        Gate::authorize('modifier-carnet', User::class);

        $this->validate([
            'edit_code' => 'required|string|unique:membership_cards,code,' . $this->editCardId,
            'edit_currency' => 'required|string',
            'edit_subscription_amount' => 'required|numeric|min:0',
            'edit_agent_id' => 'nullable|exists:users,id',
        ]);

        $card = MembershipCard::find($this->editCardId);

        if (!$card) {
            notyf()->error('Carte introuvable.');
            return;
        }

        $card->update([
            'code' => $this->edit_code,
            'currency' => $this->edit_currency,
            'subscription_amount' => $this->edit_subscription_amount,
            'user_id' => $this->edit_agent_id,
        ]);

        UserLogHelper::log_user_activity(
            action: 'modification_carte_adhesion',
            description: "Modification carte #{$card->code} du membre {$card->member->name}"
        );

        $this->editModal = false;
        $this->reset([
            'editCardId', 'edit_code', 'edit_currency',
            'edit_price', 'edit_subscription_amount', 'edit_agent_id'
        ]);
        $this->dispatch('$refresh');
        notyf()->success('Carte modifiée avec succès.');
    }

    public function showDetails($cardId)
    {
        $card = MembershipCard::find($cardId);
        if (!$card) {
            notyf()->error('Carte introuvable.');
            return;
        }
        $this->detailsCard = $card;
        $this->detailsModal = true;
    }

    public function exportPdf($cardId)
    {
        $card = MembershipCard::with(['member', 'contributions'])->find($cardId);

        if (!$card) {
            notyf()->error('Carte introuvable.');
            return;
        }

        $contributions = $card->contributions()->orderBy('contribution_date')->get();
        $paidContributions = $card->contributions->where('is_paid', 1);
        $unpaidCount = $card->getUnpaidContributionsAttribute()->count();

        $pdf = Pdf::loadView('pdf.carnet-details', [
            'card' => $card,
            'member' => $card->member,
            'paidContributions' => $paidContributions,
            'unpaidCount' => $unpaidCount,
        ])->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'Carnet-'.$card->code.'.pdf');
    }

}
