<?php

namespace Modules\OpenAiRag\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\OpenAiRag\Models\Company;
use Modules\OpenAiRag\Models\Document;
use Modules\OpenAiRag\Services\OpenAIService;

class UploadDocuments extends Command
{
    protected $signature = 'openairag:upload-docs 
                          {company : O slug da empresa} 
                          {path? : Caminho para os documentos}';
    
    protected $description = 'Faz upload dos documentos para a OpenAI';

    public function handle()
    {
        $companySlug = $this->argument('company');
        $relativePath = "companies/{$companySlug}/documents";
        
        // Se um caminho personalizado foi fornecido, use-o
        $basePath = $this->argument('path') ?? storage_path("app/{$relativePath}");

        // Busca a empresa
        $company = Company::where('slug', $companySlug)->firstOrFail();

        // Instancia o OpenAIService passando o slug da empresa
        $openAIService = new OpenAIService($companySlug);

        // Verifica se o diretório existe
        if (!is_dir($basePath)) {
            $this->error("Diretório não encontrado: {$basePath}");
            $this->info("Tentando criar o diretório...");
            
            // Usa o Storage do Laravel para criar o diretório
            if (!Storage::makeDirectory($relativePath)) {
                $this->error("Não foi possível criar o diretório");
                return 1;
            }
            
            $this->info("Diretório criado com sucesso!");
            $this->info("Por favor, coloque seus arquivos .md em: {$basePath}");
            return 0;
        }

        $files = glob($basePath . '/*.md');
        
        if (empty($files)) {
            $this->warn("Nenhum arquivo .md encontrado em: {$basePath}");
            return 1;
        }

        $bar = $this->output->createProgressBar(count($files));

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $title = basename($file, '.md');
            
            // Upload para OpenAI
            $fileId = $openAIService->uploadFile($content);
            
            // Salva no banco
            Document::create([
                'company_id' => $company->id,
                'title' => $title,
                'content' => $content,
                'file_id' => $fileId
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nDocumentos enviados com sucesso!");
        
        return 0;
    }
}
