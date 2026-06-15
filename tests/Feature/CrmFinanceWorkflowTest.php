<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmFinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_can_draft_sales_quotation_and_director_approves_with_fixed_vat(): void
    {
        $department = $this->department('MIS Department');
        $departmentUser = $this->user('MIS User', 'mis@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);
        $client = Client::query()->create([
            'client_code' => 'CLT-2026-0001',
            'name' => 'Acme Client',
            'is_active' => true,
        ]);

        $response = $this->actingAs($departmentUser)->post(route('sales-quotations.store'), [
            'client_id' => $client->id,
            'quotation_number' => 'QUO-2026-0001',
            'title' => 'CRM Services',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'description' => 'Implementation service',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount_amount' => 0,
                    'taxable' => 1,
                ],
            ],
        ]);

        $quotation = SalesQuotation::query()->firstOrFail();
        $response->assertRedirect(route('sales-quotations.show', $quotation));
        $this->assertSame('230.00', $quotation->fresh()->total);
        $this->assertSame('30.00', $quotation->fresh()->vat_total);

        $this->actingAs($departmentUser)->post(route('sales-quotations.submit', $quotation))->assertRedirect();
        $this->actingAs($director)->post(route('sales-quotations.approve', $quotation), [
            'approval_notes' => 'Approved for client issue.',
        ])->assertRedirect();

        $this->assertDatabaseHas('sales_quotations', [
            'id' => $quotation->id,
            'status' => SalesQuotation::STATUS_APPROVED,
            'approved_by' => $director->id,
        ]);
    }

    public function test_department_job_card_drives_reception_invoice_and_payment(): void
    {
        $department = $this->department('IT Department');
        $departmentUser = $this->user('IT User', 'it.workflow@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $reception = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION);
        $client = Client::query()->create([
            'client_code' => 'CLT-2026-0002',
            'name' => 'Invoice Client',
            'is_active' => true,
        ]);
        $quotation = SalesQuotation::query()->create([
            'client_id' => $client->id,
            'department_id' => $department->id,
            'created_by' => $departmentUser->id,
            'quotation_number' => 'QUO-2026-0002',
            'title' => 'Approved Quote',
            'status' => SalesQuotation::STATUS_APPROVED,
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
            'subtotal' => 100,
            'vat_total' => 15,
            'total' => 115,
        ]);
        $quotation->items()->create([
            'position' => 1,
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 100,
            'taxable' => true,
            'line_subtotal' => 100,
            'vat_amount' => 15,
            'line_total' => 115,
        ]);

        $this->actingAs($departmentUser)->post(route('sales-quotations.mark-sent', $quotation))->assertRedirect();
        $this->actingAs($departmentUser)->post(route('sales-quotations.mark-accepted', $quotation))->assertRedirect();

        $this->actingAs($departmentUser)->post(route('job-cards.store'), [
            'sales_quotation_id' => $quotation->id,
            'job_card_number' => 'JOB-2026-0001',
            'title' => 'Approved Quote Delivery',
            'scope' => 'Deliver and configure the approved service.',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'delivery_required' => 1,
        ])->assertRedirect();

        $jobCard = JobCard::query()->firstOrFail();
        $this->assertSame(JobCard::STATUS_DRAFT, $jobCard->status);
        $this->assertTrue($jobCard->delivery_required);

        $this->actingAs($departmentUser)->post(route('job-cards.create-invoice', $jobCard))->assertForbidden();
        $this->actingAs($departmentUser)->post(route('job-cards.ready-for-invoice', $jobCard))->assertRedirect();

        $this->actingAs($reception)->post(route('delivery-notes.store'), [
            'job_card_id' => $jobCard->id,
            'delivery_note_number' => 'DN-2026-0001',
            'delivery_date' => now()->toDateString(),
            'recipient_name' => 'Client Receiver',
            'delivery_address' => 'Client offices',
            'notes' => 'Router delivered.',
        ])->assertRedirect();

        $deliveryNote = DeliveryNote::query()->firstOrFail();
        $this->actingAs($reception)->post(route('delivery-notes.issue', $deliveryNote))->assertRedirect();

        $this->actingAs($reception)->post(route('job-cards.create-invoice', $jobCard->fresh()))->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame($quotation->id, $invoice->sales_quotation_id);
        $this->assertSame($jobCard->id, $invoice->job_card_id);
        $this->assertSame('115.00', $invoice->total);
        $this->assertSame('115.00', $invoice->balance_due);

        $this->actingAs($reception)->post(route('invoices.issue', $invoice))->assertRedirect();

        $this->actingAs($reception)->post(route('payments.store', $invoice), [
            'payment_date' => now()->toDateString(),
            'amount' => 115,
            'method' => 'Bank Transfer',
            'reference' => 'BANK-001',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame('0.00', $invoice->balance_due);
    }

    public function test_department_user_cannot_see_other_department_sales_quotation(): void
    {
        $it = $this->department('IT Department');
        $gis = $this->department('GIS Department');
        $itUser = $this->user('IT User', 'it@example.com', User::ROLE_DEPARTMENT_USER, $it);
        $client = Client::query()->create([
            'client_code' => 'CLT-2026-0003',
            'name' => 'Restricted Client',
            'is_active' => true,
        ]);
        $quotation = SalesQuotation::query()->create([
            'client_id' => $client->id,
            'department_id' => $gis->id,
            'quotation_number' => 'QUO-2026-0003',
            'title' => 'GIS Quote',
            'status' => SalesQuotation::STATUS_DRAFT,
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
        ]);

        $this->actingAs($itUser)->get(route('sales-quotations.show', $quotation))->assertForbidden();
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $email, string $role, ?Department $department = null): User
    {
        return User::query()->create([
            'department_id' => $department?->id,
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
