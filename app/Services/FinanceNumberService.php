<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\DeliveryNote;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\PurchaseRecord;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Supplier;

class FinanceNumberService
{
    public function clientCode(): string
    {
        return $this->next(Client::class, 'client_code', 'CLT');
    }

    public function salesQuotationNumber(): string
    {
        return $this->next(SalesQuotation::class, 'quotation_number', 'QUO');
    }

    public function invoiceNumber(): string
    {
        return $this->next(Invoice::class, 'invoice_number', 'INV');
    }

    public function jobCardNumber(): string
    {
        return $this->next(JobCard::class, 'job_card_number', 'JOB');
    }

    public function deliveryNoteNumber(): string
    {
        return $this->next(DeliveryNote::class, 'delivery_note_number', 'DN');
    }

    public function paymentNumber(): string
    {
        return $this->next(Payment::class, 'payment_number', 'REC');
    }

    public function expenseNumber(): string
    {
        return $this->next(Expense::class, 'expense_number', 'EXP');
    }

    public function requisitionNumber(): string
    {
        return $this->next(Requisition::class, 'requisition_number', 'REQ');
    }

    public function supplierCode(): string
    {
        return $this->next(Supplier::class, 'supplier_code', 'SUP');
    }

    public function purchaseNumber(): string
    {
        return $this->next(PurchaseRecord::class, 'purchase_number', 'PUR');
    }

    public function taskNumber(): string
    {
        return $this->next(\App\Models\CrmTask::class, 'task_number', 'TSK');
    }

    /**
     * @param  class-string  $modelClass
     */
    private function next(string $modelClass, string $column, string $prefix): string
    {
        $year = now()->format('Y');
        $pattern = "{$prefix}-{$year}-%";
        $last = $modelClass::query()
            ->where($column, 'like', $pattern)
            ->orderByDesc('id')
            ->value($column);
        $lastNumber = $last ? (int) substr((string) $last, -4) : 0;

        return sprintf('%s-%s-%04d', $prefix, $year, $lastNumber + 1);
    }
}
