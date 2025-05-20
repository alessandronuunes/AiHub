<?php

namespace Modules\AiHub\Ai\Contracts;

interface File
{
    /**
     * Faz upload de um arquivo para a OpenAI.
     *
     * @param string $filePath Caminho completo do arquivo local.
     * @param string $purpose O propósito do arquivo (ex: 'assistants', 'fine-tune').
     * @return object Resposta da API com os detalhes do arquivo uploaded.
     */
    public function upload(string $filePath, string $purpose): object;

    /**
     * Recupera informações de um arquivo específico na OpenAI.
     *
     * @param string $fileId ID do arquivo na OpenAI.
     * @return object Resposta da API com os detalhes do arquivo.
     */
    public function retrieve(string $fileId): object;

    /**
     * Lista todos os arquivos na OpenAI.
     *
     * @param array $params Parâmetros de listagem (ex: ['purpose' => 'assistants']).
     * @return object Resposta da API com a lista de arquivos.
     */
    public function list(array $params = []): object;

    /**
     * Deleta um arquivo da OpenAI.
     *
     * @param string $fileId ID do arquivo a ser deletado.
     * @return object Resposta da API indicando o status da exclusão.
     */
    public function delete(string $fileId): object;
}