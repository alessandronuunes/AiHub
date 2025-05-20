<?php

namespace Modules\AiHub\Ai\Factory;

use Modules\AiHub\Ai\Contracts\Ai;
use Modules\AiHub\Ai\Clients\OpenAi\OpenAi;
// Não precisamos importar a fachada se ela estiver registrada globalmente
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class AiFactory
{
    /**
     * Cria uma instância do cliente de IA.
     *
     * @param string|null $provider O nome do provedor de IA (ex: 'openai'). Se nulo, usa o padrão da configuração.
     * @param string|null $companySlug O slug da empresa para contexto (opcional).
     * @return Ai
     * @throws InvalidArgumentException Se o provedor não for suportado ou a configuração estiver faltando.
     */
    public function create(?string $provider = null, ?string $companySlug = null): Ai
    {
        $provider = $provider ?? Config::get('AiHub.ai_provider');
        
        switch (strtolower($provider)) {
            case 'openai':
                return $this->createOpenAiClient($companySlug);
            // Adicionar outros provedores aqui no futuro
            default:
                throw new InvalidArgumentException("Provedor de IA '{$provider}' não suportado.");
        }
    }

    /**
     * Cria uma instância do cliente OpenAI.
     *
     * @param string|null $companySlug O slug da empresa para contexto (opcional).
     * @return OpenAi
     * @throws RuntimeException Se a chave da API OpenAI não estiver configurada.
     */
    protected function createOpenAiClient(?string $companySlug): OpenAi
    {
        // Usar o mesmo padrão de configuração dos outros serviços
        $apiKey = Config::get('AiHub.providers.openai.api_key');
        
        // Verificar também no caminho alternativo conforme configuração fornecida
        if (!$apiKey) {
            $apiKey = Config::get('AiHub.openai.api_key');
        }

        if (!$apiKey) {
            throw new \RuntimeException('A chave da API OpenAI não está configurada. Adicione OPENAI_API_KEY ao seu arquivo .env');
        }

        // Usar a fachada OpenAI sem importação, como nos outros serviços
        $client = \OpenAI::client($apiKey);

        // Obter o modelo padrão
        $defaultModel = Config::get('AiHub.providers.openai.model');
        if (!$defaultModel) {
            $defaultModel = Config::get('AiHub.openai.model', 'gpt-4o');
        }

        // Instanciar todos os serviços com os parâmetros corretos
        $openAiInstance = new OpenAi($client);
        
        // Definir o companySlug, se fornecido
        if ($companySlug !== null) {
            $openAiInstance->setCompany($companySlug);
        }

        return $openAiInstance;
    }
}