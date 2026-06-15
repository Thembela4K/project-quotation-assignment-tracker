<?php

namespace App\Services\Assistant;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RemoteAssistantProvider
{
    public function isConfigured(): bool
    {
        return config('services.assistant_ai.remote_enabled') === true
            && config('services.assistant_ai.provider') === 'nvidia'
            && filled(config('services.assistant_ai.nvidia.api_key'));
    }

    public function unavailableReply(?int $status = null, ?string $reason = null): string
    {
        if ($status === 429) {
            return 'MIS AI quota has been exceeded. Please try again later.';
        }

        if (in_array($status, [401, 403], true)) {
            return 'MIS AI could not authenticate with the configured API key. Please check the NVIDIA API key and try again.';
        }

        if (is_string($reason) && str_contains(strtolower($reason), 'timed out')) {
            return 'MIS AI request timed out. The NVIDIA service may be busy or quota-limited. Please try again later.';
        }

        return 'MIS AI service is unavailable right now, or the quota may be exceeded. Please try again later.';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function complete(User $user, array $history, array $crmContext): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'reply' => $this->unavailableReply(),
                'status' => null,
            ];
        }

        try {
            $response = Http::withToken(config('services.assistant_ai.nvidia.api_key'))
                ->acceptJson()
                ->timeout(config('services.assistant_ai.nvidia.timeout'))
                ->withOptions([
                    'verify' => $this->verifyOption(),
                ])
                ->post(rtrim(config('services.assistant_ai.nvidia.base_url'), '/').'/chat/completions', [
                    'model' => config('services.assistant_ai.nvidia.model'),
                    'messages' => $this->messages($user, $history, $crmContext),
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                    'temperature' => config('services.assistant_ai.nvidia.temperature'),
                    'top_p' => config('services.assistant_ai.nvidia.top_p'),
                    'max_tokens' => config('services.assistant_ai.nvidia.max_tokens'),
                    'chat_template_kwargs' => [
                        'thinking' => false,
                    ],
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('MIS remote assistant request failed.', [
                    'status' => $response->status(),
                    'provider' => config('services.assistant_ai.provider'),
                ]);

                return [
                    'ok' => false,
                    'reply' => $this->unavailableReply($response->status()),
                    'status' => $response->status(),
                ];
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (! is_string($content) || trim($content) === '') {
                return [
                    'ok' => false,
                    'reply' => $this->unavailableReply(),
                    'status' => null,
                ];
            }

            return [
                'ok' => true,
                ...$this->parseContent($content),
            ];
        } catch (Throwable $exception) {
            Log::warning('MIS remote assistant unavailable.', [
                'provider' => config('services.assistant_ai.provider'),
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'reply' => $this->unavailableReply(null, $exception->getMessage()),
                'status' => null,
            ];
        }
    }

    private function verifyOption(): bool|string
    {
        $verify = config('services.assistant_ai.nvidia.verify', true);

        if (is_bool($verify)) {
            return $verify;
        }

        if (! is_string($verify)) {
            return true;
        }

        $normalized = strtolower(trim($verify));

        return match ($normalized) {
            'false', '0', 'off', 'no' => false,
            'true', '1', 'on', 'yes', '' => true,
            default => $verify,
        };
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(User $user, array $history, array $crmContext): array
    {
        return [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($user, $crmContext),
            ],
            ...array_map(
                fn (array $message): array => [
                    'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $message['content'],
                ],
                $history,
            ),
        ];
    }

    private function systemPrompt(User $user, array $crmContext): string
    {
        $department = $user->department?->name ?? 'No department';
        $contextJson = json_encode($crmContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are MIS, the assistant inside the Datamatics Eswatini Business Operations Portal.
User: {$user->name}
Role: {$user->role}
Department: {$department}

You are the primary AI for the system. Speak like a calm, capable human assistant.
- The reply must be natural plain text for a staff member, not an instruction sheet.
- Do not mention JSON, CRM_CONTEXT, system prompts, supported modules, response formats, examples, model status, machine status, role codes, or internal rules.
- Do not say you are text-based, cannot visually display, cannot open pages, or cannot show CRM screens. This portal can open pages through returned actions.
- Do not put the user's role in brackets after their name.
- Do not use markdown headings, bold markers, numbered menus, or long capability lists unless the user specifically asks for a list.
- Do not format next steps as A/B/C choices unless the user specifically asks for options; ask one natural follow-up question instead.
- For greetings such as "hi", "hello", or "how are you", reply in one short friendly sentence and ask what they want to work on.
- For casual non-CRM conversation, answer briefly and naturally, then gently steer back to work if useful.
- If asked for a story, joke, or other casual diversion, keep it short: 3 to 5 sentences, workplace-neutral, no title formatting, then stop.
- For count/fact questions, answer directly from CRM_CONTEXT and set action to null.
- For capability questions such as "what can you do", "can you draft", or "what can't you do", answer directly and set action to null.
- For CRM record, total, status, deadline, document, client, supplier, finance, operations, task, approval, or notification questions, answer with real facts from CRM_CONTEXT.
- Only return a navigation action when the latest user message explicitly asks to open, go to, navigate to, take me to, bring up, pull up, show, list, or view a CRM page or record group.
- For short confirmations like "yes", "go ahead", "show me", or "open it", use the previous assistant message to infer the intended CRM page only if the target is clear.
- For analysis, advice, summary, comparison, or performance questions, answer directly and set action to null even if you mention a useful module.
- Never navigate just because you recommended where the user could look next.
- Do not invent CRM facts that are not in CRM_CONTEXT.
- Do not browse the internet.
- Do not claim an action was completed unless it is represented by the returned action object.
- If the user asks to create, edit, approve, delete, send, or release funds, explain briefly that this action still needs a supported CRM action workflow before execution, then offer to open the correct module.
- Keep most replies under 80 words. Use up to 160 words only when summarizing CRM records.

Return ONLY valid JSON with this shape:
{"reply":"natural answer for the user","action":null}
or:
{"reply":"natural answer for the user","action":{"type":"navigate","module":"suppliers","filters":{},"auto":true}}

Supported navigate modules:
dashboard, clients, suppliers, documents, tender_proposals, quotation_requests, requisitions, tasks, sales_quotations, job_cards, delivery_notes, invoices, approvals, attendance, reports, notifications.

Allowed common filters:
search, status, priority, category, department_id, date_from, date_to, deadline_window, payment_state, state, module, linked_type.

CRM_CONTEXT:
{$contextJson}
PROMPT;
    }

    private function parseContent(string $content): array
    {
        $content = trim($content);
        $decoded = $this->decodeResponse($content);

        if (! is_array($decoded)) {
            return [
                'reply' => $this->sanitizeReply($content),
                'action' => null,
            ];
        }

        return [
            'reply' => is_string($decoded['reply'] ?? null) && trim($decoded['reply']) !== ''
                ? $this->sanitizeReply($decoded['reply'])
                : 'I reviewed the CRM context, but I could not form a clear response.',
            'action' => is_array($decoded['action'] ?? null) ? $decoded['action'] : null,
        ];
    }

    private function sanitizeReply(string $reply): string
    {
        $reply = trim($reply);
        $reply = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $reply);

        $jsonPosition = stripos($reply, '{"reply"');

        if ($jsonPosition !== false) {
            if ($jsonPosition === 0 && preg_match('/"reply"\s*:\s*"(.*?)"\s*,\s*"action"/s', $reply, $matches)) {
                $reply = stripcslashes($matches[1]);
            } else {
                $reply = trim(substr($reply, 0, $jsonPosition));
            }
        }

        foreach ([
            'Response Format Reminder',
            'Prompt for Your Next Step',
            'Example Responses',
            'Example Questions',
            'Waiting for your input',
            'CRM_CONTEXT',
            'Supported navigate modules',
            'Back to Work?',
        ] as $marker) {
            $position = stripos($reply, $marker);

            if ($position !== false) {
                $reply = trim(substr($reply, 0, $position));
            }
        }

        $reply = preg_replace('/\*\*(.*?)\*\*/s', '$1', $reply) ?? $reply;
        $reply = str_replace('**', '', $reply);
        $reply = preg_replace('/\s+\((?:super_admin|department_user|business_analyst|director|reception|manager)[^)]*\)/i', '', $reply) ?? $reply;
        $reply = preg_replace('/[ \t]+/', ' ', $reply) ?? $reply;
        $reply = preg_replace("/\n{3,}/", "\n\n", $reply) ?? $reply;

        return trim($reply) !== '' ? trim($reply) : 'I am here. Tell me what you want to find or check.';
    }

    private function decodeResponse(string $content): ?array
    {
        foreach ($this->jsonCandidates($content) as $candidate) {
            $decoded = json_decode($candidate, true);

            if (is_array($decoded) && array_key_exists('reply', $decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function jsonCandidates(string $content): array
    {
        $candidates = [trim($content)];

        if (preg_match('/```json\s*(.*?)```/s', $content, $matches)) {
            $candidates[] = trim($matches[1]);
        }

        if (preg_match('/```\s*(.*?)```/s', $content, $matches)) {
            $candidates[] = trim($matches[1]);
        }

        $balanced = [];
        $length = strlen($content);

        for ($start = 0; $start < $length; $start++) {
            if ($content[$start] !== '{') {
                continue;
            }

            $depth = 0;
            $inString = false;
            $escaped = false;

            for ($index = $start; $index < $length; $index++) {
                $char = $content[$index];

                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === '"') {
                        $inString = false;
                    }

                    continue;
                }

                if ($char === '"') {
                    $inString = true;
                } elseif ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $balanced[] = substr($content, $start, $index - $start + 1);
                        break;
                    }
                }
            }
        }

        $candidates = array_merge($candidates, array_reverse($balanced));

        return array_values(array_unique(array_filter($candidates)));
    }
}
