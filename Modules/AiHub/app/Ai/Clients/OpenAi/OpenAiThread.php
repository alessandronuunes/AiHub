<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Illuminate\Support\Facades\Log;
use Modules\AiHub\Ai\Contracts\Thread;
use OpenAI\Client;
use RuntimeException;

// For polling

class OpenAiThread implements Thread
{
    protected Client $client;

    protected ?string $companySlug;

    /**
     * Constructor.
     *
     * @param  Client  $client  OpenAI SDK client instance.
     * @param  string|null  $companySlug  Company slug for context.
     */
    public function __construct(Client $client, ?string $companySlug = null)
    {
        $this->client = $client;
        $this->companySlug = $companySlug;
    }

    /**
     * Creates a new thread.
     *
     * @param  array  $params  Optional parameters for thread creation.
     * @return object API response with the created thread.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function create(array $params = []): object
    {
        try {
            $thread = $this->client->threads()->create($params);
            Log::info("OpenAI Thread created: {$thread->id}");

            return $thread;
        } catch (\Exception $e) {
            Log::error('Error creating OpenAI thread: '.$e->getMessage());
            throw new RuntimeException('Failed to create OpenAI thread.', 0, $e);
        }
    }

    /**
     * Retrieves an existing thread.
     *
     * @param  string  $threadId  Thread ID.
     * @return object API response with thread details.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function retrieve(string $threadId): object
    {
        try {
            $thread = $this->client->threads()->retrieve($threadId);

            return $thread;
        } catch (\Exception $e) {
            Log::error("Error retrieving OpenAI thread {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Failed to retrieve OpenAI thread.', 0, $e);
        }
    }

    /**
     * Adds a message to a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $content  Message content.
     * @param  string  $role  Message role (e.g., 'user', 'assistant').
     * @param  array  $params  Additional parameters for the message.
     * @return object API response with the created message.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function addMessage(string $threadId, string $content, string $role = 'user', array $params = []): object
    {
        try {
            $message = $this->client->threads()->messages()->create($threadId, [
                'role' => $role,
                'content' => $content,
                ...$params, // Merge additional parameters
            ]);
            Log::info("Message added to thread {$threadId}. Message ID: {$message->id}");

            return $message;
        } catch (\Exception $e) {
            Log::error("Error adding message to OpenAI thread {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Failed to add message to OpenAI thread.', 0, $e);
        }
    }

    /**
     * Lists the messages of a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @param  array  $params  Listing parameters (limit, order, after, before).
     * @return object API response with the list of messages.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function listMessages(string $threadId, array $params = []): object
    {
        try {
            $messages = $this->client->threads()->messages()->list($threadId, $params);

            return $messages;
        } catch (\Exception $e) {
            Log::error("Error listing messages from OpenAI thread {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Failed to list messages from OpenAI thread.', 0, $e);
        }
    }

    /**
     * Runs the assistant in a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $assistantId  Assistant ID.
     * @param  array  $params  Additional parameters for execution.
     * @return object API response with execution details.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function runAssistant(string $threadId, string $assistantId, array $params = []): object
    {
        try {
            $run = $this->client->threads()->runs()->create($threadId, [
                'assistant_id' => $assistantId,
                ...$params, // Merges additional parameters
            ]);
            Log::info("Execution started in thread {$threadId} with assistant {$assistantId}. Run ID: {$run->id}");

            return $run;
        } catch (\Exception $e) {
            Log::error("Error starting execution in OpenAI thread {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Failed to start execution in OpenAI thread.', 0, $e);
        }
    }

    /**
     * Retrieves the status of an execution.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $runId  Run ID.
     * @return object API response with execution details.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function retrieveRun(string $threadId, string $runId): object
    {
        try {
            $run = $this->client->threads()->runs()->retrieve($threadId, $runId);

            return $run;
        } catch (\Exception $e) {
            Log::error("Error retrieving OpenAI execution {$runId} for thread {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Failed to retrieve OpenAI execution.', 0, $e);
        }
    }

    /**
     * Waits for the completion of an execution and returns the last message from the assistant.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $runId  Run ID.
     * @param  int  $maxAttempts  Maximum number of attempts.
     * @param  int  $delay  Delay between attempts in seconds.
     * @return object|null API response with the last message or null if timeout.
     *
     * @throws RuntimeException If there is an API error during polling.
     */
    public function waitForResponse(string $threadId, string $runId, int $maxAttempts = 30, int $delay = 1): ?object
    {
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $run = $this->retrieveRun($threadId, $runId);

            switch ($run->status) {
                case 'completed':
                    Log::info("Execution {$runId} completed.");
                    // Retrieve messages after completion
                    $messages = $this->listMessages($threadId, ['order' => 'desc', 'limit' => 1]);

                    // Return the last message, which should be the assistant's response
                    return $messages->data[0] ?? null;
                case 'queued':
                case 'in_progress':
                case 'cancelling':
                    // Wait and try again
                    Log::debug("Execution {$runId} in status '{$run->status}'. Attempt {$attempts}/{$maxAttempts}.");
                    sleep($delay);
                    $attempts++;
                    break;
                case 'requires_action':
                    Log::warning("Execution {$runId} requires action (e.g., tool_calls). Status: {$run->status}.");

                    // Depending on your needs, you might want to handle tool_calls here
                    // For now, we'll just log and stop polling or continue waiting
                    // For this example, we'll stop polling and return null or the status
                    return $run; // Returns the run object so the caller can inspect it
                case 'cancelled':
                case 'failed':
                case 'expired':
                    Log::error("Execution {$runId} failed or was cancelled/expired. Status: {$run->status}.");
                    throw new RuntimeException("OpenAI execution failed with status: {$run->status}.");
            }
        }

        Log::warning("Polling for execution {$runId} reached the maximum number of attempts.");

        return null; // Timeout
    }

    /**
     * Deletes a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @return bool Returns true if deletion is successful.
     */
    public function delete(string $threadId): bool
    {
        try {
            $response = $this->client->threads()->delete($threadId);
            if ($response->deleted ?? false) {
                Log::info("OpenAI thread {$threadId} successfully deleted.");

                return true;
            }
            Log::warning("Failed to delete OpenAI thread {$threadId}. Response: ".json_encode($response));

            return false;
        } catch (\Exception $e) {
            Log::error("Error deleting OpenAI thread {$threadId}: ".$e->getMessage());

            return false;
        }
    }
}
