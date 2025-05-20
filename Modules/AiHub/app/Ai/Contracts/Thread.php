<?php

namespace Modules\AiHub\Ai\Contracts;

interface Thread
{
    /**
     * Cria uma nova thread.
     *
     * @param  array  $params  Parâmetros opcionais para criação da thread.
     * @return object Resposta da API com a thread criada.
     */
    public function create(array $params = []): object;

    /**
     * Recupera uma thread existente.
     *
     * @param  string  $threadId  ID da thread.
     * @return object Resposta da API com os detalhes da thread.
     */
    public function retrieve(string $threadId): object;

    /**
     * Adiciona uma mensagem a uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $content  Conteúdo da mensagem.
     * @param  string  $role  Papel da mensagem (ex: 'user', 'assistant').
     * @param  array  $params  Parâmetros adicionais para a mensagem.
     * @return object Resposta da API com a mensagem criada.
     */
    public function addMessage(string $threadId, string $content, string $role = 'user', array $params = []): object;

    /**
     * Lista as mensagens de uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @param  array  $params  Parâmetros de listagem (limit, order, after, before).
     * @return object Resposta da API com a lista de mensagens.
     */
    public function listMessages(string $threadId, array $params = []): object;

    /**
     * Executa o assistente em uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $assistantId  ID do assistente.
     * @param  array  $params  Parâmetros adicionais para a execução.
     * @return object Resposta da API com os detalhes da execução.
     */
    public function runAssistant(string $threadId, string $assistantId, array $params = []): object;

    /**
     * Recupera o status de uma execução.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $runId  ID da execução.
     * @return object Resposta da API com os detalhes da execução.
     */
    public function retrieveRun(string $threadId, string $runId): object;

    /**
     * Aguarda a conclusão de uma execução e retorna a última mensagem do assistente.
     *
     * @param  string  $threadId  ID da thread.
     * @param  string  $runId  ID da execução.
     * @param  int  $maxAttempts  Número máximo de tentativas.
     * @param  int  $delay  Delay entre as tentativas em segundos.
     * @return object|null Resposta da API com a última mensagem ou null se timeout.
     */
    public function waitForResponse(string $threadId, string $runId, int $maxAttempts = 30, int $delay = 1): ?object;

    /**
     * Deleta uma thread.
     *
     * @param  string  $threadId  ID da thread.
     * @return bool Retorna true se a exclusão for bem-sucedida.
     */
    public function delete(string $threadId): bool;
}
