<?php

namespace App\Services\Assistant;

class AssistantActionExecutor
{
    public function execute(?array $action): ?array
    {
        if (! $action || ($action['type'] ?? null) !== 'navigate') {
            return null;
        }

        $module = $this->normalizeModule((string) ($action['module'] ?? ''));

        if (! $module) {
            return null;
        }

        $filters = is_array($action['filters'] ?? null) ? $action['filters'] : [];
        $query = $this->queryForModule($module, $filters);
        $label = $this->labelForModule($module);

        return [
            'type' => 'navigate',
            'url' => route($this->routeForModule($module), $query),
            'label' => "Open {$label}",
            'auto' => (bool) ($action['auto'] ?? true),
        ];
    }

    private function normalizeModule(string $module): ?string
    {
        $module = str($module)->lower()->replace(['-', ' '], '_')->toString();

        return match ($module) {
            'dashboard', 'home' => 'dashboard',
            'clients', 'client' => 'clients',
            'suppliers', 'supplier' => 'suppliers',
            'documents', 'document', 'files', 'file' => 'documents',
            'tender_proposals', 'tenders', 'tender', 'proposals' => 'tender_proposals',
            'quotation_requests', 'quotation_request', 'incoming_quotations' => 'quotation_requests',
            'requisitions', 'requisition' => 'requisitions',
            'tasks', 'task' => 'tasks',
            'sales_quotations', 'sales_quotes', 'client_quotations' => 'sales_quotations',
            'job_cards', 'job_card', 'jobs', 'work_cards', 'work_card' => 'job_cards',
            'delivery_notes', 'delivery_note', 'delivery', 'handover_notes' => 'delivery_notes',
            'invoices', 'invoice' => 'invoices',
            'approvals', 'approval' => 'approvals',
            'attendance' => 'attendance',
            'reports', 'report' => 'reports',
            'notifications', 'notification' => 'notifications',
            default => null,
        };
    }

    private function routeForModule(string $module): string
    {
        return match ($module) {
            'clients' => 'clients.index',
            'suppliers' => 'suppliers.index',
            'documents' => 'documents.index',
            'tender_proposals' => 'tender-proposals.index',
            'quotation_requests' => 'quotations.index',
            'requisitions' => 'requisitions.index',
            'tasks' => 'tasks.index',
            'sales_quotations' => 'sales-quotations.index',
            'job_cards' => 'job-cards.index',
            'delivery_notes' => 'delivery-notes.index',
            'invoices' => 'invoices.index',
            'approvals' => 'approvals.index',
            'attendance' => 'attendance.index',
            'reports' => 'reports.index',
            'notifications' => 'notifications.index',
            default => 'dashboard',
        };
    }

    private function queryForModule(string $module, array $filters): array
    {
        $allowed = match ($module) {
            'documents' => ['search', 'category', 'date_from', 'date_to', 'module', 'linked_type'],
            'tender_proposals', 'quotation_requests' => ['search', 'status', 'department_id', 'workflow_status', 'assignment_state', 'deadline_window', 'date_from', 'date_to'],
            'requisitions' => ['search', 'status', 'priority', 'category', 'department_id', 'date_from', 'date_to'],
            'tasks' => ['search', 'status', 'priority', 'department_id', 'assigned_to'],
            'sales_quotations' => ['search', 'status'],
            'job_cards' => ['search', 'status', 'department_id'],
            'delivery_notes' => ['search', 'status', 'department_id'],
            'invoices' => ['search', 'status', 'payment_state'],
            'attendance' => ['department_id', 'status', 'date_from', 'date_to'],
            'notifications' => ['state'],
            default => ['search'],
        };

        return collect($filters)
            ->only($allowed)
            ->filter(fn ($value): bool => is_scalar($value) && $value !== '')
            ->all();
    }

    private function labelForModule(string $module): string
    {
        return match ($module) {
            'tender_proposals' => 'tender proposals',
            'quotation_requests' => 'quotation requests',
            'sales_quotations' => 'sales quotations',
            'job_cards' => 'job cards',
            'delivery_notes' => 'delivery notes',
            default => str($module)->replace('_', ' ')->toString(),
        };
    }
}
