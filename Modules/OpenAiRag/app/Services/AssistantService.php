<?php

use OpenAI;

/**
 * A criação do assistente envolve definir um nome, descrição, modelo a ser usado e vincular os arquivos de conhecimento que você carregou anteriormente. 
 * O assistente é a entidade principal que responderá às consultas dos atendentes com base nos documentos fornecidos.
 */
class AssistantService
{
    protected $client;
    
    public function __construct()
    {
        $this->client = OpenAI::client(config('openairag.openai.api_key'));
    }
    
    /**
     * Cria um novo assistente com os arquivos especificados
     * @param array $fileIds IDs dos arquivos da base de conhecimento
     * @return string Assistant ID
     */
    public function createAssistant(array $fileIds)
    {
        // Instruções personalizadas para o assistente
        $instructions = "Você é um assistente especializado para a VMix Call, uma empresa de call center. 
        Sua função é fornecer informações precisas e atualizadas sobre os procedimentos, políticas 
        e detalhes técnicos dos provedores de internet atendidos, especialmente a Ativa Telecom. 
        Responda de forma objetiva e direta, citando a fonte da informação quando possível. 
        Se não tiver certeza sobre alguma informação, indique isso claramente. 
        Priorize informações mais recentes quando houver conflitos.";
        
        $response = $this->client->assistants()->create([
            'name' => 'Base de Conhecimento VMix Call',
            'description' => 'Assistente para consulta da base de conhecimento dos provedores atendidos',
            'model' => 'gpt-4-turbo',
            'tools' => [
                ['type' => 'retrieval'] // Habilita a recuperação de informações dos arquivos
            ],
            'file_ids' => $fileIds,
            'instructions' => $instructions,
        ]);
        
        return $response->id;
    }
    
    /**
     * Atualiza um assistente existente
     * @param string $assistantId ID do assistente
     * @param array $fileIds Novos IDs de arquivos
     * @return mixed
     */
    public function updateAssistant(string $assistantId, array $fileIds = null)
    {
        $updateData = [];
        
        if ($fileIds !== null) {
            $updateData['file_ids'] = $fileIds;
        }
        
        if (empty($updateData)) {
            throw new \Exception("Nenhum dado fornecido para atualização");
        }
        
        return $this->client->assistants()->update($assistantId, $updateData);
    }
}