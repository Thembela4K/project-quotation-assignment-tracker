<?php

namespace App\Services\Assistant;

use App\Models\AiActionLog;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Str;

class OperationsAssistantService
{
    public function __construct(
        private readonly RemoteAssistantProvider $remoteAssistantProvider,
        private readonly AssistantCrmContextService $crmContext,
        private readonly AssistantActionExecutor $actionExecutor,
        private readonly LocalAssistantResponder $localAssistantResponder,
    ) {
    }

    public function handle(User $user, string $message, ?int $conversationId = null): array
    {
        $conversation = $this->conversation($user, $message, $conversationId);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $history = $this->conversationContext($conversation);
        $crmContext = $this->contextForMessage($user, $message);
        $result = $this->localAssistantResponder->navigation($user, $message, $history);

        if (! $result) {
            $result = $this->localAssistantResponder->answer($user, $message, $crmContext, $history);
        }

        if (! $result) {
            $completion = $this->remoteAssistantProvider->complete(
                $user,
                $history,
                $crmContext,
            );

            $result = $this->assistantResult($completion, $message, $user, $crmContext, $history);
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['reply'],
            'metadata' => [
                'action' => $result['action'],
                'filters' => $result['filters'] ?? [],
                'assistant_mode' => $result['assistant_mode'] ?? 'local',
            ],
        ]);
        $conversation->touch();

        AiActionLog::query()->create([
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
            'intent' => $result['intent'] ?? null,
            'action_type' => $result['action']['type'] ?? 'reply',
            'status' => 'completed',
            'input' => ['message' => $message],
            'output' => $result,
            'route' => $result['action']['url'] ?? null,
        ]);

