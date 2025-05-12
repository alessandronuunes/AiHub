<?php

use OpenAI;

/**
 * Para interagir com o assistente, você precisa criar threads e mensagens. 
 * Uma thread representa uma conversa contínua com o assistente. 
 * Isso permite manter o contexto entre as mensagens, o que é crucial para obter respostas precisas e relevantes.
 */
class ThreadService
{
    protected $client;
    
    public function __construct()
    {
        $this->client = OpenAI::client(config('openairag.openai.api_key'));
    }
    
    /**
     * Cria uma nova thread
     * @return string Thread ID
     */
    public function createThread()
    {
        $response = $this->client->threads()->create([]);
        return $response->id;
    }
    
    /**
     * Adiciona uma mensagem à thread
     * @param string $threadId ID da thread
     * @param string $content Conteúdo da mensagem
     * @return string Message ID
     */
    public function addMessage(string $threadId, string $content)
    {
        $response = $this->client->threads()->messages()->create($threadId, [
            'role' => 'user',
            'content' => $content
        ]);
        
        return $response->id;
    }
    
    /**
     * Executa o assistente na thread
     * @param string $threadId ID da thread
     * @param string $assistantId ID do assistente
     * @return array Resposta do assistente
     */
    public function runAssistant(string $threadId, string $assistantId)
    {
        // Iniciar a execução
        $run = $this->client->threads()->runs()->create($threadId, [
            'assistant_id' => $assistantId
        ]);
        
        // Aguardar a conclusão (poll)
        $status = $run->status;
        $maxAttempts = 30;
        $attempts = 0;
        
        while ($status !== 'completed' && $status !== 'failed' && $attempts < $maxAttempts) {
            sleep(1); // Aguarda 1 segundo antes de verificar novamente
            $run = $this->client->threads()->runs()->retrieve($threadId, $run->id);
            $status = $run->status;
            $attempts++;
        }
        
        if ($status !== 'completed') {
            throw new \Exception("Execução não concluída. Status: $status");
        }
        
        // Obter as mensagens (respostas) do assistente
        $messages = $this->client->threads()->messages()->list($threadId, [
            'order' => 'desc',
            'limit' => 1
        ]);
        
        return [
            'content' => $messages->data[0]->content[0]->text->value,
            'role' => $messages->data[0]->role,
            'created_at' => $messages->data[0]->created_at
        ];
    }
}