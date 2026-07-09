<?php

namespace App\Observers;

use App\Models\ClientPayment;
use App\Models\Commission;

class CommissionObserver
{
    public function created(ClientPayment $payment): void
    {
        $this->generateCommission($payment);
    }

    public function updated(ClientPayment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === 'confirme') {
            $this->generateCommission($payment);
        }
    }

    private function generateCommission(ClientPayment $payment): void
    {
        if ($payment->status !== 'confirme') {
            return;
        }

        $client = $payment->client()->with('salesRep')->first();
        if (! $client || ! $client->salesRep || ! $client->salesRep->is_active) {
            return;
        }

        $rep  = $client->salesRep;
        $rate = (float) $rep->commission_rate;
        if ($rate <= 0) {
            return;
        }

        $base   = (float) $payment->amount;
        $amount = round($base * $rate / 100, 2);

        Commission::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'company_id'        => $payment->company_id,
                'sales_rep_id'      => $rep->id,
                'client_id'         => $payment->client_id,
                'base_amount'       => $base,
                'commission_rate'   => $rate,
                'commission_amount' => $amount,
                'period'            => $payment->payment_date
                    ? $payment->payment_date->format('Y-m')
                    : now()->format('Y-m'),
                'status'            => 'calculee',
            ]
        );
    }
}
