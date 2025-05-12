<?php

namespace Modules\OpenAiRag\Console;

use Illuminate\Console\Command;
use Modules\OpenAiRag\Models\Company;
use Modules\OpenAiRag\Models\Document;
use Modules\OpenAiRag\Services\OpenAIService;

class DeleteDocuments extends Command
{
    protected $signature = 'openairag:delete-docs {company : O slug da empresa}';
    
    protected $description = 'Exclui todos os documentos da empresa da OpenAI';

    public function handle(OpenAIService $openAIService)
    {
        $companySlug = $this->argument('company');

        // Busca a empresa
        $company = Company::where('slug', $companySlug)->firstOrFail();

        // Busca todos os documentos da empresa
        $documents = Document::where('company_id', $company->id)->get();

        if ($documents->isEmpty()) {
            $this->warn("Nenhum documento encontrado para a empresa {$company->name}");
            return 0;
        }

        $bar = $this->output->createProgressBar(count($documents));

        foreach ($documents as $document) {
            if ($document->file_id) {
                try {
                    // Exclui o arquivo da OpenAI
                    $openAIService->deleteFile($document->file_id);
                } catch (\Exception $e) {
                    $this->error("Erro ao excluir arquivo {$document->file_id}: {$e->getMessage()}");
                }
            }
            
            // Exclui o registro do banco
            $document->delete();
            
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nDocumentos excluídos com sucesso!");
        
        return 0;
    }
}