<?php

namespace App\Chat\Http;

use App\Chat\AnswerService;
use App\Chat\Models\Conversation;
use App\Chat\SseWriter;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation, AnswerService $service): StreamedResponse
    {
        $content = $request->string('content')->toString();

        return response()->stream(function () use ($conversation, $content, $service) {
            // Tests capture the stream through their own output buffer, which must stay intact.
            while (! app()->runningUnitTests() && ob_get_level() > 0) {
                ob_end_flush();
            }
            $writer = new SseWriter(function (string $frame) {
                if (connection_aborted()) {
                    return;
                }
                echo $frame;
                flush();
            });
            $service->answer($conversation, $content, $writer);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
