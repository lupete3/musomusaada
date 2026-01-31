<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackfillTransactionMembershipCardId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-transaction-membership-card-id';
    protected $description = 'Populate membership_card_id in transactions table by parsing descriptions';

    public function handle()
    {
        $transactions = \App\Models\Transaction::whereNull('membership_card_id')
            ->whereNull('account_id')
            ->whereIn('type', ['mise_quotidienne', 'retrait_carte_adhesion'])
            ->get();

        $this->info("Found {$transactions->count()} transactions to process.");

        $bar = $this->output->createProgressBar($transactions->count());
        $bar->start();

        foreach ($transactions as $transaction) {
            // Regex to find card code like "CAR-..." or whatever the format is.
            // In MemberDetails.php it uses $card->code.
            // Examples: 
            // "Paiement groupé de X mises sur la carte CODE ..."
            // "Retrait carnet CODE ..."

            if (preg_match('/(?:carte|carnet)\s+([A-Z0-9\/-]+)/i', $transaction->description, $matches)) {
                $code = $matches[1];
                $card = \App\Models\MembershipCard::where('code', $code)->first();
                if ($card) {
                    $transaction->membership_card_id = $card->id;
                    $transaction->save();
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('Backfill completed.');
    }
}
