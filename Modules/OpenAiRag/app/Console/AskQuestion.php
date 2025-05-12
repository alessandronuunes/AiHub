<?php

namespace Modules\OpenAiRag\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AskQuestion extends Command
{
    protected $signature = 'openairag:ask {pergunta : A pergunta que você quer fazer}';
    protected $description = 'Faz uma pergunta ao assistente sobre procedimentos e políticas';

    public function handle(ThreadService $threadService)
    {
        // Verificar se o assistente já foi criado
        if (!Storage::exists('openai_assistant_id.txt')) {
            $this->error("Assistente não encontrado. Execute 'openairag:create-assistant' primeiro.");
            return 1;
        }

        $assistantId = Storage::get('openai_assistant_id.txt');
        $pergunta = $this->argument('pergunta');

        $this->info("Processando sua pergunta...");

        try {
            // Criar uma nova thread
            $threadId = $threadService->createThread();
            
            // Adicionar a mensagem
            $threadService->addMessage($threadId, $pergunta);
            
            // Executar o assistente e obter a resposta
            $resposta = $threadService->runAssistant($threadId, $assistantId);
            
            $this->info("\nResposta:");
            $this->line($resposta['content']);
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Erro ao processar pergunta: " . $e->getMessage());
            return 1;
        }
    }
}