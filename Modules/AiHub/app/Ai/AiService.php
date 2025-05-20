<?php

namespace Modules\AiHub\Ai;

use Modules\AiHub\Ai\Contracts\Ai;
use Modules\AiHub\Ai\Factory\AiFactory;
use Modules\AiHub\Ai\Contracts\Assistant;
use Modules\AiHub\Ai\Contracts\Thread;
use Modules\AiHub\Ai\Contracts\VectorStore;
use Modules\AiHub\Ai\Contracts\File;

/**
 * Serviço principal para interagir com provedores de IA.
 * Atua como um ponto de entrada e fachada para os diferentes serviços de IA (Assistant, Thread, etc.).
 */
class AiService
{
    protected AiFactory $factory;
    protected ?string $defaultProvider = null;
    protected ?string $defaultCompanySlug = null;

    /**
     * Construtor.
     *
     * @param AiFactory $factory Instância da fábrica de IA.
     */
    public function __construct(AiFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Define o provedor de IA padrão para esta instância do serviço.
     *
     * @param string $provider O nome do provedor (ex: 'openai').
     * @return $this
     */
    public function provider(string $provider): self
    {
        $this->defaultProvider = $provider;
        return $this;
    }

    /**
     * Define o slug da empresa padrão para esta instância do serviço.
     *
     * @param string|null $companySlug O slug da empresa.
     * @return $this
     */
    public function forCompany(?string $companySlug): self
    {
        $this->defaultCompanySlug = $companySlug;
        return $this;
    }

    /**
     * Obtém a instância do cliente de IA configurado.
     *
     * @param string|null $provider O nome do provedor (se diferente do padrão).
     * @param string|null $companySlug O slug da empresa (se diferente do padrão).
     * @return Ai
     */
    protected function getAiClient(?string $provider = null, ?string $companySlug = null): Ai
    {
        $providerToUse = $provider ?? $this->defaultProvider;
        $companySlugToUse = $companySlug ?? $this->defaultCompanySlug;

        $client = $this->factory->create($providerToUse, $companySlugToUse);

        // Resetar defaults após obter o cliente, para não afetar chamadas futuras
        $this->defaultProvider = null;
        $this->defaultCompanySlug = null;

        return $client;
    }

    /**
     * Obtém o serviço de Assistente do provedor de IA configurado.
     *
     * @param string|null $provider O nome do provedor (se diferente do padrão).
     * @param string|null $companySlug O slug da empresa (se diferente do padrão).
     * @return Assistant
     */
    public function assistant(?string $provider = null, ?string $companySlug = null): Assistant
    {
        return $this->getAiClient($provider, $companySlug)->assistant();
    }

    /**
     * Obtém o serviço de Thread do provedor de IA configurado.
     *
     * @param string|null $provider O nome do provedor (se diferente do padrão).
     * @param string|null $companySlug O slug da empresa (se diferente do padrão).
     * @return Thread
     */
    public function thread(?string $provider = null, ?string $companySlug = null): Thread
    {
        return $this->getAiClient($provider, $companySlug)->thread();
    }

    /**
     * Obtém o serviço de Vector Store do provedor de IA configurado.
     *
     * @param string|null $provider O nome do provedor (se diferente do padrão).
     * @param string|null $companySlug O slug da empresa (se diferente do padrão).
     * @return VectorStore
     */
    public function vectorStore(?string $provider = null, ?string $companySlug = null): VectorStore
    {
        return $this->getAiClient($provider, $companySlug)->vectorStore();
    }

    /**
     * Obtém o serviço de Arquivo do provedor de IA configurado.
     *
     * @param string|null $provider O nome do provedor (se diferente do padrão).
     * @param string|null $companySlug O slug da empresa (se diferente do padrão).
     * @return File
     */
    public function file(?string $provider = null, ?string $companySlug = null): File
    {
        return $this->getAiClient($provider, $companySlug)->file();
    }

    // Exemplo de como você pode adicionar métodos de conveniência diretamente aqui
    // public function createAssistant(array $params): object
    // {
    //     return $this->assistant()->create($params);
    // }

    // public function addMessageToThread(string $threadId, string $content, string $role = 'user', array $params = []): object
    // {
    //     return $this->thread()->addMessage($threadId, $content, $role, $params);
    // }
}