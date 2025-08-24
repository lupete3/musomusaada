<?php

namespace App\Livewire\Credit;

use App\Helpers\UserLogHelper;
use Livewire\Component;
use App\Models\User;
use App\Models\Account;
use App\Models\AgentAccount;
use App\Models\Credit;
use App\Models\Repayment;
use App\Models\MainCashRegister;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GrantCredit extends Component
{
    public $member_id;
    public $currency = 'USD';
    public $amount = 0;
    public $interest_rate = 5.0; // %
    public $installments = 6;
    public $start_date;
    public $frequency = 'daily'; // 'daily', 'monthly', 'weekly'

    public $description = '';

    public $members = [];
    public $search;
    public $results = [];

    protected $rules = [
        'member_id' => 'required|exists:users,id',
        'currency' => 'required|in:USD,CDF',
        'amount' => 'required|numeric|min:0.01',
        'interest_rate' => 'required|numeric|min:0|max:100',
        'installments' => 'required|integer|min:1',
        'start_date' => 'required|date',
        'frequency' => 'required|in:daily,monthly,weekly',
    ];

    public function mount()
    {
        $user = Auth::user();
        Gate::authorize('ajouter-credit', User::class);

        $this->members = User::where('role', 'membre')->get();
        $this->start_date = now()->format('Y-m-d');
    }

    public function updatedSearch()
    {
        $query = trim($this->search);
        if ($query !== '') {
            $this->results = User::query()
                ->where(function($q) use ($query) {
                    $q->where('role', 'membre')
                    ->where('code', 'like', "%{$query}%")
                      ->orWhere('name', 'like', "%{$query}%")
                      ->orWhere('postnom', 'like', "%{$query}%")
                      ->orWhere('prenom', 'like', "%{$query}%")
                      ->orWhere('telephone', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'code', 'name', 'postnom', 'prenom'])
                ->toArray();
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
        $this->validate();

        DB::beginTransaction();
        try {
            $member = User::findOrFail($this->member_id);

            $account = Account::where('user_id', $this->member_id)
                ->where('currency', $this->currency)
                ->lockForUpdate()
                ->firstOrFail();

            $mainCash = MainCashRegister::where('currency', $this->currency)
                ->lockForUpdate()
                ->firstOrCreate(['currency' => $this->currency], ['balance' => 0]);

            $creditFrisFix = round($this->amount * (5 / 100), 2);
            //$creditFris = round($this->amount * ($this->interest_rate / 100), 2);

            if ($account->balance < $creditFrisFix) {
                DB::rollBack();
                notyf()->error(__('Solde insuffisant dans le compte client pour payer les frais du dossier'));
                return;
            }

            if ($mainCash->balance < $this->amount) {
                DB::rollBack();
                notyf()->error(__('Solde insuffisant dans la caisse centrale.'));
                return;
            }

            $account->balance -= $creditFrisFix;
            $account->save();

            // Frais dossier
            Transaction::create([
                'account_id' => $account->id,
                'user_id' => $member->id,
                'type' => 'commission_credit',
                'currency' => $this->currency,
                'amount' => $creditFrisFix,
                'balance_after' => $account->balance,
                'description' => "Frais de commission du dossier du credit. Montant: {$creditFrisFix} {$this->currency} octroyé à {$member->name} {$member->postnom}",
            ]);

            $mainCash->balance -= $this->amount;
            $account->balance += $this->amount;

            $mainCash->save();
            $account->save();

            $credit = Credit::create([
                'user_id'       => $member->id,
                'account_id'    => $account->id,
                'currency'      => $this->currency,
                'amount'        => $this->amount,
                'interest_rate' => $this->interest_rate,
                'installments'  => $this->installments,
                'start_date'    => $this->start_date,
                'due_date'      => Carbon::parse($this->start_date),
                'is_paid'       => false,
            ]);

            // Transactions
            Transaction::create([
                'user_id'       => $member->id,
                'type'           => 'octroi_de_credit',
                'currency'       => $this->currency,
                'amount'        => $this->amount,
                'balance_after' => $account->balance,
                'account_id'    => $account->id,
                'description'    => $this->description ?: "Crédit octroyé à {$member->name} {$member->postnom}",
            ]);

            Transaction::create([
                'account_id'    => NULL,
                'user_id'       => Auth::user()->id,
                'type'           => 'octroi_de_credit_client',
                'currency'       => $credit->currency,
                'amount'        => $this->amount,
                'balance_after' => $mainCash->balance,
                'description'    => $this->description ?: "Crédit octroyé à {$member->name} {$member->postnom}",
            ]);

            UserLogHelper::log_user_activity(
                action: 'octroi_de_credit',
                description: "Crédit octroyé à {$member->name} {$member->postnom} ({$member->code}), montant total {$this->amount} {$this->currency}"
            );

            // 👉 Échéancier à intérêt CONSTANT
            $interestPart = round($this->amount * ($this->interest_rate / 100), 2);
            $capitalPart = round($this->amount / $this->installments, 2);
            $installmentTotal = round($capitalPart + $interestPart, 2);

            $remainingCapital = $this->amount;

            $startDate = Carbon::parse($this->start_date);
            $currentDate = $startDate->copy();
            $lastDueDate = null;

            for ($i = 0; $i < $this->installments; $i++) {
                // Dernière ligne : corriger le capital restant
                if ($i == $this->installments - 1) {
                    $capitalPart = round($remainingCapital, 2);
                    $installmentTotal = round($capitalPart + $interestPart, 2);
                }

                Repayment::create([
                    'credit_id'       => $credit->id,
                    'due_date'        => $currentDate->toDateString(),
                    'expected_amount' => $installmentTotal,
                    'total_due'       => $installmentTotal,
                ]);

                $remainingCapital -= $capitalPart;

                $lastDueDate = $currentDate->copy();

                // Incrémentation de la date selon la fréquence
                if ($this->frequency === 'daily') {
                    do {
                        $currentDate->addDay();
                    } while ($currentDate->isSunday());
                } elseif ($this->frequency === 'weekly') {
                    $currentDate = $i === 0 ? $currentDate : $currentDate->addWeek();
                    if ($currentDate->isSunday()) {
                        $currentDate->addDay();
                    }
                } else {
                    $currentDate->addMonth();
                }
            }

            $credit->due_date = $lastDueDate ? $lastDueDate->toDateString() : $credit->start_date;
            $credit->save();

            // Envoyer les frais de commitssion du crédit au compte 6
            $commissionCreditAccount = AgentAccount::firstOrCreate(
                ['user_id' => 6, 'currency' => $credit->currency],
                ['balance' => 0]
            );

            $commissionCreditAccount->balance += $interestPart;
            $commissionCreditAccount->save();

            // Envoyer le montant du crédit au compte 2 du caissier pour attente du retrait
            $cassisierAccount = AgentAccount::firstOrCreate(
                ['user_id' => 2, 'currency' => $credit->currency],
                ['balance' => 0]
            );
            $cassisierAccount->balance += $this->amount;
            $cassisierAccount->save();

            // Enregistrement de la transaction pour commission crédit
            Transaction::create([
                'account_id' => null,
                'agent_account_id' => $commissionCreditAccount->id,
                'user_id' => 6,
                'type' => 'commission_credit',
                'currency' => $credit->currency,
                'amount' => $interestPart,
                'balance_after' => $commissionCreditAccount->balance,
                'description' => "Frais de commission du dossier du credit #{$credit->id} - Montant: {$interestPart} {$credit->currency} octroyé à {$member->name} {$member->postnom}",
            ]);

            // Enregistrement de la transaction pour commission crédit au caissier
            Transaction::create([
                'account_id' => null,
                'agent_account_id' => $cassisierAccount->id,
                'user_id' => 2,
                'type' => 'frais_credit_pour_retrait',
                'currency' => $credit->currency,
                'amount' => $this->amount,
                'balance_after' => $cassisierAccount->balance,
                'description' => "Frais à retirer du dossier du credit #{$credit->id} - Montant: {$this->amount} {$credit->currency} du client {$member->name} {$member->postnom}",
            ]);

            DB::commit();

            notyf()->success(__('Crédit octroyé avec succès !'));
            $this->reset(['amount', 'description']);
            $this->dispatch('facture-validee', url: route('credit.receipt.generate', ['id' => $credit->id]));

        } catch (\Throwable $th) {
            DB::rollBack();
            report($th);
            notyf()->error(__('Une erreur est survenue lors de l’octroi du crédit.'));
        }
    }

    // remboursement degressif
    // public function submit()
    // {
    //     $this->validate();

    //     DB::beginTransaction();
    //     try {
    //         $member = User::findOrFail($this->member_id);

    //         $account = Account::where('user_id', $this->member_id)
    //             ->where('currency', $this->currency)
    //             ->lockForUpdate()
    //             ->firstOrFail();

    //         $mainCash = MainCashRegister::where('currency', $this->currency)
    //             ->lockForUpdate()
    //             ->firstOrCreate(['currency' => $this->currency], ['balance' => 0]);

    //         if ($mainCash->balance < $this->amount) {
    //             DB::rollBack();
    //             notyf()->error(__('Solde insuffisant dans la caisse centrale.'));
    //             return;
    //         }

    //         $mainCash->balance -= $this->amount;
    //         $account->balance += $this->amount;

    //         $mainCash->save();
    //         $account->save();

    //         $credit = Credit::create([
    //             'user_id'       => $member->id,
    //             'account_id'    => $account->id,
    //             'currency'      => $this->currency,
    //             'amount'        => $this->amount,
    //             'interest_rate' => $this->interest_rate,
    //             'installments'  => $this->installments,
    //             'start_date'    => $this->start_date,
    //             'due_date'      => Carbon::parse($this->start_date),
    //             'is_paid'       => false,
    //         ]);

    //         // Transactions
    //         Transaction::create([
    //             'user_id'       => $member->id,
    //             'type'           => 'octroi_de_credit',
    //             'currency'       => $this->currency,
    //             'amount'        => $this->amount,
    //             'balance_after' => $account->balance,
    //             'account_id'    => $account->id,
    //             'description'    => $this->description ?: "Crédit octroyé à {$member->name} {$member->postnom}",
    //         ]);

    //         Transaction::create([
    //             'account_id'    => $account->id,
    //             'user_id'       => Auth::user()->id,
    //             'type'           => 'octroi_de_credit_client',
    //             'currency'       => $credit->currency,
    //             'amount'        => $this->amount,
    //             'balance_after' => $mainCash->balance,
    //             'description'    => $this->description ?: "Crédit octroyé à {$member->name} {$member->postnom}",
    //         ]);

    //         // 👉 Échéancier à remboursement dégressif
    //         $capitalPart = $this->amount / $this->installments;
    //         $remainingCapital = $this->amount;

    //         $startDate = Carbon::parse($this->start_date);
    //         $currentDate = $startDate->copy();
    //         $lastDueDate = null;

    //         for ($i = 0; $i < $this->installments; $i++) {
    //             $interest = $remainingCapital * ($this->interest_rate / 100);
    //             $installmentTotal = $capitalPart + $interest;

    //             Repayment::create([
    //                 'credit_id'        => $credit->id,
    //                 'due_date'         => $currentDate->toDateString(),
    //                 'expected_amount'  => round($installmentTotal, 2),
    //                 'total_due'        => round($installmentTotal, 2),
    //             ]);

    //             $remainingCapital -= $capitalPart;

    //             $lastDueDate = $currentDate->copy();

    //             // Incrémentation de la date selon la fréquence
    //             if ($this->frequency === 'daily') {
    //                 do {
    //                     $currentDate->addDay();
    //                 } while ($currentDate->isSunday());
    //             } elseif ($this->frequency === 'weekly') {
    //                 $currentDate = $i === 0 ? $currentDate : $currentDate->addWeek();
    //                 if ($currentDate->isSunday()) {
    //                     $currentDate->addDay();
    //                 }
    //             } else {
    //                 $currentDate->addMonth();
    //             }
    //         }

    //         $credit->due_date = $lastDueDate ? $lastDueDate->toDateString() : $credit->start_date;
    //         $credit->save();

    //         DB::commit();

    //         notyf()->success(__('Crédit octroyé avec succès !'));
    //         $this->reset(['amount', 'description']);
    //         $this->dispatch('facture-validee', url: route('credit.receipt.generate', ['id' => $credit->id]));

    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         report($th);
    //         notyf()->error(__('Une erreur est survenue lors de l’octroi du crédit.'));
    //     }
    // }

    // Taux constat reguit
    // public function submit()
    // {
    //     $this->validate();

    //     DB::beginTransaction();
    //     try {
    //         $member = User::findOrFail($this->member_id);

    //         $account = Account::where('user_id', $this->member_id)
    //             ->where('currency', $this->currency)
    //             ->lockForUpdate()
    //             ->firstOrFail();

    //         $mainCash = MainCashRegister::where('currency', $this->currency)
    //             ->lockForUpdate()
    //             ->firstOrCreate(['currency' => $this->currency], ['balance' => 0]);

    //         if ($mainCash->balance < $this->amount) {
    //             DB::rollBack();
    //             notyf()->error(__('Solde insuffisant dans la caisse centrale.'));
    //             return;
    //         }

    //         $mainCash->balance -= $this->amount;
    //         $account->balance += $this->amount;

    //         $mainCash->save();
    //         $account->save();

    //         $credit = Credit::create([
    //             'user_id' => $member->id,
    //             'account_id' => $account->id,
    //             'currency' => $this->currency,
    //             'amount' => $this->amount,
    //             'interest_rate' => $this->interest_rate,
    //             'installments' => $this->installments,
    //             'start_date' => $this->start_date,
    //             'due_date' => Carbon::parse($this->start_date),
    //             'is_paid' => false,
    //         ]);

    //         Transaction::create([
    //             'user_id' => $member->id,
    //             'type' => 'octroi_de_credit',
    //             'currency' => $this->currency,
    //             'amount' => $this->amount,
    //             'balance_after' => $account->balance,
    //             'account_id' => $account->id,
    //             'description' => $this->description ?: "Crédit octroyé à votre compte: {$member->code} du client {$member->name} {$member->postnom}",
    //         ]);

    //         Transaction::create([
    //             'account_id' => $account->id,
    //             'user_id' => Auth::user()->id,
    //             'type' => 'octroi_de_credit_client',
    //             'currency' => $credit->currency,
    //             'amount' => $this->amount,
    //             'balance_after' => $mainCash->balance,
    //             'description' => $this->description ?: "Crédit octroyé au compte: {$member->code} du client {$member->name} {$member->postnom}",
    //         ]);

    //         // Échéancier
    //         $totalWithInterest = $this->amount * (1 + $this->interest_rate / 100);
    //         $installmentAmount = round($totalWithInterest / $this->installments, 2);
    //         $startDate = Carbon::parse($this->start_date);
    //         $currentDate = $startDate->copy();
    //         $installmentsAdded = 0;
    //         $lastDueDate = null;

    //         while ($installmentsAdded < $this->installments) {
    //             if ($this->frequency === 'daily') {
    //                 if (!$currentDate->isSunday()) {
    //                     Repayment::create([
    //                         'credit_id' => $credit->id,
    //                         'due_date' => $currentDate->toDateString(),
    //                         'expected_amount' => $installmentAmount,
    //                         'total_due' => $installmentAmount,
    //                     ]);
    //                     $lastDueDate = $currentDate->copy();
    //                     $installmentsAdded++;
    //                 }
    //                 $currentDate->addDay();
    //             } elseif ($this->frequency === 'weekly') {
    //                 $currentDate = $installmentsAdded === 0 ? $startDate : $currentDate->addWeek();
    //                 if (!$currentDate->isSunday()) {
    //                     Repayment::create([
    //                         'credit_id' => $credit->id,
    //                         'due_date' => $currentDate->toDateString(),
    //                         'expected_amount' => $installmentAmount,
    //                         'total_due' => $installmentAmount,
    //                     ]);
    //                     $lastDueDate = $currentDate->copy();
    //                     $installmentsAdded++;
    //                 } else {
    //                     $currentDate->addDay(); // éviter le dimanche
    //                 }
    //             } else { // monthly
    //                 Repayment::create([
    //                     'credit_id' => $credit->id,
    //                     'due_date' => $currentDate->toDateString(),
    //                     'expected_amount' => $installmentAmount,
    //                     'total_due' => $installmentAmount,
    //                 ]);
    //                 $lastDueDate = $currentDate->copy();
    //                 $installmentsAdded++;
    //                 $currentDate->addMonth();
    //             }
    //         }

    //         $credit->due_date = $lastDueDate ? $lastDueDate->toDateString() : $credit->start_date;
    //         $credit->save();

    //         DB::commit();

    //         notyf()->success(__('Crédit octroyé avec succès !'));

    //         $this->reset(['amount', 'description']);
    //         $this->dispatch('facture-validee', url: route('credit.receipt.generate', ['id' => $credit->id]));
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         report($th);
    //         notyf()->error(__('Une erreur est survenue lors de l’octroi du crédit.'));
    //     }
    // }

    public function render()
    {
        return view('livewire.credit.grant-credit', [
            'members' => $this->members,
        ]);
    }
}
