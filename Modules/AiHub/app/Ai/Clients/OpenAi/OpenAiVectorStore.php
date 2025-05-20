<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Modules\AiHub\Ai\Contracts\VectorStore;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiVectorStore implements VectorStore
{
    protected Client $client;
    protected ?string $companySlug;

    /**
     * Construtor.
     *
     * @param Client $client Instância do cliente OpenAI SDK.
     * @param string|null $companySlug Slug da empresa para contexto.
     */
    public function __construct(Client $client, ?string $companySlug = null)
    {
        $this->client = $client;
        $this->companySlug = $companySlug;
    }

    /**
     * Cria uma nova vector store.
     *
     * @param string $name Nome da vector store.
     * @param array $params Parâmetros adicionais para criação.
     * @return object Resposta da API com a vector store criada.
     * @throws RuntimeException Se houver erro na API.
     */
    public function create(string $name, array $params = []): object
    {
        try {
            $vectorStore = $this->client->vectorStores()->create([
                'name' => $name,
                ...$params, // Mescla parâmetros adicionais
            ]);
            Log::info("Vector Store OpenAI criada: {$vectorStore->id}");
            return $vectorStore;
        } catch (\Exception $e) {
            Log::error("Erro ao criar Vector Store OpenAI: " . $e->getMessage());
            throw new RuntimeException("Falha ao criar Vector Store OpenAI.", 0, $e);
        }
    }

    /**
     * Recupera uma vector store específica.
     *
     * @param string $vectorStoreId ID da vector store.
     * @return object Resposta da API com os detalhes da vector store.
     * @throws RuntimeException Se houver erro na API.
     */
    public function retrieve(string $vectorStoreId): object
    {
        try {
            $vectorStore = $this->client->vectorStores()->retrieve($vectorStoreId);
            return $vectorStore;
        } catch (\Exception $e) {
            Log::error("Erro ao recuperar Vector Store OpenAI {$vectorStoreId}: " . $e->getMessage());
            throw new RuntimeException("Falha ao recuperar Vector Store OpenAI.", 0, $e);
        }
    }

    /**
     * Lista todas as vector stores.
     *
     * @param array $params Parâmetros de listagem.
     * @return object Resposta da API com a lista de vector stores.
     * @throws RuntimeException Se houver erro na API.
     */
    public function list(array $params = []): object
    {
        try {
            $vectorStores = $this->client->vectorStores()->list($params);
            return $vectorStores;
        } catch (\Exception $e) {
            Log::error("Erro ao listar Vector Stores OpenAI: " . $e->getMessage());
            throw new RuntimeException("Falha ao listar Vector Stores OpenAI.", 0, $e);
        }
    }

    /**
     * Deleta uma vector store.
     *
     * @param string $vectorStoreId ID da vector store a ser deletada.
     * @param bool $forceDelete Se true, tenta deletar arquivos associados primeiro (não suportado diretamente pela API, requer lógica manual).
     * @return bool Retorna true se a exclusão for bem-sucedida.
     */
    public function delete(string $vectorStoreId, bool $forceDelete = false): bool
    {
        // Nota: A API OpenAI não tem um forceDelete direto que deleta arquivos.
        // Se forceDelete for true, você precisaria listar e deletar os arquivos manualmente primeiro.
        // Para simplificar, esta implementação apenas tenta deletar a vector store.
        // Implementar a lógica de forceDelete manual seria mais complexo e fora do escopo direto da refatoração simples.

        try {
            $response = $this->client->vectorStores()->delete($vectorStoreId);
            if ($response->deleted ?? false) {
                Log::info("Vector Store OpenAI {$vectorStoreId} deletada com sucesso.");
                return true;
            }
            Log::warning("Falha ao deletar Vector Store OpenAI {$vectorStoreId}. Resposta: " . json_encode($response));
            return false;
        } catch (\Exception $e) {
            Log::error("Erro ao deletar Vector Store OpenAI {$vectorStoreId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adiciona arquivos a uma vector store.
     *
     * @param string $vectorStoreId ID da vector store.
     * @param array $fileIds IDs dos arquivos a serem adicionados.
     * @return object Resposta da API com os detalhes da operação.
     * @throws RuntimeException Se houver erro na API.
     */
    public function addFiles(string $vectorStoreId, array $fileIds): object
    {
        try {
            // A API não suporta batchCreate, então precisamos adicionar um por um
            $results = [];
            
            foreach ($fileIds as $fileId) {
                $response = $this->client->vectorStores()->files()->create(
                    $vectorStoreId,
                    [
                        'file_id' => $fileId
                    ]
                );
                $results[] = $response;
            }
            
            // Retorna um objeto com os resultados
            return (object) [
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            Log::error("Erro ao adicionar arquivos à Vector Store OpenAI {$vectorStoreId}: " . $e->getMessage());
            throw new RuntimeException("Falha ao adicionar arquivos à Vector Store OpenAI.", 0, $e);
        }
    }

    /**
     * Remove arquivos de uma vector store.
     *
     * @param string $vectorStoreId ID da vector store.
     * @param array $fileIds IDs dos arquivos a serem removidos.
     * @return object Resposta da API com os detalhes da operação.
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
                 if (!($response->deleted ?? false)) {
                     $success = false;
                     Log::warning("Falha ao remover arquivo {$fileId} da Vector Store {$vectorStoreId}.");
                 } else {
                     Log::info("Arquivo {$fileId} removido da Vector Store {$vectorStoreId}.");
                 }
             } catch (\Exception $e) {
                 $success = false;
                 $results[$fileId] = ['error' => $e->getMessage()];
                 Log::error("Erro ao remover arquivo {$fileId} da Vector Store {$vectorStoreId}: " . $e->getMessage());
             }
         }

         // Retorna um objeto que indica o sucesso geral e os resultados individuais
         return (object) ['success' => $success, 'results' => $results];
    }

    /**
     * Lista os arquivos associados a uma vector store.
     *
     * @param string $vectorStoreId ID da vector store.
     * @param array $params Parâmetros de listagem.
     * @return object Resposta da API com a lista de arquivos.
     * @throws RuntimeException Se houver erro na API.
     */
    public function listFiles(string $vectorStoreId, array $params = []): object
    {
        try {
            $files = $this->client->vectorStores()->files()->list($vectorStoreId, $params);
            return $files;
        } catch (\Exception $e) {
            Log::error("Erro ao listar arquivos da Vector Store OpenAI {$vectorStoreId}: " . $e->getMessage());
            throw new RuntimeException("Falha ao listar arquivos da Vector Store OpenAI.", 0, $e);
        }
    }
}