<?php

namespace Modules\AiHub\Ai\Contracts;

interface File
{
    /**
     * Uploads a file to OpenAI.
     *
     * @param  string  $filePath  Full path of the local file.
     * @param  string  $purpose  The purpose of the file (e.g., 'assistants', 'fine-tune').
     * @return object API response with details of the uploaded file.
     */
    public function upload(string $filePath, string $purpose): object;

    /**
     * Retrieves information about a specific file in OpenAI.
     *
     * @param  string  $fileId  File ID in OpenAI.
     * @return object API response with file details.
     */
    public function retrieve(string $fileId): object;

    /**
     * Lists all files in OpenAI.
     *
     * @param  array  $params  Listing parameters (e.g., ['purpose' => 'assistants']).
     * @return object API response with the list of files.
     */
    public function list(array $params = []): object;

    /**
     * Deletes a file from OpenAI.
     *
     * @param  string  $fileId  ID of the file to be deleted.
     * @return object API response indicating the deletion status.
     */
    public function delete(string $fileId): object;
}
