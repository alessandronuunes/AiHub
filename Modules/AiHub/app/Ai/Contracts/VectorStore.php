<?php

namespace Modules\AiHub\Ai\Contracts;

interface VectorStore
{
    /**
     * Creates a new vector store.
     *
     * @param  string  $name  Vector store name.
     * @param  array  $params  Additional parameters for creation.
     * @return object API response with the created vector store.
     */
    public function create(string $name, array $params = []): object;

    /**
     * Retrieves a specific vector store.
     *
     * @param  string  $vectorStoreId  Vector store ID.
     * @return object API response with vector store details.
     */
    public function retrieve(string $vectorStoreId): object;

    /**
     * Lists all vector stores.
     *
     * @param  array  $params  Listing parameters.
     * @return object API response with the list of vector stores.
     */
    public function list(array $params = []): object;

    /**
     * Deletes a vector store.
     *
     * @param  string  $vectorStoreId  ID of the vector store to be deleted.
     * @param  bool  $forceDelete  If true, tries to delete associated files first.
     * @return bool Returns true if deletion is successful.
     */
    public function delete(string $vectorStoreId, bool $forceDelete = false): bool;

    /**
     * Adds files to a vector store.
     *
     * @param  string  $vectorStoreId  Vector store ID.
     * @param  array  $fileIds  IDs of files to be added.
     * @return object API response with operation details.
     */
    public function addFiles(string $vectorStoreId, array $fileIds): object;

    /**
     * Removes files from a vector store.
     *
     * @param  string  $vectorStoreId  Vector store ID.
     * @param  array  $fileIds  IDs of files to be removed.
     * @return object API response with operation details.
     */
    public function removeFiles(string $vectorStoreId, array $fileIds): object;

    /**
     * Lists files associated with a vector store.
     *
     * @param  string  $vectorStoreId  Vector store ID.
     * @param  array  $params  Listing parameters.
     * @return object API response with the list of files.
     */
    public function listFiles(string $vectorStoreId, array $params = []): object;
}
