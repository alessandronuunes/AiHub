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

    // Instâncias dos serviços específicos
    protected Assistant $assistantService;

    protected Thread $threadService;

    protected VectorStore $vectorStoreService;

    protected File $fileService;

    /**
     * Construtor.
     *
     * @param  OpenAiClient  $client  Instância do cliente OpenAI SDK.
     */
    public function __construct(OpenAiClient $client)
    {
        $this->client = $client;
        // Instancia os serviços específicos da OpenAI, passando o cliente SDK
        $defaultModel = Config::get('aihub.providers.openai.model');
        $this->assistantService = new OpenAiAssistant($this->client, $defaultModel, $this->companySlug);
        $this->threadService = new OpenAiThread($this->client, $this->companySlug);
        $this->vectorStoreService = new OpenAiVectorStore($this->client, $this->companySlug);
        $this->fileService = new OpenAiFile($this->client);
    }

    /**
     * Define o slug da empresa para o contexto da requisição.
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
     * Obtém a instância do serviço de Assistente.
     */
    public function assistant(): Assistant
    {
        return $this->assistantService;
    }

    /**
     * Obtém a instância do serviço de Thread.
     */
    public function thread(): Thread
    {
        return $this->threadService;
    }

    /**
     * Obtém a instância do serviço de Vector Store.
     */
    public function vectorStore(): VectorStore
    {
        return $this->vectorStoreService;
    }

    /**
     * Obtém a instância do serviço de Arquivo.
     */
    public function file(): File
    {
        return $this->fileService;
    }
}
