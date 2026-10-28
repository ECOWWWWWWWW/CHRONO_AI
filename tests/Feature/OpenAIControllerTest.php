<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenAIControllerTest extends TestCase
{
    public function test_exponential_backoff_retries_on_failure_then_succeeds()
    {
        // 1. Fake the HTTP client to fail twice, then succeed on the 3rd try
        Http::fakeSequence()
            ->pushStatus(503) // Attempt 1 fails
            ->pushStatus(503) // Attempt 2 fails
            ->push([          // Attempt 3 succeeds
                'steps' => [
                    ['type' => 'model_output', 'content' => [['text' => 'Success after backoff!']]]
                ]
            ], 200);

        // 2. Make the request to the exact route we defined in web.php
        $response = $this->postJson('/test', [
            'prompt' => 'Create a lesson plan',
            'history' => []
        ]);

        // 3. Assert it eventually succeeded
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Success after backoff!'
            ]);

        // 4. Assert that Laravel actually made 3 requests (1 initial + 2 retries)
        Http::assertSentCount(3);
    }

    public function test_it_does_not_retry_on_400_bad_request()
    {
        // Fake a 400 error (e.g., invalid prompt)
        Http::fake([
            '*' => Http::response(['error' => 'Invalid prompt'], 400)
        ]);

        $response = $this->postJson('/test', [
            'prompt' => 'Invalid prompt that triggers safety filter',
        ]);

        // Your controller returns 500 when $response->failed() is true
        $response->assertStatus(500);

        // Assert it was ONLY called ONCE. If it retried, this would fail.
        Http::assertSentCount(1);
    }
}
