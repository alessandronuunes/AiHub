<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Illuminate\Support\Facades\Log;
use Modules\AiHub\Ai\Contracts\Thread;
use OpenAI\Client;
use RuntimeException;

// Para polling

class OpenAiThread implements Thread
{
    protected Client $client;

    protected ?string $companySlug;

    /**
     * Construtor.
     *
     * @param  Client  $client  Instância do cliente OpenAI SDK.
     * @param  string|null  $companySlug  Slug da empresa para contexto.
     */
    public function __construct(Client $client, ?string $companySlug = null)
    {
        $this->client = $client;
        $this->companySlug = $companySlug;
    }

    /**
     * Cria uma nova thread.
     *
     * @param  array  $params  Parâmetros opcionais para criação da thread.
     * @return object Resposta da API com a thread criada.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function create(array $params = []): object
    {
        try {
            $thread = $this->client->threads()->create($params);
            Log::info("Thread OpenAI criada: {$thread->id}");

            return $thread;
        } catch (\Exception $e) {
            Log::error('Erro ao criar thread OpenAI: '.$e->getMessage());
            throw new RuntimeException('Falha ao criar thread OpenAI.', 0, $e);
        }
    }

    /**
     * Recupera uma thread existente.
     *
     * @param  string  $threadId  ID da thread.
     * @return object Resposta da API com os detalhes da thread.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function retrieve(string $threadId): object
    {
        try {
            $thread = $this->client->threads()->retrieve($threadId);

            return $thread;
        } catch (\Exception $e) {
            Log::error("Erro ao recuperar thread OpenAI {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao recuperar thread OpenAI.', 0, $e);
        }
    }

    /**
     * Adiciona uma mensagem a uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $content  Conteúdo da mensagem.
     * @param  string  $role  Papel da mensagem (ex: 'user', 'assistant').
     * @param  array  $params  Parâmetros adicionais para a mensagem.
     * @return object Resposta da API com a mensagem criada.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function addMessage(string $threadId, string $content, string $role = 'user', array $params = []): object
    {
        try {
            $message = $this->client->threads()->messages()->create($threadId, [
                'role' => $role,
                'content' => $content,
                ...$params, // Mescla parâmetros adicionais
            ]);
            Log::info("Mensagem adicionada à thread {$threadId}. Mensagem ID: {$message->id}");

            return $message;
        } catch (\Exception $e) {
            Log::error("Erro ao adicionar mensagem à thread OpenAI {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao adicionar mensagem à thread OpenAI.', 0, $e);
        }
    }

    /**
     * Lista as mensagens de uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @param  array  $params  Parâmetros de listagem (limit, order, after, before).
     * @return object Resposta da API com a lista de mensagens.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function listMessages(string $threadId, array $params = []): object
    {
        try {
            $messages = $this->client->threads()->messages()->list($threadId, $params);

            return $messages;
        } catch (\Exception $e) {
            Log::error("Erro ao listar mensagens da thread OpenAI {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao listar mensagens da thread OpenAI.', 0, $e);
        }
    }

    /**
     * Executa o assistente em uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $assistantId  ID do assistente.
     * @param  array  $params  Parâmetros adicionais para a execução.
     * @return object Resposta da API com os detalhes da execução.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function runAssistant(string $threadId, string $assistantId, array $params = []): object
    {
        try {
            $run = $this->client->threads()->runs()->create($threadId, [
                'assistant_id' => $assistantId,
                ...$params, // Mescla parâmetros adicionais
            ]);
            Log::info("Execução iniciada na thread {$threadId} com assistente {$assistantId}. Run ID: {$run->id}");

            return $run;
        } catch (\Exception $e) {
            Log::error("Erro ao iniciar execução na thread OpenAI {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao iniciar execução na thread OpenAI.', 0, $e);
        }
    }

    /**
     * Recupera o status de uma execução.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $runId  ID da execução.
     * @return object Resposta da API com os detalhes da execução.
     *
     * @throws RuntimeException Se houver erro na API.
     */
    public function retrieveRun(string $threadId, string $runId): object
    {
        try {
            $run = $this->client->threads()->runs()->retrieve($threadId, $runId);

            return $run;
        } catch (\Exception $e) {
            Log::error("Erro ao recuperar execução OpenAI {$runId} para thread {$threadId}: ".$e->getMessage());
            throw new RuntimeException('Falha ao recuperar execução OpenAI.', 0, $e);
        }
    }

    /**
     * Aguarda a conclusão de uma execução e retorna a última mensagem do assistente.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $runId  ID da execução.
     * @param  int  $maxAttempts  Número máximo de tentativas.
     * @param  int  $delay  Delay entre as tentativas em segundos.
     * @return object|null Resposta da API com a última mensagem ou null se timeout.
     *
     * @throws RuntimeException Se houver erro na API durante o polling.
     */
    public function waitForResponse(string $threadId, string $runId, int $maxAttempts = 30, int $delay = 1): ?object
    {
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $run = $this->retrieveRun($threadId, $runId);

            switch ($run->status) {
                case 'completed':
                    Log::info("Execução {$runId} concluída.");
                    // Recupera as mensagens após a conclusão
                    $messages = $this->listMessages($threadId, ['order' => 'desc', 'limit' => 1]);

                    // Retorna a última mensagem, que deve ser a resposta do assistente
                    return $messages->data[0] ?? null;
                case 'queued':
                case 'in_progress':
                case 'cancelling':
                    // Aguarda e tenta novamente
                    Log::debug("Execução {$runId} em status '{$run->status}'. Tentativa {$attempts}/{$maxAttempts}.");
                    sleep($delay);
                    $attempts++;
                    break;
                case 'requires_action':
                    Log::warning("Execução {$runId} requer ação (ex: tool_calls). Status: {$run->status}.");

                    // Dependendo da sua necessidade, você pode querer tratar tool_calls aqui
                    // Por enquanto, vamos apenas logar e parar o polling ou continuar esperando
                    // Para este exemplo, vamos parar o polling e retornar null ou o status
                    return $run; // Retorna o objeto run para que o chamador possa inspecionar
                case 'cancelled':
                case 'failed':
                case 'expired':
                    Log::error("Execução {$runId} falhou ou foi cancelada/expirada. Status: {$run->status}.");
                    throw new RuntimeException("Execução OpenAI falhou com status: {$run->status}.");
            }
        }

        Log::warning("Polling para execução {$runId} atingiu o número máximo de tentativas.");

        return null; // Timeout
    }

    /**
     * Deleta uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @return bool Retorna true se a exclusão for bem-sucedida.
     */
    public function delete(string $threadId): bool
    {
        try {
            $response = $this->client->threads()->delete($threadId);
            if ($response->deleted ?? false) {
                Log::info("Thread OpenAI {$threadId} deletada com sucesso.");

                return true;
            }
            Log::warning("Falha ao deletar thread OpenAI {$threadId}. Resposta: ".json_encode($response));

            return false;
        } catch (\Exception $e) {
            Log::error("Erro ao deletar thread OpenAI {$threadId}: ".$e->getMessage());

            return false;
        }
    }
}
