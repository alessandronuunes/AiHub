<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Illuminate\Support\Facades\Log;
use Modules\AiHub\Ai\Contracts\VectorStore;
use OpenAI\Client;
use RuntimeException;

class OpenAiVectorStore implements VectorStore
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
     * Creates a new vector store.
     *
     * @param  string  $name  Name of the vector store.
     * @param  array  $params  Additional parameters for creation.
     * @return object API response with the created vector store.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function create(string $name, array $params = []): object
    {
        try {
            $vectorStore = $this->client->vectorStores()->create([
                'name' => $name,
                ...$params, // Merges additional parameters
            ]);
            Log::info("OpenAI Vector Store created: {$vectorStore->id}");

            return $vectorStore;
        } catch (\Exception $e) {
            Log::error('Error creating OpenAI Vector Store: '.$e->getMessage());
            throw new RuntimeException('Failed to create OpenAI Vector Store.', 0, $e);
        }
    }

    /**
     * Retrieves a specific vector store.
     *
     * @param  string  $vectorStoreId  Vector store ID.
     * @return object API response with vector store details.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function retrieve(string $vectorStoreId): object
    {
        try {
            $vectorStore = $this->client->vectorStores()->retrieve($vectorStoreId);

            return $vectorStore;
        } catch (\Exception $e) {
            Log::error("Error retrieving OpenAI Vector Store {$vectorStoreId}: ".$e->getMessage());
            throw new RuntimeException('Failed to retrieve OpenAI Vector Store.', 0, $e);
        }
    }

    /**
     * Lists all vector stores.
     *
     * @param  array  $params  Listing parameters.
     * @return object API response with the list of vector stores.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function list(array $params = []): object
    {
        try {
            $vectorStores = $this->client->vectorStores()->list($params);

            return $vectorStores;
        } catch (\Exception $e) {
            Log::error('Error listing OpenAI Vector Stores: '.$e->getMessage());
            throw new RuntimeException('Failed to list OpenAI Vector Stores.', 0, $e);
        }
    }

    /**
     * Deletes a vector store.
     *
     * @param  string  $vectorStoreId  ID of the vector store to be deleted.
     * @param  bool  $forceDelete  If true, tries to delete associated files first (not directly supported by the API, requires manual logic).
     * @return bool Returns true if deletion is successful.
     */
    public function delete(string $vectorStoreId, bool $forceDelete = false): bool
    {
        // Note: The OpenAI API doesn't have a direct forceDelete that deletes files.
        // If forceDelete is true, you would need to list and delete the files manually first.
        // To simplify, this implementation only tries to delete the vector store.
        // Implementing manual forceDelete logic would be more complex and outside the direct scope of simple refactoring.

        try {
            $response = $this->client->vectorStores()->delete($vectorStoreId);
            if ($response->deleted ?? false) {
                Log::info("OpenAI Vector Store {$vectorStoreId} successfully deleted.");

                return true;
            }
            Log::warning("Failed to delete OpenAI Vector Store {$vectorStoreId}. Response: ".json_encode($response));

            return false;
        } catch (\Exception $e) {
            Log::error("Error deleting OpenAI Vector Store {$vectorStoreId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Adds files to a vector store.
     *
     * @param  string  $vectorStoreId  Vector store ID.
     * @param  array  $fileIds  IDs of files to be added.
     * @return object API response with operation details.
     *
     * @throws RuntimeException If there is an API error.
     */
    public function addFiles(string $vectorStoreId, array $fileIds): object
    {
        try {
            // The API doesn't support batchCreate, so we need to add one by one
            $results = [];

            foreach ($fileIds as $fileId) {
                $response = $this->client->vectorStores()->files()->create(
                    $vectorStoreId,
                    [
                        'file_id' => $fileId,
                    ]
                );
                $results[] = $response;
            }

            // Retorna um objeto com os resultados
            return (object) [
                'success' => true,
                'results' => $results,
                'count' => count($results),
            ];
        } catch (\Exception $e) {
            Log::error("Error adding files to OpenAI Vector Store {$vectorStoreId}: ".$e->getMessage());
            throw new RuntimeException('Failed to add files to OpenAI Vector Store.', 0, $e);
        }
    }

    /**
     * Remove arquivos de uma vector store.
     *
     * @param  string  $vectorStoreId  ID da vector store.
     * @param  array  $fileIds  IDs dos arquivos a serem removidos.
     * @return object Resposta da API com os detalhes da operação.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function removeFiles(string $vectorStoreId, array $fileIds): object
    {
        // A API não tem um endpoint de remoção em lote direto.
        // É necessário remover um por um.
        $results = [];
        $success = true;

        foreach ($fileIds as $fileId) {
            try {
                $response = $this->client->vectorStores()->files()->delete($vectorStoreId, $fileId);
                $results[$fileId] = $response;
                if (! ($response->deleted ?? false)) {
                    $success = false;
                    Log::warning("Falha ao remover arquivo {$fileId} da Vector Store {$vectorStoreId}.");
                } else {
                    Log::info("Arquivo {$fileId} removido da Vector Store {$vectorStoreId}.");
                }
            } catch (\Exception $e) {
                $success = false;
                $results[$fileId] = ['error' => $e->getMessage()];
                Log::error("Erro ao remover arquivo {$fileId} da Vector Store {$vectorStoreId}: ".$e->getMessage());
            }
        }

        // Retorna um objeto que indica o sucesso geral e os resultados individuais
        return (object) ['success' => $success, 'results' => $results];
    }

    /**
     * Lista os arquivos associados a uma vector store.
     *
     * @param  string  $vectorStoreId  ID da vector store.
     * @param  array  $params  Parâmetros de listagem.
     * @return object Resposta da API com a lista de arquivos.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function listFiles(string $vectorStoreId, array $params = []): object
    {
        try {
            $files = $this->client->vectorStores()->files()->list($vectorStoreId, $params);

            return $files;
        } catch (\Exception $e) {
            Log::error("Erro ao listar arquivos da Vector Store OpenAI {$vectorStoreId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao listar arquivos da Vector Store OpenAI.', 0, $e);
        }
    }
}
