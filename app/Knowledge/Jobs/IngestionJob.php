<?php

namespace App\Knowledge\Jobs;

use App\Knowledge\Embedding\EmbeddingException;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Knowledge\Enums\IngestionStep;
use App\Knowledge\Extraction\ExtractionException;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\IngestionRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

abstract class IngestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 150;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    protected ?IngestionRun $run = null;

    public function __construct(public int $documentId)
    {
        $this->onQueue('ingestion');
    }

    abstract protected function step(): IngestionStep;

    abstract protected function statusWhileRunning(): DocumentStatus;

    /** Returns null when the job must exit silently (deleted, or already past this step). */
    protected function begin(): ?Document
    {
        $document = Document::query()->find($this->documentId);
        if ($document === null || $document->status->order() > $this->statusWhileRunning()->order()) {
            return null;
        }
        $document->forceFill(['status' => $this->statusWhileRunning()])->save();
        $this->run = $document->ingestionRuns()->create([
            'step' => $this->step(), 'status' => 'running', 'attempt' => $this->attempts() ?: 1, 'started_at' => now(),
        ]);

        return $document;
    }

    /** Closes this attempt's run row as failed with its own error, then rethrows so the queue can retry. */
    protected function guarded(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            $this->run?->forceFill(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();
            throw $e;
        }
    }

    /** @param  array<string, mixed>  $extra */
    protected function finish(Document $document, DocumentStatus $next, array $extra = []): void
    {
        $this->run?->forceFill(['status' => 'done', 'finished_at' => now()])->save();
        $document->forceFill(array_merge(['status' => $next], $extra))->save();
    }

    public function failed(Throwable $e): void
    {
        $document = Document::query()->find($this->documentId);
        if ($document === null) {
            return;
        }
        // defensive: attempts normally close their own row in guarded()
        $document->ingestionRuns()->where('status', 'running')->update([
            'status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
        $document->markFailed($this->failureCode($e), $e->getMessage());
    }

    protected function failureCode(Throwable $e): FailureCode
    {
        return match (true) {
            $e instanceof NoTextLayerException => FailureCode::NoTextLayer,
            $e instanceof ExtractionException && $e->code_ === ExtractionException::UNSUPPORTED => FailureCode::UnsupportedFormat,
            $e instanceof ExtractionException => FailureCode::ExtractorUnavailable,
            $e instanceof EmbeddingException => FailureCode::EmbedderUnavailable,
            default => FailureCode::Unknown,
        };
    }
}
