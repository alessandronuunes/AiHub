<?php

namespace Modules\OpenAiRag\Services;

use OpenAI;

class OpenAIService
{
    protected $client;
    protected $companySlug;

    public function __construct($companySlug = null)
    {
        $this->companySlug = $companySlug;
        $apiKey = config('openairag.openai.api_key');
        
        if (!$apiKey) {
            throw new \RuntimeException('A chave da API OpenAI não está configurada. Adicione OPENAI_API_KEY ao seu arquivo .env');
        }

        $this->client = OpenAI::client($apiKey);
    }

    /**
     * Faz upload do conteúdo do arquivo para a OpenAI
     * 
     * @param string $content Conteúdo do arquivo
     * @return string ID do arquivo na OpenAI
     */
    public function uploadFile($content)
    {
        // Gera um identificador único
        $uniqueId = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
        
        // Define o nome base do arquivo temporário
        $tempName = ($this->companySlug ?? 'openai') . '_' . $uniqueId;
        
        // Cria o arquivo temporário com o nome personalizado
        $tempFile = tempnam(sys_get_temp_dir(), '');
        $newTempFile = dirname($tempFile) . DIRECTORY_SEPARATOR . $tempName;
        
        // Remove o arquivo temporário original e cria o novo com o nome correto
        unlink($tempFile);
        file_put_contents($newTempFile, $content);
        
        try {
            // Upload do arquivo usando o endpoint correto
            $response = $this->client->files()->upload([
                'purpose' => 'assistants',
                'file' => fopen($newTempFile, 'r'),
            ]);
            
            return $response->id;
        } finally {
            // Sempre remove o arquivo temporário
            if (file_exists($newTempFile)) {
                unlink($newTempFile);
            }
        }
    }

    /**
     * Exclui um arquivo da OpenAI
     * 
     * @param string $fileId ID do arquivo na OpenAI
     * @return bool
     */
    public function deleteFile($fileId)
    {
        return $this->client->files()->delete($fileId);
    }

    /**
     * Cria um novo assistente na OpenAI
     * 
     * @param array $params Parâmetros para criação do assistente
     * @return object Resposta da API com o assistente criado
     */
    public function createAssistant($params)
    {
        // Garante que os parâmetros obrigatórios estejam presentes
        $defaultParams = [
            'model' => 'gpt-4-1106-preview',
            'tools' => [['type' => 'retrieval']]
        ];

        // Mescla os parâmetros padrão com os fornecidos
        $parameters = array_merge($defaultParams, $params);

        // Cria o assistente usando o cliente OpenAI
        return $this->client->assistants()->create($parameters);
    }

    public function modifyAssistant($assistantId, $params)
    {
        return $this->client->assistants()->modify($assistantId, $params);
    }

    /**
     * Cria um novo vector store na OpenAI
     * 
     * @param array $params Parâmetros para criação do vector store
     * @return object Resposta da API com o vector store criado
     */
    public function createVectorStore($params)
    {
        return $this->client->vectorStores()->create($params);
    }
}