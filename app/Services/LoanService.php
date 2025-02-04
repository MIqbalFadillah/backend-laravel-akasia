<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        //
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'status' => Loan::STATUS_DUE,
            'processed_at' => Carbon::parse($processedAt)->toDateString()
        ]);

        
        $repaymentAmount = intdiv($amount, $terms);
        $dueDate = Carbon::parse($processedAt)->addMonth();

        for ($i = 0; $i < $terms; $i++) {
            ScheduledRepayment::create([
                'loan_id' => (string) $loan->id,
                'amount' => (string) $repaymentAmount,
                'outstanding_amount' => (string) $repaymentAmount,
                'currency_code' => $currencyCode,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);
            $dueDate->addMonth();
        }

        
        $lastRepayment = ScheduledRepayment::where('loan_id', $loan->id)->latest()->first();
        if ($lastRepayment->amount > $repaymentAmount) {
            $lastRepayment->update(['amount' => $amount - ($repaymentAmount * ($terms - 1))]);
        }

        Log::channel('info')->info('API endpoint Info Logging',[$lastRepayment]);

        return $loan;
    }

    private function calculateRepaymentAmount(int $amount, int $terms): float
    {
        return round($amount / $terms, 2);
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        //
        
        $nextDueRepayment = ScheduledRepayment::where('loan_id', $loan->id)
            ->where('status', ScheduledRepayment::STATUS_DUE)
            ->orderBy('due_date', 'asc')
            ->first();

        if (!$nextDueRepayment) {
            throw new \Exception('No due scheduled repayments found');
        }

    
        $outstandingAmount = $nextDueRepayment->outstanding_amount - $amount;
        if ($outstandingAmount < 0) {
            $outstandingAmount = 0;
        }

        
        if ($outstandingAmount == 0) {
            $nextDueRepayment->update(['status' => ScheduledRepayment::STATUS_REPAID]);
        } else {
            $nextDueRepayment->update(['status' => ScheduledRepayment::STATUS_PARTIAL]);
        }
    
        $loan->update(['outstanding_amount' => $loan->outstanding_amount - $amount]);

        if ($loan->outstanding_amount == 0) {
            $loan->update(['status' => Loan::STATUS_REPAID]);
        }

        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => Carbon::parse($receivedAt),
        ]);

        return $receivedRepayment;
    }
}
