<?php

namespace Modules\AiHub\Ai\Contracts;

interface Thread
{
    /**
     * Creates a new thread.
     *
     * @param  array  $params  Optional parameters for thread creation.
     * @return object API response with the created thread.
     */
    public function create(array $params = []): object;

    /**
     * Retrieves an existing thread.
     *
     * @param  string  $threadId  Thread ID.
     * @return object API response with thread details.
     */
    public function retrieve(string $threadId): object;

    /**
     * Adds a message to a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $content  Message content.
     * @param  string  $role  Message role (e.g., 'user', 'assistant').
     * @param  array  $params  Additional parameters for the message.
     * @return object API response with the created message.
     */
    public function addMessage(string $threadId, string $content, string $role = 'user', array $params = []): object;

    /**
     * Lists messages in a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @param  array  $params  Listing parameters (limit, order, after, before).
     * @return object API response with the list of messages.
     */
    public function listMessages(string $threadId, array $params = []): object;

    /**
     * Runs the assistant on a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $assistantId  Assistant ID.
     * @param  array  $params  Additional parameters for execution.
     * @return object API response with execution details.
     */
    public function runAssistant(string $threadId, string $assistantId, array $params = []): object;

    /**
     * Retrieves the status of a run.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $runId  Run ID.
     * @return object API response with run details.
     */
    public function retrieveRun(string $threadId, string $runId): object;

    /**
     * Waits for the completion of a run and returns the assistant's last message.
     *
     * @param  string  $threadId  Thread ID.
     * @param  string  $runId  Run ID.
     * @param  int  $maxAttempts  Maximum number of attempts.
     * @param  int  $delay  Delay between attempts in seconds.
     * @return object|null API response with the last message or null if timeout.
     */
    public function waitForResponse(string $threadId, string $runId, int $maxAttempts = 30, int $delay = 1): ?object;

    /**
     * Deletes a thread.
     *
     * @param  string  $threadId  Thread ID.
     * @return bool Returns true if deletion is successful.
     */
    public function delete(string $threadId): bool;
}
