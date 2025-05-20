<?php

namespace Modules\AiHub\Ai\Clients\OpenAi;

use Illuminate\Support\Facades\Config;
use Modules\AiHub\Ai\Contracts\Ai;
use Modules\AiHub\Ai\Contracts\Assistant;
use Modules\AiHub\Ai\Contracts\File;
use Modules\AiHub\Ai\Contracts\Thread;
use Modules\AiHub\Ai\Contracts\VectorStore;
use OpenAI\Client as OpenAiClient;

class OpenAi implements Ai
{
    protected OpenAiClient $client;

    protected ?string $companySlug = null;

    // Specific service instances
    protected Assistant $assistantService;

    protected Thread $threadService;

    protected VectorStore $vectorStoreService;

    protected File $fileService;

    /**
     * Constructor.
     *
     * @param  OpenAiClient  $client  OpenAI SDK client instance.
     */
    public function __construct(OpenAiClient $client)
    {
        $this->client = $client;
        // Instantiate specific OpenAI services, passing the SDK client
        $defaultModel = Config::get('aihub.providers.openai.model');
        $this->assistantService = new OpenAiAssistant($this->client, $defaultModel, $this->companySlug);
        $this->threadService = new OpenAiThread($this->client, $this->companySlug);
        $this->vectorStoreService = new OpenAiVectorStore($this->client, $this->companySlug);
        $this->fileService = new OpenAiFile($this->client);
    }

    /**
     * Sets the company slug for the request context.
     *
     * @return $this
     */
    public function setCompany(?string $companySlug): self
    {
        $this->companySlug = $companySlug;
        if ($this->assistantService instanceof OpenAiAssistant) {
            $this->assistantService = new OpenAiAssistant($this->client,
                Config::get('aihub.providers.openai.model'),
                $companySlug);
        }
        if ($this->threadService instanceof OpenAiThread) {
            $this->threadService = new OpenAiThread($this->client, $companySlug);
        }
        if ($this->vectorStoreService instanceof OpenAiVectorStore) {
            $this->vectorStoreService = new OpenAiVectorStore($this->client, $companySlug);
        }

        // $this->fileService->setCompany($companySlug);
        return $this;
    }

    /**
     * Gets the Assistant service instance.
     */
    public function assistant(): Assistant
    {
        return $this->assistantService;
    }

    /**
     * Gets the Thread service instance.
     */
    public function thread(): Thread
    {
        return $this->threadService;
    }

    /**
     * Gets the Vector Store service instance.
     */
    public function vectorStore(): VectorStore
    {
        return $this->vectorStoreService;
    }

    /**
     * Gets the File service instance.
     */
    public function file(): File
    {
        return $this->fileService;
    }
}
