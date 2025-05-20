<?php

namespace Modules\AiHub\Ai;

use Modules\AiHub\Ai\Contracts\Ai;
use Modules\AiHub\Ai\Contracts\Assistant;
use Modules\AiHub\Ai\Contracts\File;
use Modules\AiHub\Ai\Contracts\Thread;
use Modules\AiHub\Ai\Contracts\VectorStore;
use Modules\AiHub\Ai\Factory\AiFactory;

/**
 * Main service for interacting with AI providers.
 * Acts as an entry point and facade for different AI services (Assistant, Thread, etc.).
 */
class AiService
{
    protected AiFactory $factory;

    protected ?string $defaultProvider = null;

    protected ?string $defaultCompanySlug = null;

    /**
     * Constructor.
     *
     * @param  AiFactory  $factory  AI factory instance.
     */
    public function __construct(AiFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Sets the default AI provider for this service instance.
     *
     * @param  string  $provider  The provider name (e.g.: 'openai').
     * @return $this
     */
    public function provider(string $provider): self
    {
        $this->defaultProvider = $provider;

        return $this;
    }

    /**
     * Sets the default company slug for this service instance.
     *
     * @param  string|null  $companySlug  The company slug.
     * @return $this
     */
    public function forCompany(?string $companySlug): self
    {
        $this->defaultCompanySlug = $companySlug;

        return $this;
    }

    /**
     * Gets the configured AI client instance.
     *
     * @param  string|null  $provider  The provider name (if different from default).
     * @param  string|null  $companySlug  The company slug (if different from default).
     */
    protected function getAiClient(?string $provider = null, ?string $companySlug = null): Ai
    {
        $providerToUse = $provider ?? $this->defaultProvider;
        $companySlugToUse = $companySlug ?? $this->defaultCompanySlug;

        $client = $this->factory->create($providerToUse, $companySlugToUse);

        // Reset defaults after getting the client, to avoid affecting future calls
        $this->defaultProvider = null;
        $this->defaultCompanySlug = null;

        return $client;
    }

    /**
     * Gets the Assistant service from the configured AI provider.
     *
     * @param  string|null  $provider  The provider name (if different from default).
     * @param  string|null  $companySlug  The company slug (if different from default).
     */
    public function assistant(?string $provider = null, ?string $companySlug = null): Assistant
    {
        return $this->getAiClient($provider, $companySlug)->assistant();
    }

    /**
     * Gets the Thread service from the configured AI provider.
     *
     * @param  string|null  $provider  The provider name (if different from default).
     * @param  string|null  $companySlug  The company slug (if different from default).
     */
    public function thread(?string $provider = null, ?string $companySlug = null): Thread
    {
        return $this->getAiClient($provider, $companySlug)->thread();
    }

    /**
     * Gets the Vector Store service from the configured AI provider.
     *
     * @param  string|null  $provider  The provider name (if different from default).
     * @param  string|null  $companySlug  The company slug (if different from default).
     */
    public function vectorStore(?string $provider = null, ?string $companySlug = null): VectorStore
    {
        return $this->getAiClient($provider, $companySlug)->vectorStore();
    }

    /**
     * Gets the File service from the configured AI provider.
     *
     * @param  string|null  $provider  The provider name (if different from default).
     * @param  string|null  $companySlug  The company slug (if different from default).
     */
    public function file(?string $provider = null, ?string $companySlug = null): File
    {
        return $this->getAiClient($provider, $companySlug)->file();
    }

    // Example of how you can add convenience methods directly here
    // public function createAssistant(array $params): object
    // {
    //     return $this->assistant()->create($params);
    // }

    // public function addMessageToThread(string $threadId, string $content, string $role = 'user', array $params = []): object
    // {
    //     return $this->thread()->addMessage($threadId, $content, $role, $params);
    // }
}