        return [
            'conversation_id' => $conversation->id,
            'reply' => $result['reply'],
            'action' => $result['action'],
        ];
    }

    public function startConversation(User $user): array
    {
        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => 'New MIS chat',
            'metadata' => [
                'provider' => 'local',
                'assistant' => 'mis',
            ],
        ]);

        return [
            'conversation_id' => $conversation->id,
            'messages' => [],
        ];
    }

    public function conversations(User $user): array
    {
        return [
            'conversations' => AiConversation::query()
                ->where('user_id', $user->id)
                ->with('latestMessage')
                ->withCount('messages')
                ->latest('updated_at')
                ->limit(20)
                ->get()
                ->map(function (AiConversation $conversation): array {
                    $latestMessage = $conversation->latestMessage;
                    $title = $conversation->title;

                    if ($latestMessage && $title === 'New MIS chat') {
                        $title = Str::limit($latestMessage->content, 70);
                    }

                    return [
                        'id' => $conversation->id,
                        'title' => $title,
                        'message_count' => $conversation->messages_count,
                        'latest_message' => $latestMessage?->content,
                        'updated_at' => $conversation->updated_at?->toIso8601String(),
                    ];
                })
                ->all(),
        ];
    }

    public function history(User $user, ?int $conversationId = null): array
    {
        $query = AiConversation::query()->where('user_id', $user->id);
        $conversation = $conversationId
            ? $query->find($conversationId)
            : $query->latest('updated_at')->first();

        if (! $conversation) {
            return [
                'conversation_id' => null,
                'messages' => [],
            ];
        }

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'conversation_id' => $conversation->id,
            'messages' => $messages,
        ];
    }

    private function conversation(User $user, string $message, ?int $conversationId): AiConversation
    {
        if ($conversationId) {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->find($conversationId);

            if ($conversation) {
                return $conversation;
            }
        }

        return AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => Str::limit($message, 90),
            'metadata' => [
                'provider' => 'local',
            ],
        ]);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function conversationContext(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function assistantResult(array $completion, string $message, User $user, array $crmContext, array $history): array
    {
        if (! ($completion['ok'] ?? false)) {
            $localFallback = $this->localAssistantResponder->answer($user, $message, $crmContext, $history);

            if ($localFallback && ($completion['status'] ?? null) !== 429) {
                return $localFallback;
            }

            return [
                'intent' => 'ai_unavailable',
                'reply' => $this->friendlyUnavailableReply($completion),
                'action' => null,
                'filters' => [],
                'assistant_mode' => 'remote_unavailable',
            ];
        }

        $requestedNavigation = $this->allowsNavigation($message);
        $action = $requestedNavigation
            ? $this->actionExecutor->execute($completion['action'] ?? null)
            : null;

        return [
            'intent' => $action ? 'ai_action' : 'ai_answer',
            'reply' => $completion['reply'],
            'action' => $action,
            'filters' => is_array($completion['action']['filters'] ?? null) ? $completion['action']['filters'] : [],
            'assistant_mode' => 'remote_ai',
        ];
    }

    private function friendlyUnavailableReply(array $completion): string
    {
        if (($completion['status'] ?? null) === 429) {
            return 'The live AI quota is currently exhausted. Please try again later.';
        }

        if (in_array($completion['status'] ?? null, [401, 403], true)) {
            return 'MIS cannot connect to the configured AI key right now. Please check the AI settings.';
        }

        return 'MIS is having trouble reaching the live AI service right now. Please try again, or ask a specific CRM question I can answer from local records.';
    }

    private function allowsNavigation(string $message): bool
    {
        $text = Str::of($message)->lower()->squish()->toString();

        if ($this->isCapabilityQuestion($text)) {
            return false;
        }

        if ($this->blocksNavigation($text)) {
            return false;
        }

        if ($this->isAnalysisQuestion($text)) {
            return false;
        }

        if ((bool) preg_match('/\b(open|go to|navigate to|take me to|bring up|pull up|send me to)\b/i', $text)) {
            return true;
        }

        if (! (bool) preg_match('/\b(show|list|view)\b/i', $text)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(tender|proposal|quotation request|request quotation|rfq|requisition|task|document|file|invoice|sales quotation|quote|job card|delivery note|client|customer|supplier|approval|attendance|report|notification|overdue|pending|submitted|unpaid|paid|due|approved|rejected|draft|finished|active|inactive|last month|this month|today|tomorrow|yesterday)\b/i',
            $text,
        );
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

    private function isCapabilityQuestion(string $text): bool
    {
        return (bool) preg_match(
            "/\\b(what can you do|what you can do|what u can do|what can'?t you do|what you can'?t do|what you cannot do|what are you capable of|things you can do|things you can'?t do|list of things you can|list of what you can|your capabilities|your limitations|do you have (?:the )?capabilit|are you able to|can you draft|can you create|can you make|can you edit|can you approve|can you delete|can you send|can you release|can you change|can you update|do you support)\\b/i",
            $text,
        );
    }

    private function contextForMessage(User $user, string $message): array
    {
        $sections = $this->recordSectionsForMessage($message);

        if ($sections === []) {
            return $this->crmContext->buildLight($user);
        }

        return $this->crmContext->buildScoped($user, $sections);
    }

    /**
     * @return array<int, string>
     */
    private function recordSectionsForMessage(string $message): array
    {
        $text = Str::of($message)->lower()->squish()->toString();

        if ($this->isLightAssistantQuestion($text)) {
            return [];
        }

        $sections = [];
        $map = [
            'clients' => ['client', 'customer', 'contact'],
            'suppliers' => ['supplier', 'vendor', 'procurement'],
            'tender_proposals' => ['tender', 'proposal', 'sppra', 'esppra'],
            'quotation_requests' => ['quotation request', 'request quotation', 'rfq', 'quote request'],
            'sales_quotations' => ['sales quotation', 'estimate', 'quote', 'quotation'],
            'job_cards' => ['job card', 'work card'],
            'delivery_notes' => ['delivery note', 'handover note'],
            'invoices' => ['invoice', 'payment', 'paid', 'unpaid', 'overdue invoice'],
            'requisitions' => ['requisition', 'funds', 'cash request', 'approval money'],
            'tasks' => ['task', 'workload', 'assignment'],
            'documents' => ['document', 'file', 'attachment', 'download', 'submitted document'],
            'notifications' => ['notification', 'alert', 'unread'],
        ];

        foreach ($map as $section => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $sections[] = $section;
                    break;
                }
            }
        }

        if (str_contains($text, 'approval') || str_contains($text, 'approve')) {
            $sections[] = 'sales_quotations';
            $sections[] = 'requisitions';
        }

        if (str_contains($text, 'submitted') || str_contains($text, 'submission')) {
            $sections[] = 'documents';
            $sections[] = 'tender_proposals';
            $sections[] = 'quotation_requests';
        }

        if ($this->isCountQuestion($text) && ! $this->hasStatusModifier($text)) {
            return [];
        }

        return array_values(array_unique($sections));
    }

    private function isLightAssistantQuestion(string $text): bool
    {
        return $text === 'help'
            || $this->isCapabilityQuestion($text)
            || (bool) preg_match('/\b(hi|hello|hey|how are you|who are you|what are you|your name|tell me who you are|what can you do|today|date|time)\b/i', $text);
    }

    private function isCountQuestion(string $text): bool
    {
        return (bool) preg_match('/\b(how many|count|total number|number of)\b/i', $text);
    }

    private function hasStatusModifier(string $text): bool
    {
        return (bool) preg_match('/\b(overdue|pending|submitted|unpaid|paid|due|approved|rejected|draft|finished|active|inactive|late|closed|open)\b/i', $text);
    }
}
