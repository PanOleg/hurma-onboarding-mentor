<?php

namespace App\Chat;

use App\Chat\Contracts\LlmClient;
use App\Chat\Enums\MessageStatus;
use App\Chat\Llm\LlmException;
use App\Chat\Llm\PromptBuilder;
use App\Chat\Models\Conversation;
use App\Chat\Models\Message;
use App\Insights\KnowledgeGapRecorder;
use App\Knowledge\Embedding\EmbeddingException;
use App\Retrieval\ChunkSearchRepository;
use App\Retrieval\QueryEmbeddingCache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AnswerService
{
    public const NO_ANSWER_TEXT = 'У базі знань компанії немає інформації з цього питання. Я передав його HR, щоб доповнити документи.';

    public function __construct(
        private readonly QueryEmbeddingCache $embeddings,
        private readonly ChunkSearchRepository $search,
        private readonly PromptBuilder $prompts,
        private readonly LlmClient $llm,
        private readonly CitationParser $citations,
        private readonly KnowledgeGapRecorder $gaps,
    ) {}

    public function answer(Conversation $conversation, string $content, SseWriter $sse): Message
    {
        $startedAt = hrtime(true);
        $history = $conversation->messages()->whereIn('status', ['completed', 'no_answer'])->reorder('id', 'desc')->limit(6)->get()->reverse()
            ->map(fn (Message $m) => ['role' => $m->role, 'content' => $m->content])->values()->all();

        $conversation->messages()->create(['role' => 'user', 'content' => $content, 'status' => MessageStatus::Completed]);
        $assistant = $conversation->messages()->create(['role' => 'assistant', 'content' => '', 'status' => MessageStatus::Streaming]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        $sse->event('message', ['id' => $assistant->id, 'conversation_id' => $conversation->id]);

        try {
            $question = $content;
            if ($history !== []) {
                $rewrite = $this->prompts->rewrite($content, $history);
                $question = $this->llm->rewriteQuestion($rewrite['system'], $rewrite['user']);
                $assistant->rewritten_question = $question;
            }

            $vector = $this->embeddings->embedQuery($question);
            $hits = $this->search->search($vector, $conversation->user, (int) config('rag.top_k'), (int) config('rag.candidate_limit'));
            $best = $hits === [] ? null : $hits[0]->distance;
            $assistant->best_distance = $best;

            if ($best === null || $best > (float) config('rag.max_distance')) {
                $sse->event('token', ['text' => self::NO_ANSWER_TEXT]);
                $sse->event('citations', []);
                $assistant->forceFill(['content' => self::NO_ANSWER_TEXT, 'status' => MessageStatus::NoAnswer, 'latency_ms' => $this->ms($startedAt)])->save();
                $this->gaps->recordNoAnswer($question);
                $sse->event('done', ['status' => 'no_answer', 'grounded' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'latency_ms' => $assistant->latency_ms]);

                return $assistant;
            }

            $prompt = $this->prompts->answer($question, $hits);
            $result = $this->llm->streamAnswer($prompt['system'], $prompt['user'], fn (string $delta) => $sse->event('token', ['text' => $delta]));

            $parsed = $this->citations->parse($result->text, $hits);
            if ($parsed['invalidMarkers'] !== []) {
                Log::warning('rag.invalid_citation_markers', ['message_id' => $assistant->id, 'markers' => $parsed['invalidMarkers']]);
            }
            $payload = [];
            foreach ($parsed['citations'] as $c) {
                $assistant->citations()->create(['marker' => $c->marker, 'chunk_id' => $c->hit->chunkId, 'quote' => mb_substr($c->hit->content, 0, 500)]);
                $payload[] = ['marker' => $c->marker, 'chunk_id' => $c->hit->chunkId, 'document_id' => $c->hit->documentId,
                    'document_title' => $c->hit->documentTitle, 'page' => $c->hit->page, 'quote' => mb_substr($c->hit->content, 0, 500)];
            }
            $sse->event('citations', $payload);

            $grounded = $parsed['citations'] !== [];
            if ($grounded) {
                $g = $this->prompts->grounding($result->text, $hits);
                $grounded = $this->llm->checkGrounding($g['system'], $g['user'])->grounded;
            }
            if (! $grounded) {
                $this->gaps->recordNeedsReview($question);
            }

            $assistant->forceFill([
                'content' => $result->text, 'status' => MessageStatus::Completed, 'grounded' => $grounded, 'model' => $result->model,
                'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'latency_ms' => $this->ms($startedAt),
            ])->save();
            $sse->event('done', ['status' => 'completed', 'grounded' => $grounded, 'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens, 'latency_ms' => $assistant->latency_ms]);
        } catch (LlmException|EmbeddingException $e) {
            $this->fail($assistant, $sse, $e->code_, $e->getMessage(), $startedAt);
        } catch (Throwable $e) {
            Log::error('rag.answer_failed', ['message_id' => $assistant->id, 'exception' => $e]);
            $this->fail($assistant, $sse, 'internal_error', 'Внутрішня помилка.', $startedAt);
        }

        return $assistant;
    }

    private function fail(Message $assistant, SseWriter $sse, string $code, string $detail, int $startedAt): void
    {
        $assistant->forceFill(['status' => MessageStatus::Failed, 'latency_ms' => $this->ms($startedAt)])->save();
        $sse->event('error', ['code' => $code, 'message' => $this->userMessage($code), 'detail' => mb_substr($detail, 0, 300)]);
    }

    private function userMessage(string $code): string
    {
        return match ($code) {
            'llm_unavailable', 'embedder_unavailable', 'embedder_bad_dimension' => 'Сервіс тимчасово недоступний, спробуйте ще раз за хвилину.',
            'llm_refusal' => 'Модель відмовилась відповідати на це питання.',
            'llm_truncated' => 'Відповідь обірвалась, спробуйте уточнити питання.',
            default => 'Щось пішло не так. Ми вже дивимось.',
        };
    }

    private function ms(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
