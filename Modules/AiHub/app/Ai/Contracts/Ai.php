<?php

namespace Modules\AiHub\Ai\Contracts;

interface Ai
{
    /**
     * Obtém a instância do serviço de Assistente.
     */
    public function assistant(): Assistant;

    /**
     * Obtém a instância do serviço de Thread.
     */
    public function thread(): Thread;

    /**
     * Obtém a instância do serviço de Vector Store.
     */
    public function vectorStore(): VectorStore;

    /**
     * Obtém a instância do serviço de Arquivo.
     */
    public function file(): File;

    /**
     * Define o slug da empresa para o contexto da requisição.
     *
     * @return $this
     */
    public function setCompany(?string $companySlug): self;
}
