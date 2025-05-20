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
     * Construtor.
     *
     * @param  Client  $client  Instância do cliente OpenAI SDK.
     * @param  string  $defaultModel  Modelo padrão a ser usado.
     * @param  string|null  $companySlug  Slug da empresa para contexto.
     */
    public function __construct(Client $client, string $defaultModel, ?string $companySlug = null)
    {
        $this->client = $client;
        $this->defaultModel = $defaultModel;
        $this->companySlug = $companySlug;
    }

    /**
     * Cria um novo assistente.
     *
     * @param  array  $params  Parâmetros para criação do assistente.
     * @return object Resposta da API com o assistente criado.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function create(array $params): object
    {
        // Garantir que o modelo esteja definido, usando o padrão se não fornecido
        if (! isset($params['model'])) {
            $params['model'] = $this->defaultModel;
        }

        $this->processTools($params);

        try {
            $assistant = $this->client->assistants()->create($params);
            Log::info("Assistente OpenAI criado: {$assistant->id}");

            return $assistant;
        } catch (\Exception $e) {
            Log::error('Erro ao criar assistente OpenAI: '.$e->getMessage());
            throw new RuntimeException('Falha ao criar assistente OpenAI.', 0, $e);
        }
    }

    /**
     * Modifica um assistente existente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @param  array  $params  Parâmetros para modificação.
     * @return object Resposta da API.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function modify(string $assistantId, array $params): object
    {
        $this->processTools($params);

        try {
            $assistant = $this->client->assistants()->modify($assistantId, $params);
            Log::info("Assistente OpenAI {$assistantId} modificado.");

            return $assistant;
        } catch (\Exception $e) {
            Log::error("Erro ao modificar assistente OpenAI {$assistantId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao modificar assistente OpenAI.', 0, $e);
        }
    }

    /**
     * Exclui um assistente.
     *
     * @param  string  $assistantId  ID do assistente a ser excluído.
     * @return bool Retorna true se a exclusão for bem-sucedida.
     */
    public function delete(string $assistantId): bool
    {
        try {
            $response = $this->client->assistants()->delete($assistantId);
            if ($response->deleted ?? false) {
                Log::info("Assistente OpenAI {$assistantId} deletado com sucesso.");

                return true;
            }
            Log::warning("Falha ao deletar assistente OpenAI {$assistantId}. Resposta: ".json_encode($response));

            return false;
        } catch (\Exception $e) {
            Log::error("Erro ao deletar assistente OpenAI {$assistantId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Adiciona um arquivo a um assistente existente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @param  string  $fileId  ID do arquivo.
     * @return object Resposta da API.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function addFile(string $assistantId, string $fileId): object
    {
        try {
            // A API Assistants V2 usa o endpoint files para associar arquivos
            $response = $this->client->assistants()->files()->create($assistantId, [
                'file_id' => $fileId,
            ]);
            Log::info("Arquivo {$fileId} adicionado ao assistente {$assistantId}.");

            return $response;
        } catch (\Exception $e) {
            Log::error("Erro ao adicionar arquivo {$fileId} ao assistente {$assistantId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao adicionar arquivo ao assistente OpenAI.', 0, $e);
        }
    }

    /**
     * Remove um arquivo de um assistente existente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @param  string  $fileId  ID do arquivo.
     * @return bool Retorna true se a remoção for bem-sucedida.
     */
    public function removeFile(string $assistantId, string $fileId): bool
    {
        try {
            $response = $this->client->assistants()->files()->delete($assistantId, $fileId);
            if ($response->deleted ?? false) {
                Log::info("Arquivo {$fileId} removido do assistente {$assistantId}.");

                return true;
            }
            Log::warning("Falha ao remover arquivo {$fileId} do assistente {$assistantId}. Resposta: ".json_encode($response));

            return false;
        } catch (\Exception $e) {
            Log::error("Erro ao remover arquivo {$fileId} do assistente {$assistantId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Lista os arquivos associados a um assistente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @return object Resposta da API com a lista de arquivos.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function listFiles(string $assistantId): object
    {
        try {
            $response = $this->client->assistants()->files()->list($assistantId);

            return $response;
        } catch (\Exception $e) {
            Log::error("Erro ao listar arquivos do assistente {$assistantId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao listar arquivos do assistente OpenAI.', 0, $e);
        }
    }

    private function processTools(array &$params): void
    {
        if (! isset($params['tools'])) {
            $params['tools'] = [['type' => 'file_search']];
        } else {
            // Atualizar 'retrieval' para 'file_search' se presente
            foreach ($params['tools'] as &$tool) {
                if (isset($tool['type']) && $tool['type'] === 'retrieval') {
                    $tool['type'] = 'file_search';
                }
            }
            unset($tool); // Quebrar a referência do último elemento
        }
    }
}
