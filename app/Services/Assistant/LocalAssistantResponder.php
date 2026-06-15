<?php

namespace App\Services\Assistant;

use App\Models\CrmTask;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LocalAssistantResponder
{
    public function __construct(private readonly AssistantActionExecutor $actionExecutor)
    {
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function navigation(User $user, string $message, array $history = []): ?array
    {
        $module = $this->requestedNavigationModule($message, $history);

        if (! $module) {
            return null;
        }

        $text = $this->normalize($message);
        $action = $this->actionExecutor->execute([
            'type' => 'navigate',
            'module' => $module,
            'filters' => $this->navigationFilters($module, $text),
            'auto' => true,
        ]);

        if (! $action) {
            return null;
        }

        return [
            'intent' => 'local_navigation',
            'reply' => 'Opening '.$this->moduleLabel($module).'.',
            'action' => $action,
            'filters' => $this->navigationFilters($module, $text),
            'assistant_mode' => 'local_navigation',
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function answer(User $user, string $message, array $crmContext, array $history = []): ?array
    {
        $text = $this->normalize($message);

        if ($this->asksCapabilityQuestion($text)) {
            return $this->capabilityResult($text);
        }

        if ($this->asksTaskWorkloadQuestion($text)) {
            return $this->taskWorkloadResult($user);
        }

        if ($this->looksLikeNavigationFollowUp($text)) {
            return null;
        }

        $conversationText = $this->conversationText($history, $text);

        $reply = match (true) {
            $this->asksDateOrTime($text) => $this->dateTimeReply(),
            $this->asksVatQuestion($text) => 'VAT is fixed at 15% in this CRM.',
            $this->isGreeting($text) => "I'm here, {$this->firstName($user)}. What would you like to check?",
            $this->asksGenericQuotationCount($text) => $this->quotationCountReply($crmContext),
            $this->asksSupplierCount($text) => $this->countReply('suppliers', 'supplier', $crmContext),
            $this->asksClientCount($text) => $this->countReply('clients', 'client', $crmContext),
            $this->asksTenderCount($text) => $this->countReply('tender_proposals', 'tender proposal', $crmContext),
            $this->asksQuotationRequestCount($text) => $this->countReply('quotation_requests', 'quotation request', $crmContext),
            $this->asksSalesQuotationCount($text) => $this->countReply('sales_quotations', 'sales quotation', $crmContext),
            $this->asksJobCardCount($text) => $this->countReply('job_cards', 'job card', $crmContext),
            $this->asksDeliveryNoteCount($text) => $this->countReply('delivery_notes', 'delivery note', $crmContext),
            $this->asksInvoiceCount($text) => $this->countReply('invoices', 'invoice', $crmContext),
            $this->asksUnpaidInvoiceCount($text) => $this->countReply('unpaid_invoices', 'unpaid invoice', $crmContext),
            $this->asksRequisitionCount($text) => $this->countReply('requisitions', 'requisition', $crmContext),
            $this->asksTaskCount($text) => $this->countReply('tasks', 'task', $crmContext),
            $this->asksDocumentCount($text) => $this->countReply('documents', 'document', $crmContext),
            $this->asksUnreadNotificationCount($text) => $this->countReply('unread_notifications', 'unread notification', $crmContext),
            $this->asksPendingApprovalCount($text) => $this->countReply('pending_approvals', 'pending approval', $crmContext),
            $this->asksInvoiceGeneration($conversationText) => $this->invoiceGenerationReply($user),
            $this->asksSalesHealth($conversationText) => $this->salesHealthReply($user),
            default => null,
        };

        if (! $reply) {
            return null;
        }

        return [
            'intent' => 'local_fallback',
            'reply' => $reply,
            'action' => null,
            'filters' => [],
            'assistant_mode' => 'local_fallback',
        ];
    }

    private function asksDateOrTime(string $text): bool
    {
        return (bool) preg_match('/\b(today|date|time|day is it)\b/i', $text);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function requestedNavigationModule(string $message, array $history): ?string
    {
        $text = $this->normalize($message);

        if ($this->asksCapabilityQuestion($text)) {
            return null;
        }

        if ($this->asksTaskWorkloadQuestion($text)) {
            return null;
        }

        if ($this->blocksNavigation($text)) {
            return null;
        }

        if ($this->isAnalysisQuestion($text)) {
            return null;
        }

        $choiceModule = $this->moduleFromChoice($text, $history);

        if ($choiceModule) {
            return $choiceModule;
        }

        if (! $this->hasNavigationIntent($text) && ! $this->isAffirmativeNavigation($text)) {
            return null;
        }

        return $this->moduleFromText($text)
            ?? $this->moduleFromRecentAssistantMessage($history);
    }

    private function hasNavigationIntent(string $text): bool
    {
        return (bool) preg_match('/\b(open|view|show|sow|list|go to|navigate to|take me to|bring up|pull up|send me to)\b/i', $text);
    }

    private function isAffirmativeNavigation(string $text): bool
    {
        return (bool) preg_match('/^(yes|yeah|yebo|ok|okay|please|go ahead|do that|show me|sow me|open it|view it|that one)$/i', trim($text));
    }

    private function looksLikeNavigationFollowUp(string $text): bool
    {
        return $this->hasNavigationIntent($text) || $this->isAffirmativeNavigation($text);
    }

    private function isAnalysisQuestion(string $text): bool
    {
        return (bool) preg_match(
            '/\b(how are|how is|how do|how did|how to|why|where are we|where do we|falling short|fall short|improve|performance|doing|trend|trends|analyse|analyze|summary|summarize|summarise|explain|guide me|teach me|walk me through|steps|procedure|process|what is|what are|what should|what can)\b/i',
            $text,
        );
    }

    private function blocksNavigation(string $text): bool
    {
        return (bool) preg_match(
            "/\\b(without opening|without open|without navigating|without navigate|without taking me|before we open|before opening|don't open|do not open|dont open|don't navigate|do not navigate|dont navigate|not open|not navigate|no navigation|stay here)\\b/i",
            $text,
        );
    }

    private function asksCapabilityQuestion(string $text): bool
    {
        return (bool) preg_match(
            "/\\b(what can you do|what you can do|what u can do|what can'?t you do|what you can'?t do|what you cannot do|what are you capable of|things you can do|things you can'?t do|list of things you can|list of what you can|your capabilities|your limitations|do you have (?:the )?capabilit|are you able to|can you draft|can you create|can you make|can you edit|can you approve|can you delete|can you send|can you release|can you change|can you update|do you support)\\b/i",
            $text,
        );
    }

    private function capabilityResult(string $text): array
    {
        $reply = 'I can answer questions from the CRM records you are allowed to see, count and summarize work, explain finance or operations gaps, find documents, and open filtered pages when you clearly ask me to. I can also help draft wording for quotations, requisitions, emails, notes, and follow-ups. I cannot save, edit, approve, delete, send, release funds, or change records on your behalf yet.';

        if ((bool) preg_match('/\b(draft|quotation|quote|estimate)\b/i', $text)) {
            $reply = 'Yes, I can help draft a quotation in text: structure the scope, line items, terms, VAT notes, and wording. I can also open the Sales Quotations page when you ask. I cannot create or save the official quotation directly yet; a staff member still needs to enter and submit it in the CRM.';
        }

        return [
            'intent' => 'local_capability_answer',
            'reply' => $reply,
            'action' => null,
            'filters' => [],
            'assistant_mode' => 'local_fallback',
        ];
    }

    private function moduleFromText(string $text): ?string
    {
        $map = [
            'sales_quotations' => ['sales quotation', 'sales quotations', 'sales quote', 'sales quotes', 'client quotation', 'client quotations', 'estimate', 'estimates', 'sales pipeline'],
            'quotation_requests' => ['quotation request', 'quotation requests', 'quote request', 'quote requests', 'rfq', 'incoming quotation'],
            'tender_proposals' => ['tender proposal', 'tender proposals', 'tender', 'tenders', 'sppra', 'esppra'],
            'job_cards' => ['job card', 'job cards', 'work card', 'work cards'],
            'delivery_notes' => ['delivery note', 'delivery notes', 'delivery', 'handover note', 'handover notes'],
            'invoices' => ['invoice', 'invoices', 'payment details'],
            'clients' => ['client', 'clients', 'customer', 'customers'],
            'suppliers' => ['supplier', 'suppliers', 'vendor', 'vendors'],
            'requisitions' => ['requisition', 'requisitions', 'fund request', 'funds request'],
            'tasks' => ['departmental task', 'departmental tasks', 'task', 'tasks', 'workload'],
            'documents' => ['document', 'documents', 'file', 'files', 'attachment', 'attachments'],
            'approvals' => ['approval', 'approvals'],
            'attendance' => ['attendance', 'clock in', 'clock out'],
            'reports' => ['report', 'reports'],
            'notifications' => ['notification', 'notifications', 'alert', 'alerts'],
            'dashboard' => ['dashboard', 'home'],
        ];

        foreach ($map as $module => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($text, $phrase)) {
                    return $module;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function moduleFromRecentAssistantMessage(array $history): ?string
    {
        $message = collect($history)
            ->reverse()
            ->first(fn (array $message): bool => ($message['role'] ?? null) === 'assistant');

        if (! is_array($message)) {
            return null;
        }

        return $this->moduleFromText($this->normalize($message['content'] ?? ''));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function moduleFromChoice(string $text, array $history): ?string
    {
        if (! preg_match('/^(?:option\s*)?([abc])$/i', trim($text), $matches)) {
            return null;
        }

        $assistantMessage = collect($history)
            ->reverse()
            ->first(fn (array $message): bool => ($message['role'] ?? null) === 'assistant');

        if (! is_array($assistantMessage)) {
            return null;
        }

        $choice = strtolower($matches[1]);
        $content = (string) ($assistantMessage['content'] ?? '');
        $pattern = '/(?:^|\n|\s)'.preg_quote($choice, '/').'\)\s*(.*?)(?=(?:\n|\s)[abc]\)|$)/is';

        if (preg_match($pattern, $content, $optionMatches)) {
            return $this->moduleFromText($this->normalize($optionMatches[1]));
        }

        return null;
    }

    private function navigationFilters(string $module, string $text): array
    {
        if ($module === 'invoices') {
            if (str_contains($text, 'unpaid')) {
                return ['payment_state' => 'unpaid'];
            }

            if (str_contains($text, 'overdue')) {
                return ['status' => Invoice::STATUS_OVERDUE];
            }
        }

        if ($module === 'sales_quotations') {
            $status = match (true) {
                str_contains($text, 'draft') => SalesQuotation::STATUS_DRAFT,
                str_contains($text, 'approved') => SalesQuotation::STATUS_APPROVED,
                str_contains($text, 'accepted') => SalesQuotation::STATUS_ACCEPTED,
                str_contains($text, 'sent') => SalesQuotation::STATUS_SENT,
                str_contains($text, 'converted') => SalesQuotation::STATUS_CONVERTED,
                str_contains($text, 'rejected') => SalesQuotation::STATUS_REJECTED,
                default => null,
            };

            return $status ? ['status' => $status] : [];
        }

        if ($module === 'tasks') {
            if (str_contains($text, 'pending') || str_contains($text, 'to do') || str_contains($text, 'todo')) {
                return ['status' => CrmTask::STATUS_TO_DO];
            }

            if (str_contains($text, 'progress')) {
                return ['status' => CrmTask::STATUS_IN_PROGRESS];
            }

            if (str_contains($text, 'blocked')) {
                return ['status' => CrmTask::STATUS_BLOCKED];
            }
        }

        if ($module === 'job_cards') {
            $status = match (true) {
                str_contains($text, 'ready') || str_contains($text, 'invoice') => JobCard::STATUS_READY_FOR_INVOICE,
                str_contains($text, 'progress') => JobCard::STATUS_IN_PROGRESS,
                str_contains($text, 'complete') => JobCard::STATUS_COMPLETED,
                str_contains($text, 'draft') => JobCard::STATUS_DRAFT,
                str_contains($text, 'invoiced') => JobCard::STATUS_INVOICED,
                default => null,
            };

            return $status ? ['status' => $status] : [];
        }

        if ($module === 'delivery_notes') {
            $status = match (true) {
                str_contains($text, 'issued') => DeliveryNote::STATUS_ISSUED,
                str_contains($text, 'delivered') => DeliveryNote::STATUS_DELIVERED,
                str_contains($text, 'draft') => DeliveryNote::STATUS_DRAFT,
                default => null,
            };

            return $status ? ['status' => $status] : [];
        }

        if ($module === 'requisitions' && str_contains($text, 'pending')) {
            return ['status' => 'Submitted'];
        }

        return [];
    }

    private function taskWorkloadResult(User $user): array
    {
        $openStatuses = [
            CrmTask::STATUS_TO_DO,
            CrmTask::STATUS_IN_PROGRESS,
            CrmTask::STATUS_BLOCKED,
        ];
        $openQuery = CrmTask::query()
            ->visibleTo($user)
            ->whereIn('status', $openStatuses);
        $openCount = (clone $openQuery)->count();
        $overdueCount = (clone $openQuery)
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
        $openTasks = (clone $openQuery)
            ->with(['assignee:id,name', 'department:id,name'])
            ->orderByRaw('due_date is null, due_date asc')
            ->latest()
            ->limit(5)
            ->get();

        if ($openCount === 0) {
            return [
                'intent' => 'local_task_answer',
                'reply' => 'No, I do not see any open tasks or pending work in your task list right now.',
                'action' => null,
                'filters' => [],
                'assistant_mode' => 'local_fallback',
            ];
        }

        $examples = $openTasks
            ->map(fn (CrmTask $task): string => trim($task->task_number.' - '.$task->title))
            ->implode('; ');
        $reply = "Yes, you have {$openCount} open {$this->plural('task', $openCount)}";

        if ($overdueCount > 0) {
            $reply .= ", including {$overdueCount} overdue";
        }

        $reply .= '.';

        if ($examples !== '') {
            $reply .= ' Nearest items: '.$examples.'.';
        }

        return [
            'intent' => 'local_task_answer',
            'reply' => $reply,
            'action' => null,
            'filters' => [],
            'assistant_mode' => 'local_fallback',
        ];
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'sales_quotations' => 'Sales Quotations',
            'quotation_requests' => 'Quotation Requests',
            'tender_proposals' => 'Tender Proposals',
            'job_cards' => 'Job Cards',
            'delivery_notes' => 'Delivery Notes',
            default => Str::of($module)->replace('_', ' ')->title()->toString(),
        };
    }

    private function isGreeting(string $text): bool
    {
        return (bool) preg_match('/^(hi|hello|hey|morning|good morning|good afternoon|good evening)\b/i', $text);
    }

    private function asksSupplierCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'supplier');
    }

    private function asksClientCount(string $text): bool
    {
        return $this->isCountQuestion($text) && (str_contains($text, 'client') || str_contains($text, 'customer'));
    }

    private function asksTaskCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'task');
    }

    private function asksTenderCount(string $text): bool
    {
        return $this->isCountQuestion($text) && (str_contains($text, 'tender') || str_contains($text, 'proposal'));
    }

    private function asksQuotationRequestCount(string $text): bool
    {
        return $this->isCountQuestion($text)
            && (str_contains($text, 'quotation request') || str_contains($text, 'quote request') || str_contains($text, 'rfq'));
    }

    private function asksSalesQuotationCount(string $text): bool
    {
        return $this->isCountQuestion($text)
            && (str_contains($text, 'sales quotation') || str_contains($text, 'client quotation') || str_contains($text, 'estimate'));
    }

    private function asksJobCardCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'job card');
    }

    private function asksDeliveryNoteCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'delivery note');
    }

    private function asksGenericQuotationCount(string $text): bool
    {
        return $this->isCountQuestion($text)
            && (str_contains($text, 'quotation') || str_contains($text, 'quote'))
            && ! $this->asksQuotationRequestCount($text)
            && ! $this->asksSalesQuotationCount($text);
    }

    private function asksInvoiceCount(string $text): bool
    {
        return $this->isCountQuestion($text)
            && str_contains($text, 'invoice')
            && ! str_contains($text, 'unpaid');
    }

    private function asksUnpaidInvoiceCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'unpaid') && str_contains($text, 'invoice');
    }

    private function asksRequisitionCount(string $text): bool
    {
        return $this->isCountQuestion($text) && (str_contains($text, 'requisition') || str_contains($text, 'fund request'));
    }

    private function asksDocumentCount(string $text): bool
    {
        return $this->isCountQuestion($text) && (str_contains($text, 'document') || str_contains($text, 'file'));
    }

    private function asksUnreadNotificationCount(string $text): bool
    {
        return $this->isCountQuestion($text)
            && str_contains($text, 'unread')
            && (str_contains($text, 'notification') || str_contains($text, 'alert'));
    }

    private function asksPendingApprovalCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'pending') && str_contains($text, 'approval');
    }

    private function asksVatQuestion(string $text): bool
    {
        return (bool) preg_match('/\b(vat|tax rate|tax percentage)\b/i', $text)
            && (bool) preg_match('/\b(what|how much|rate|percentage|percent)\b/i', $text);
    }

    private function asksTaskWorkloadQuestion(string $text): bool
    {
        return (bool) preg_match('/\b(do i have|do we have|have i got|any|are there|what)\b/i', $text)
            && (bool) preg_match('/\b(open task|open tasks|pending task|pending tasks|pending work|work to do|todo|to do|assigned task|assigned tasks)\b/i', $text);
    }

    private function asksSalesHealth(string $text): bool
    {
        return (bool) preg_match('/\b(sales|quotation|quotations|pipeline|finance|revenue)\b/i', $text)
            && (bool) preg_match('/\b(how are|how is|performance|falling short|doing|gap|weak|short)\b/i', $text);
    }

    private function asksInvoiceGeneration(string $text): bool
    {
        return str_contains($text, 'invoice')
            && (bool) preg_match('/\b(why|not generated|not created|generated|created|converted|conversion|falling short|gap)\b/i', $text);
    }

    private function isCountQuestion(string $text): bool
    {
        return (bool) preg_match('/\b(how many|count|total number|number of)\b/i', $text);
    }

    private function dateTimeReply(): string
    {
        return 'Today is '.now()->format('l, F j, Y').', and the current time is '.now()->format('H:i').'.';
    }

    private function countReply(string $key, string $singular, array $crmContext): string
    {
        $count = (int) Arr::get($crmContext, "counts.{$key}", 0);
        $label = Str::plural($singular, $count);

        return "There ".($count === 1 ? 'is' : 'are')." {$count} {$label} in the CRM.";
    }

    private function quotationCountReply(array $crmContext): string
    {
        $salesQuotations = (int) Arr::get($crmContext, 'counts.sales_quotations', 0);
        $quotationRequests = (int) Arr::get($crmContext, 'counts.quotation_requests', 0);

        return "There are {$salesQuotations} sales {$this->plural('quotation', $salesQuotations)} and {$quotationRequests} quotation {$this->plural('request', $quotationRequests)} in the CRM.";
    }

    private function salesHealthReply(User $user): string
    {
        $sales = SalesQuotation::query()->visibleTo($user);
        $invoices = Invoice::query()->visibleTo($user);

        $salesCount = (clone $sales)->count();
        $invoiceCount = (clone $invoices)->count();
        $salesTotal = (float) (clone $sales)->sum('total');
        $invoiceTotal = (float) (clone $invoices)->sum('total');
        $unpaidInvoices = (clone $invoices)->where('balance_due', '>', 0)->count();
        $accepted = (clone $sales)->where('status', SalesQuotation::STATUS_ACCEPTED)->count();
        $converted = (clone $sales)->where('status', SalesQuotation::STATUS_CONVERTED)->count();
        $drafts = (clone $sales)->where('status', SalesQuotation::STATUS_DRAFT)->count();

        if ($salesCount === 0) {
            return 'There are no sales quotations recorded yet, so the sales pipeline has not started in the CRM.';
        }

        return "Sales show {$salesCount} {$this->plural('quotation', $salesCount)} worth {$this->money($salesTotal)}, "
            ."but only {$invoiceCount} {$this->plural('invoice', $invoiceCount)} worth {$this->money($invoiceTotal)}. "
            ."The shortfall is conversion: {$drafts} {$this->plural('quotation', $drafts)} are still drafts, "
            ."{$accepted} accepted {$this->plural('quotation', $accepted)} need follow-through, and {$converted} have been converted. "
            ."Unpaid invoices are {$unpaidInvoices}, so collections are not the main issue right now.";
    }

    private function invoiceGenerationReply(User $user): string
    {
        $sales = SalesQuotation::query()->visibleTo($user);
        $invoices = Invoice::query()->visibleTo($user);

        $salesCount = (clone $sales)->count();
        $invoiceCount = (clone $invoices)->count();
        $withoutInvoice = (clone $sales)->whereDoesntHave('invoice')->count();
        $drafts = (clone $sales)->where('status', SalesQuotation::STATUS_DRAFT)->count();
        $approvedOrAccepted = (clone $sales)->whereIn('status', [
            SalesQuotation::STATUS_APPROVED,
            SalesQuotation::STATUS_SENT,
            SalesQuotation::STATUS_ACCEPTED,
        ])->count();
        $converted = (clone $sales)->where('status', SalesQuotation::STATUS_CONVERTED)->count();

        if ($salesCount === 0) {
            return 'No invoices have been generated because there are no sales quotations recorded yet.';
        }

        return "Invoices are low because quotation conversion is low: the CRM has {$salesCount} sales {$this->plural('quotation', $salesCount)} "
            ."but {$invoiceCount} {$this->plural('invoice', $invoiceCount)}. {$withoutInvoice} {$this->plural('quotation', $withoutInvoice)} "
            ."still have no linked invoice. Usually the next step is to move approved or accepted quotations into invoicing; "
            ."right now {$approvedOrAccepted} are in that follow-up zone, {$drafts} are still drafts, and {$converted} are already converted.";
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function conversationText(array $history, string $message): string
    {
        $previous = collect($history)
            ->take(-4)
            ->pluck('content')
            ->implode(' ');

        return Str::of($previous.' '.$message)->lower()->squish()->toString();
    }

    private function firstName(User $user): string
    {
        return Str::of($user->name)->before(' ')->toString();
    }

    private function money(float $amount): string
    {
        return 'E'.number_format($amount, 2);
    }

    private function plural(string $word, int $count): string
    {
        return Str::plural($word, $count);
    }

    private function normalize(string $text): string
    {
        return Str::of($text)->lower()->squish()->toString();
    }
}
