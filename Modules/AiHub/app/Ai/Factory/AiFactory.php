<?php

namespace Modules\AiHub\Ai\Factory;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
// We don't need to import the facade if it's globally registered
use Modules\AiHub\Ai\Clients\OpenAi\OpenAi;
use Modules\AiHub\Ai\Contracts\Ai;

class AiFactory
{
    /**
     * Creates an instance of the AI client.
     *
     * @param  string|null  $provider  The AI provider name (e.g.: 'openai'). If null, uses the default from configuration.
     * @param  string|null  $companySlug  The company slug for context (optional).
     *
     * @throws InvalidArgumentException If the provider is not supported or the configuration is missing.
     */
    public function create(?string $provider = null, ?string $companySlug = null): Ai
    {
        $provider = $provider ?? Config::get('aihub.ai_provider');

        switch (strtolower($provider)) {
            case 'openai':
                return $this->createOpenAiClient($companySlug);
                // Add other providers here in the future
            default:
                throw new InvalidArgumentException("AI provider '{$provider}' not supported.");
        }
    }

    /**
     * Creates an instance of the OpenAI client.
     *
     * @param  string|null  $companySlug  The company slug for context (optional).
     *
     * @throws RuntimeException If the OpenAI API key is not configured.
     */
    protected function createOpenAiClient(?string $companySlug): OpenAi
    {
        // Use the same configuration pattern as other services
        $apiKey = Config::get('aihub.providers.openai.api_key');

        // Also check the alternative path as per provided configuration
        if (! $apiKey) {
            $apiKey = Config::get('aihub.openai.api_key');
        }

        if (! $apiKey) {
            throw new \RuntimeException('The OpenAI API key is not configured. Add OPENAI_API_KEY to your .env file');
        }

        // Use the OpenAI facade without importing, as in other services
        $client = \OpenAI::client($apiKey);

        // Get the default model
        $defaultModel = Config::get('aihub.providers.openai.model');
        if (! $defaultModel) {
            $defaultModel = Config::get('aihub.openai.model', 'gpt-4o');
        }

        // Instantiate all services with the correct parameters
        $openAiInstance = new OpenAi($client);

        // Set the companySlug, if provided
        if ($companySlug !== null) {
            $openAiInstance->setCompany($companySlug);
        }

        return $openAiInstance;
    }
}
