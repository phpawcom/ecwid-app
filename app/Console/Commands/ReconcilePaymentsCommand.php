<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Payments\PaymentService;
use App\Payments\PaymentStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Catches payments where the shopper paid but never came back to the store
 * (closed the tab), and retries Ecwid updates that failed.
 */
#[Signature('payments:reconcile {--hours=48 : How far back to look}')]
#[Description('Re-check unfinished payments with the gateway and update Ecwid')]
class ReconcilePaymentsCommand extends Command
{
    public function handle(PaymentService $payments): int
    {
        $query = PaymentTransaction::query()
            ->where('created_at', '>=', now()->subHours((int) $this->option('hours')))
            ->where('created_at', '<=', now()->subMinutes(5))
            ->whereNotNull('gateway_reference')
            ->where(fn ($q) => $q
                ->where('status', PaymentStatus::Pending)
                ->orWhere(fn ($q) => $q->where('status', PaymentStatus::Paid)->whereNull('ecwid_synced_at')));

        $count = 0;
        foreach ($query->lazyById() as $transaction) {
            $settled = $payments->settle($transaction);
            $count++;
            $this->line("{$transaction->public_id}: {$settled->status->value}".($settled->ecwid_synced_at ? ' (Ecwid updated)' : ''));
        }

        $this->components->info("Checked $count payment(s).");

        return self::SUCCESS;
    }
}
