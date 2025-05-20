<?php

namespace Modules\AiHub\Ai\Contracts;

interface Ai
{
    /**
     * Gets the instance of the Assistant service.
     */
    public function assistant(): Assistant;

    /**
     * Gets the instance of the Thread service.
     */
    public function thread(): Thread;

    /**
     * Gets the instance of the Vector Store service.
     */
    public function vectorStore(): VectorStore;

    /**
     * Gets the instance of the File service.
     */
    public function file(): File;

    /**
     * Sets the company slug for the request context.
     *
     * @return $this
     */
    public function setCompany(?string $companySlug): self;
}
