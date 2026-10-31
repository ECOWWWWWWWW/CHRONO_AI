<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Services\ChatService;
use Illuminate\Http\Request;
use App\Exceptions\Gemini\GeminiException;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    /**
     * Create a new chat session (e.g., start a new lesson plan).
     */
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'grade_level' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $session = ChatSession::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'active',
        ]);

        return response()->json($session, 201);
    }

    /**
     * List all active sessions for the current user.
     */
    public function indexSessions(Request $request)
    {
        $sessions = ChatSession::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($sessions);
    }

    /**
     * Get messages for a specific session.
     */
    public function showMessages(Request $request, ChatSession $session)
    {
        // Security: Ensure ownership
        if ($session->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to chat session.');
        }

        return response()->json([
            'session' => $session,
            'messages' => $this->chatService->getHistory($session),
        ]);
    }

    /**
     * Send a message within a session.
     */
    public function sendMessage(Request $request, ChatSession $session)
    {
        // Security: Ensure ownership
        if ($session->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to chat session.');
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:5000',
        ]);

        try {
            $message = $this->chatService->sendMessage(
                session: $session,
                prompt: $validated['prompt'],
                userId: $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (GeminiException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => class_basename($e),
            ], $e->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
