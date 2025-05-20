<?php

namespace Modules\AiHub\Ai\Contracts;

interface Assistant
{
    /**
     * Creates a new assistant.
     *
     * @param  array  $params  Parameters for creating the assistant.
     * @return object API response with the created assistant.
     */
    public function create(array $params): object;

    /**
     * Modifies an existing assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @param  array  $params  Parameters for modification.
     * @return object API response.
     */
    public function modify(string $assistantId, array $params): object;

    /**
     * Deletes an assistant.
     *
     * @param  string  $assistantId  ID of the assistant to be deleted.
     * @return bool Returns true if deletion is successful.
     */
    public function delete(string $assistantId): bool;

    /**
     * Adds a file to an existing assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @param  string  $fileId  File ID.
     * @return object API response.
     */
    public function addFile(string $assistantId, string $fileId): object;

    /**
     * Removes a file from an existing assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @param  string  $fileId  File ID.
     * @return bool Returns true if removal is successful.
     */
    public function removeFile(string $assistantId, string $fileId): bool;

    /**
     * Lists files associated with an assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @return object API response with the list of files.
     */
    public function listFiles(string $assistantId): object;
}
