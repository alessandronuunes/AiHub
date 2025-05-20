<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Modules\AiHub\Ai\Contracts\File;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiFile implements File
{
    protected Client $client;

    /**
     * Construtor.
     *
     * @param Client $client Instância do cliente OpenAI SDK.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Faz upload de um arquivo para a OpenAI.
     *
     * @param string $filePath Caminho completo do arquivo local.
     * @param string $purpose O propósito do arquivo (ex: 'assistants', 'fine-tune').
     * @return object Resposta da API com os detalhes do arquivo uploaded.
     * @throws RuntimeException Se houver erro na API ou arquivo não encontrado/aberto.
     */
    public function upload(string $filePath, string $purpose): object
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Arquivo não encontrado para upload: {$filePath}");
        }

        $fileHandle = null;
        try {
            $fileHandle = fopen($filePath, 'r');
            if (!$fileHandle) {
                throw new RuntimeException("Não foi possível abrir o arquivo para upload: {$filePath}");
            }

            $response = $this->client->files()->upload([
                'purpose' => $purpose,
                'file' => $fileHandle
            ]);

            Log::info("Arquivo uploaded para OpenAI: {$response->id} ({$response->filename})");
            return $response;
        } catch (\Exception $e) {
            Log::error("Erro ao fazer upload do arquivo {$filePath} para OpenAI: " . $e->getMessage());
            throw new RuntimeException("Falha ao fazer upload do arquivo para OpenAI.", 0, $e);
        } finally {
            if (is_resource($fileHandle)) {
                fclose($fileHandle);
            }
        }
    }

    /**
     * Recupera informações de um arquivo específico na OpenAI.
     *
     * @param string $fileId ID do arquivo na OpenAI.
     * @return object Resposta da API com os detalhes do arquivo.
     * @throws RuntimeException Se houver erro na API.
     */
    public function retrieve(string $fileId): object
    {
        try {
            $file = $this->client->files()->retrieve($fileId);
            return $file;
        } catch (\Exception $e) {
            Log::error("Erro ao recuperar arquivo OpenAI {$fileId}: " . $e->getMessage());
            throw new RuntimeException("Falha ao recuperar arquivo OpenAI.", 0, $e);
        }
    }

    /**
     * Lista todos os arquivos na OpenAI.
     *
     * @param array $params Parâmetros de listagem (ex: ['purpose' => 'assistants']).
     * @return object Resposta da API com a lista de arquivos.
     * @throws RuntimeException Se houver erro na API.
     */
    public function list(array $params = []): object
    {
        try {
            $files = $this->client->files()->list($params);
            return $files;
        } catch (\Exception $e) {
            Log::error("Erro ao listar arquivos OpenAI: " . $e->getMessage());
            throw new RuntimeException("Falha ao listar arquivos OpenAI.", 0, $e);
        }
    }

    /**
     * Deleta um arquivo da OpenAI.
     *
     * @param string $fileId ID do arquivo a ser deletado.
     * @return object Resposta da API indicando o status da exclusão.
     * @throws RuntimeException Se houver erro na API.
     */
    public function delete(string $fileId): object
    {
        try {
            $response = $this->client->files()->delete($fileId);
            if ($response->deleted ?? false) {
                Log::info("Arquivo OpenAI {$fileId} deletado com sucesso.");
            } else {
                 Log::warning("Falha ao deletar arquivo OpenAI {$fileId}. Resposta: " . json_encode($response));
            }
            return $response;
        } catch (\Exception $e) {
            Log::error("Erro ao deletar arquivo OpenAI {$fileId}: " . $e->getMessage());
            throw new RuntimeException("Falha ao deletar arquivo OpenAI.", 0, $e);
        }
    }
}