<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function show(PaymentTransaction $transaction): View
    {
        $transaction->load('app');

        return view('admin.transactions.show', [
            'transaction' => $transaction,
            'logs' => $transaction->logs()->oldest('id')->get(),
        ]);
    }
}
