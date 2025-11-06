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
    }

    public function updatedSearch()
    {
        $query = trim($this->search);

        if ($query !== '') {
            // Découper la recherche en mots séparés
            $terms = preg_split('/\s+/', $query); // gère plusieurs espaces

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

        try {
            DB::beginTransaction();

            // Récupération du membre
            $member = User::findOrFail($this->member_id);

            // Définition des dates
            $startDate = now();
            $endDate = $startDate->copy()->addDays(30); // 31 jours incluant le jour de début

            // Génération automatique du code
            $this->code = $this->generateCardCode();

            // Création de la carte avec les dates
            $card = MembershipCard::create([
                'code' => $this->code,
                'member_id' => $member->id,
                'currency' => $this->currency,
                'price' => $this->price,
                'subscription_amount' => $this->subscription_amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => true,
            ]);

            // Génération des 31 mises
            $startDate = now();

            for ($i = 0; $i < 31; $i++) {
                $card->contributions()->create([
                    'membership_card_id' => $card->id,
                    'contribution_date' => $startDate->copy()->addDays($i),
                    'amount' => $this->subscription_amount,
                    'is_paid' => false,
                ]);
            }
            // Débit du compte agent
            $agentAccount = MainCashRegister::firstOrCreate(
                ['currency' => 'CDF'],
                ['balance' => 0]
            );
            $agentAccount->balance += $this->price;
            $agentAccount->save();

            // Débit du compte agent des profits des carnets
            $membershipCardAccount = AgentAccount::firstOrCreate(
                ['user_id' => 8, 'currency' => 'CDF'],
                ['balance' => 0]
            );
            $membershipCardAccount->balance += $this->price;
            $membershipCardAccount->save();

            // Enregistrement de la transaction
            Transaction::create([
                'account_id' => null,
                'user_id' => Auth::user()->id,
                'type' => 'vente_carte_adhesion',
                'currency' => 'CDF',
                'amount' => $this->price,
                'balance_after' => $agentAccount->balance,
                'description' => "Vente de carte à {$member->name} - Montant: {$this->price} CDF",
            ]);

            // Enregistrement de la transaction dans le compte 8
            Transaction::create([
                'account_id' => null,
                'agent_account_id' => $membershipCardAccount->id,
                'user_id' => 8,
                'type' => 'vente_carte_adhesion',
                'currency' => 'CDF',
                'amount' => $this->price,
                'balance_after' => $membershipCardAccount->balance,
                'description' => "Vente de carte à {$member->name} - Montant: {$this->price} CDF",
            ]);

            UserLogHelper::log_user_activity(
                action: 'achat_carte_adhesion',
                description: "Achat de la carte #{$card->id} pour le membre {$member->name} {$member->postnom} ({$member->code}), montant total {$this->price} {$this->currency}"
            );

            DB::commit();

            $this->reset(['code','member_id','currency','price','subscription_amount']);
            $this->dispatch('$refresh');
            $this->resetPage();
            notyf()->success('Carte achetée avec succès !');
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e->getMessage());
            notyf()->error("Cette carte existe déjà");
        }
    }

    public function render()
    {
        $cards = MembershipCard::with('member')
            ->when($this->searchCard, function ($query) {
                // Découpe la recherche en plusieurs termes (séparés par espace)
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

        // Récupérer la dernière carte créée pour l'année en cours
        $lastCard = MembershipCard::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        // Si aucune carte n’existe encore pour cette année
        if (!$lastCard) {
            $nextNumber = 1;
        } else {
            // Extraire la partie numérique avant le slash du code (ex: "0001" dans "0001/2025")
            $lastNumber = (int) explode('/', $lastCard->code)[0];
            $nextNumber = $lastNumber + 1;
        }

        // Format du code : 4 chiffres + "/" + année, ex: "0001/2025"
        return str_pad($nextNumber, 4, '0', STR_PAD_LEFT) . '/' . $year;
    }

}
