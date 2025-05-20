<?php

namespace Modules\AiHub\Ai\Contracts;

interface VectorStore
{
    /**
     * Cria uma nova vector store.
     *
     * @param string $name Nome da vector store.
     * @param array $params Parâmetros adicionais para criação.
     * @return object Resposta da API com a vector store criada.
     */
    public function create(string $name, array $params = []): object;

    /**
     * Recupera uma vector store específica.
     *
     * @param string $vectorStoreId ID da vector store.
     * @return object Resposta da API com os detalhes da vector store.
     */
    public function retrieve(string $vectorStoreId): object;

    /**
     * Lista todas as vector stores.
     *
     * @param array $params Parâmetros de listagem.
     * @return object Resposta da API com a lista de vector stores.
     */
    public function list(array $params = []): object;

    /**
     * Deleta uma vector store.
     *
     * @param string $vectorStoreId ID da vector store a ser deletada.
     * @param bool $forceDelete Se true, tenta deletar arquivos associados primeiro.
     * @return bool Retorna true se a exclusão for bem-sucedida.
     */
    public function delete(string $vectorStoreId, bool $forceDelete = false): bool;

    /**
     * Adiciona arquivos a uma vector store.
     *
     * @param string $vectorStoreId ID da vector store.
     * @param array $fileIds IDs dos arquivos a serem adicionados.
     * @return object Resposta da API com os detalhes da operação.
     */
    public function addFiles(string $vectorStoreId, array $fileIds): object;

    /**
     * Remove arquivos de uma vector store.
     *
     * @param string $vectorStoreId ID da vector store.
     * @param array $fileIds IDs dos arquivos a serem removidos.
     * @return object Resposta da API com os detalhes da operação.
     */
    public function removeFiles(string $vectorStoreId, array $fileIds): object;

    /**
     * Lista os arquivos associados a uma vector store.
     *
     * @param string $vectorStoreId ID da vector store.
     * @param array $params Parâmetros de listagem.
     * @return object Resposta da API com a lista de arquivos.
     */
    public function listFiles(string $vectorStoreId, array $params = []): object;
}