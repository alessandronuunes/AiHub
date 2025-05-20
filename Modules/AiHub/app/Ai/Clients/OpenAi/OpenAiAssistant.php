<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Illuminate\Support\Facades\Log;
use Modules\AiHub\Ai\Contracts\Assistant;
use OpenAI\Client;
use RuntimeException;

class OpenAiAssistant implements Assistant
{
    protected Client $client;

    protected ?string $companySlug;

    protected string $defaultModel;

    /**
     * Constructor.
     *
     * @param  Client  $client  Instance of the OpenAI SDK client.
     * @param  string  $defaultModel  Default model to be used.
     * @param  string|null  $companySlug  Company slug for context.
     */
    public function __construct(Client $client, string $defaultModel, ?string $companySlug = null)
    {
        $this->client = $client;
        $this->defaultModel = $defaultModel;
        $this->companySlug = $companySlug;
    }

    /**
     * Creates a new assistant.
     *
     * @param  array  $params  Parameters for assistant creation.
     * @return object API response with the created assistant.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function create(array $params): object
    {
        // Ensure the model is defined, using the default if not provided
        if (! isset($params['model'])) {
            $params['model'] = $this->defaultModel;
        }

        $this->processTools($params);

        try {
            $assistant = $this->client->assistants()->create($params);
            Log::info("OpenAI Assistant created: {$assistant->id}");

            return $assistant;
        } catch (\Exception $e) {
            Log::error('Error creating OpenAI assistant: '.$e->getMessage());
            throw new RuntimeException('Failed to create OpenAI assistant.', 0, $e);
        }
    }

    /**
     * Modifies an existing assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @param  array  $params  Parameters for modification.
     * @return object API response.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function modify(string $assistantId, array $params): object
    {
        $this->processTools($params);

        try {
            $assistant = $this->client->assistants()->modify($assistantId, $params);
            Log::info("OpenAI Assistant {$assistantId} modified.");

            return $assistant;
        } catch (\Exception $e) {
            Log::error("Error modifying OpenAI assistant {$assistantId}: ".$e->getMessage());
            throw new RuntimeException('Failed to modify OpenAI assistant.', 0, $e);
        }
    }

    /**
     * Deletes an assistant.
     *
     * @param  string  $assistantId  ID of the assistant to be deleted.
     * @return bool Returns true if deletion is successful.
     */
    public function delete(string $assistantId): bool
    {
        try {
            $response = $this->client->assistants()->delete($assistantId);
            if ($response->deleted ?? false) {
                Log::info("OpenAI Assistant {$assistantId} successfully deleted.");

                return true;
            }
            Log::warning("Failed to delete OpenAI assistant {$assistantId}. Response: ".json_encode($response));

            return false;
        } catch (\Exception $e) {
            Log::error("Error deleting OpenAI assistant {$assistantId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Adds a file to an existing assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @param  string  $fileId  File ID.
     * @return object API response.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function addFile(string $assistantId, string $fileId): object
    {
        try {
            // The Assistants V2 API uses the files endpoint to associate files
            $response = $this->client->assistants()->files()->create($assistantId, [
                'file_id' => $fileId,
            ]);
            Log::info("File {$fileId} added to assistant {$assistantId}.");

            return $response;
        } catch (\Exception $e) {
            Log::error("Error adding file {$fileId} to assistant {$assistantId}: ".$e->getMessage());
            throw new RuntimeException('Failed to add file to OpenAI assistant.', 0, $e);
        }
    }

    /**
     * Removes a file from an existing assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @param  string  $fileId  File ID.
     * @return bool Returns true if removal is successful.
     */
    public function removeFile(string $assistantId, string $fileId): bool
    {
        try {
            $response = $this->client->assistants()->files()->delete($assistantId, $fileId);
            if ($response->deleted ?? false) {
                Log::info("File {$fileId} removed from assistant {$assistantId}.");

                return true;
            }
            Log::warning("Failed to remove file {$fileId} from assistant {$assistantId}. Response: ".json_encode($response));

            return false;
        } catch (\Exception $e) {
            Log::error("Error removing file {$fileId} from assistant {$assistantId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Lists the files associated with an assistant.
     *
     * @param  string  $assistantId  Assistant ID.
     * @return object API response with the list of files.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function listFiles(string $assistantId): object
    {
        try {
            $response = $this->client->assistants()->files()->list($assistantId);

            return $response;
        } catch (\Exception $e) {
            Log::error("Error listing files for assistant {$assistantId}: ".$e->getMessage());
            throw new RuntimeException('Failed to list files for OpenAI assistant.', 0, $e);
        }
    }

    private function processTools(array &$params): void
    {
        if (! isset($params['tools'])) {
            $params['tools'] = [['type' => 'file_search']];
        } else {
            // Update 'retrieval' to 'file_search' if present
            foreach ($params['tools'] as &$tool) {
                if (isset($tool['type']) && $tool['type'] === 'retrieval') {
                    $tool['type'] = 'file_search';
                }
            }
            unset($tool); // Break the reference to the last element
        }
    }
}
