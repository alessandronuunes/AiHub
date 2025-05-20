<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Illuminate\Support\Facades\Log;
use Modules\AiHub\Ai\Contracts\File;
use OpenAI\Client;
use RuntimeException;

class OpenAiFile implements File
{
    protected Client $client;

    /**
     * Constructor.
     *
     * @param  Client  $client  OpenAI SDK client instance.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Uploads a file to OpenAI.
     *
     * @param  string  $filePath  Full path of the local file.
     * @param  string  $purpose  The purpose of the file (e.g., 'assistants', 'fine-tune').
     * @return object API response with details of the uploaded file.
     *
     * @throws RuntimeException If there is an API error or the file is not found/cannot be opened.
     */
    public function upload(string $filePath, string $purpose): object
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File not found for upload: {$filePath}");
        }

        $fileHandle = null;
        try {
            $fileHandle = fopen($filePath, 'r');
            if (! $fileHandle) {
                throw new RuntimeException("Could not open file for upload: {$filePath}");
            }

            $response = $this->client->files()->upload([
                'purpose' => $purpose,
                'file' => $fileHandle,
            ]);

            Log::info("File uploaded to OpenAI: {$response->id} ({$response->filename})");

            return $response;
        } catch (\Exception $e) {
            Log::error("Error uploading file {$filePath} to OpenAI: ".$e->getMessage());
            throw new RuntimeException('Failed to upload file to OpenAI.', 0, $e);
        } finally {
            if (is_resource($fileHandle)) {
                fclose($fileHandle);
            }
        }
    }

    /**
     * Retrieves information about a specific file from OpenAI.
     *
     * @param  string  $fileId  OpenAI file ID.
     * @return object API response with file details.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function retrieve(string $fileId): object
    {
        try {
            $file = $this->client->files()->retrieve($fileId);

            return $file;
        } catch (\Exception $e) {
            Log::error("Error retrieving OpenAI file {$fileId}: ".$e->getMessage());
            throw new RuntimeException('Failed to retrieve OpenAI file.', 0, $e);
        }
    }

    /**
     * Lists all files in OpenAI.
     *
     * @param  array  $params  Listing parameters (e.g., ['purpose' => 'assistants']).
     * @return object API response with the list of files.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function list(array $params = []): object
    {
        try {
            $files = $this->client->files()->list($params);

            return $files;
        } catch (\Exception $e) {
            Log::error('Error listing OpenAI files: '.$e->getMessage());
            throw new RuntimeException('Failed to list OpenAI files.', 0, $e);
        }
    }

    /**
     * Deletes a file from OpenAI.
     *
     * @param  string  $fileId  ID of the file to be deleted.
     * @return object API response indicating the deletion status.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function delete(string $fileId): object
    {
        try {
            $response = $this->client->files()->delete($fileId);
            if ($response->deleted ?? false) {
                Log::info("OpenAI file {$fileId} successfully deleted.");
            } else {
                Log::warning("Failed to delete OpenAI file {$fileId}. Response: ".json_encode($response));
            }

            return $response;
        } catch (\Exception $e) {
            Log::error("Error deleting OpenAI file {$fileId}: ".$e->getMessage());
            throw new RuntimeException('Failed to delete OpenAI file.', 0, $e);
        }
    }
}
