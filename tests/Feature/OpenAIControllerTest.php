<?php

namespace Tests\Feature;

use App\Services\Gemini\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIControllerTest extends TestCase
{
    public function test_exponential_backoff_retries_on_failure_then_succeeds()
    {
        Http::fakeSequence()
            ->pushStatus(503)
            ->pushStatus(503)
            ->push([
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Success after backoff!',
                            ],
                        ],
                    ],
                ],
            ], 200);

        $response = $this->postJson('/test', [
            'prompt' => 'Create a lesson plan',
            'history' => [],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Success after backoff!',
            ]);

        Http::assertSentCount(3);
    }

    public function test_it_does_not_retry_on_400_bad_request()
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Invalid prompt',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $response = $this->postJson('/test', [
            'prompt' => 'Invalid prompt that triggers safety filter',
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('exception', 'GeminiRequestFailedException')
            ->assertJsonPath('details.upstream_status', 400);

        Http::assertSentCount(1);
    }

    public function test_service_can_inject_rag_context_into_system_instruction()
    {
        Http::fake([
            '*' => Http::response([
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Grounded answer.',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(GeminiService::class);

        $reply = $service->generate(
            prompt: 'Create a lesson plan for Chapter 4.',
            history: [],
            context: 'Chapter 4 covers the water cycle, including evaporation, condensation, and precipitation.'
        );

        $this->assertSame('Grounded answer.', $reply);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return isset($payload['system_instruction'])
                && str_contains($payload['system_instruction'], '[RETRIEVED CURRICULUM CONTEXT]')
                && str_contains($payload['system_instruction'], 'Chapter 4 covers the water cycle');
        });
    }
}
