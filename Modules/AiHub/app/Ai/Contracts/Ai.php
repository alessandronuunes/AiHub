<?php

namespace Modules\AiHub\Ai\Contracts;

interface Ai
{
    /**
     * Obtém a instância do serviço de Assistente.
     *
     * @return Assistant
     */
    public function assistant(): Assistant;

    /**
     * Obtém a instância do serviço de Thread.
     *
     * @return Thread
     */
    public function thread(): Thread;

    /**
     * Obtém a instância do serviço de Vector Store.
     *
     * @return VectorStore
     */
    public function vectorStore(): VectorStore;

    /**
     * Obtém a instância do serviço de Arquivo.
     *
     * @return File
     */
    public function file(): File;

    /**
     * Define o slug da empresa para o contexto da requisição.
     *
     * @param string|null $companySlug
     * @return $this
     */
    public function setCompany(?string $companySlug): self;
}