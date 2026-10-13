<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Returns the conversations of the logged-in user.
     */
    public function index()
    {
        $conversations = Conversation::query()
            ->where('user_id', auth()->id())
            ->select('id', 'title', 'created_at', 'updated_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Returns a user's conversation along with its messages.
     */
    public function show($id)
    {
        $conversation = Conversation::query()
            ->where('user_id', auth()->id())
            ->with([
                'messages' => function ($query) {
                    $query->orderBy('id');
                },
            ])
            ->findOrFail($id);

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    /**
     * Saves new messages and requests a response from Gemini.
     */
    public function send(Request $request)
    {
        set_time_limit(90);

        $validated = $request->validate([
            'conversation_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'messages' => ['required', 'array', 'min:1', 'max:50'],
            'messages.*.role' => [
                'required',
                'in:user,assistant',
            ],
            'messages.*.content' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            return response()->json([
                'message' => 'The Gemini API key has not been set in .env.',
            ], 500);
        }

        /*
         * Find a conversation only from those of the logged-in user
         * or create a new conversation with their user_id.
         */
        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::query()
                ->where('user_id', auth()->id())
                ->findOrFail($validated['conversation_id']);
        } else {
            $firstUserMessage = collect($validated['messages'])
                ->first(function ($message) {
                    return $message['role'] === 'user';
                });

            if (!$firstUserMessage) {
                return response()->json([
                    'message' => 'No user message was found.',
                ], 422);
            }

            $title = trim($firstUserMessage['content']);

            $conversation = Conversation::create([
                'title' => mb_substr($title, 0, 60),
                'user_id' => auth()->id(),
            ]);
        }

        /*
         * The frontend sends the entire history.
         * We only save messages that don't already exist.
         */
        $storedCount = $conversation->messages()->count();
        $incomingMessages = $validated['messages'];
        $incomingCount = count($incomingMessages);

        if ($storedCount > $incomingCount) {
            return response()->json([
                'message' => 'The conversation history is not synchronized.',
            ], 409);
        }

        for ($i = $storedCount; $i < $incomingCount; $i++) {
            $message = $incomingMessages[$i];

            $conversation->messages()->create([
                'role' => $message['role'],
                'content' => $message['content'],
            ]);
        }

        $conversation->touch();

        /*
         * Preparing the messages for Gemini.
         */
        $contents = [];

        foreach ($validated['messages'] as $message) {
            $role = $message['role'] === 'assistant'
                ? 'model'
                : 'user';

            // Gemini requires role alternation.
            if (
                !empty($contents) &&
                end($contents)['role'] === $role
            ) {
                $lastIndex = array_key_last($contents);

                $contents[$lastIndex]['parts'][0]['text']
                    .= "\n\n" . $message['content'];

                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $message['content']],
                ],
            ];
        }

        // Gemini must receive at least one user message.
        if (
            empty($contents) ||
            !in_array('user', array_column($contents, 'role'), true)
        ) {
            return response()->json([
                'message' => 'No user message was found.',
            ], 422);
        }

        try {
            $response = Http::connectTimeout(10)
                ->timeout(75)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent',
                    [
                        'contents' => $contents,
                        'generationConfig' => [
                            'maxOutputTokens' => 1024,
                            'thinkingConfig' => [
                                'thinkingLevel' => 'low',
                            ],
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'message' => 'Gemini could not respond. Check the API key and available usage limit.',
                ], 502);
            }

            $answer = $response->json(
                'candidates.0.content.parts.0.text'
            );

            if (!is_string($answer) || trim($answer) === '') {
                return response()->json([
                    'message' => 'Gemini returned an empty response.',
                ], 502);
            }

            /*
             * Saving Gemini's response.
             */
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $answer,
            ]);

            $conversation->touch();

            return response()->json([
                'reply' => $answer,
                'conversation_id' => $conversation->id,
                'title' => $conversation->title,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gemini connection error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not communicate with Gemini.',
            ], 502);
        }
    }

    /**
     * Deletes a conversation only if it belongs to the logged-in user.
     */
    public function destroy($id)
    {
        $conversation = Conversation::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'The conversation was successfully deleted.',
        ]);
    }
}