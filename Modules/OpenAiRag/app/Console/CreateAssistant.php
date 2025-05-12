<?php

namespace Modules\OpenAiRag\Console;

use Illuminate\Console\Command;
use Modules\OpenAiRag\Models\Company;
use Modules\OpenAiRag\Models\Assistant;
use Modules\OpenAiRag\Services\OpenAIService;

class CreateAssistant extends Command
{
    protected $signature = 'openairag:create-assistant {company : O slug da empresa}';
    
    protected $description = 'Cria um assistente OpenAI com os arquivos previamente carregados';

    public function handle()
    {
        $companySlug = $this->argument('company');

        // Busca a empresa
        $company = Company::where('slug', $companySlug)->firstOrFail();

        // Instancia o OpenAIService passando o slug da empresa
        $openAIService = new OpenAIService($companySlug);

        // Busca todos os documentos da empresa
        $documents = $company->documents()->whereNotNull('file_id')->get();

        if ($documents->isEmpty()) {
            $this->error("Nenhum documento encontrado para a empresa {$company->name}");
            return 1;
        }

        $fileIds = $documents->pluck('file_id')->toArray();
        
        $this->info("Criando assistente com " . count($fileIds) . " arquivos...");
        
        try {
            // Criar o assistente com os parâmetros corretos
            $response = $openAIService->createAssistant([
                'name' => "Assistente {$company->name}",
                'instructions' => "Você é um assistente especializado em responder perguntas sobre {$company->name}. Use os documentos fornecidos como referência para suas respostas. Seja preciso e direto nas respostas, citando sempre a fonte da informação quando possível.",
                'model' => 'gpt-4-1106-preview',
                'tools' => [['type' => 'retrieval']],
                'file_ids' => $fileIds
            ]);
            
            // Salvar o assistente no banco
            Assistant::create([
                'company_id' => $company->id,
                'assistant_id' => $response->id,
                'name' => "Assistente {$company->name}",
                'instructions' => "Você é um assistente especializado em responder perguntas sobre {$company->name}.",
                'file_ids' => $fileIds
            ]);

            $this->info("Assistente criado com sucesso!");
            return 0;
        } catch (\Exception $e) {
            $this->error("Erro ao criar assistente: " . $e->getMessage());
            return 1;
        }
    }
}
