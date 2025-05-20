<?php

namespace Modules\AiHub\Ai\Contracts;

interface Assistant
{
    /**
     * Cria um novo assistente.
     *
     * @param  array  $params  Parâmetros para criação do assistente.
     * @return object Resposta da API com o assistente criado.
     */
    public function create(array $params): object;

    /**
     * Modifica um assistente existente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @param  array  $params  Parâmetros para modificação.
     * @return object Resposta da API.
     */
    public function modify(string $assistantId, array $params): object;

    /**
     * Exclui um assistente.
     *
     * @param  string  $assistantId  ID do assistente a ser excluído.
     * @return bool Retorna true se a exclusão for bem-sucedida.
     */
    public function delete(string $assistantId): bool;

    /**
     * Adiciona um arquivo a um assistente existente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @param  string  $fileId  ID do arquivo.
     * @return object Resposta da API.
     */
    public function addFile(string $assistantId, string $fileId): object;

    /**
     * Remove um arquivo de um assistente existente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @param  string  $fileId  ID do arquivo.
     * @return bool Retorna true se a remoção for bem-sucedida.
     */
    public function removeFile(string $assistantId, string $fileId): bool;

    /**
     * Lista os arquivos associados a um assistente.
     *
     * @param  string  $assistantId  ID do assistente.
     * @return object Resposta da API com a lista de arquivos.
     */
    public function listFiles(string $assistantId): object;
}
