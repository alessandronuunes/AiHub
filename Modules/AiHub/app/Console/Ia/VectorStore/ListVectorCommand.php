<?php

namespace Modules\AiHub\Console\Ia\VectorStore;

use Illuminate\Console\Command;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\VectorStore;
use Modules\AiHub\Ai\AiService;
use function Laravel\Prompts\{error, info, select, table};
use function Modules\AiHub\Console\Ia\VectorStore\spin;

class ListVectorCommand extends Command
{
    protected $signature = 'ai:knowledge-list
        {company? : Slug da empresa}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Lista todas as bases de conhecimento (Vector Stores)';

    /**
     * Empresa selecionada
     */
    protected Company $company;

    /**
     * Serviço de IA
     */
    protected AiService $aiService;

    /**
     * Construtor para injetar dependências
     *
     * @param AiService $aiService
     */
    public function __construct(AiService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    /**
     * Ponto de entrada principal do comando
     */
    public function handle()
    {
        try {
            info("\n📚 Listagem de Bases de Conhecimento\n");

            // Seleciona a empresa
            if (!$this->selectCompany()) {
                return 1;
            }

            // Configura o aiService para a empresa selecionada
            $this->aiService->forCompany($this->company->slug);

            // Lista as Vector Stores da empresa
            if (!$this->listCompanyVectorStores()) {
                return 0;
            }

            // Oferece opções adicionais no modo interativo
            if ($this->option('interactive')) {
                $this->offerAdditionalOptions();
            }

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Erro ao listar bases de conhecimento: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Seleciona a empresa para listar as Vector Stores
     *
     * @return bool true se a empresa foi selecionada com sucesso, false caso contrário
     */
    private function selectCompany(): bool
    {
        $companySlug = $this->argument('company');

        if (!$companySlug || $this->option('interactive')) {
            return $this->selectCompanyInteractively();
        }

        return $this->findCompanyBySlug($companySlug);
    }

    /**
     * Seleciona a empresa interativamente
     *
     * @return bool true se a empresa foi selecionada com sucesso, false caso contrário
     */
    private function selectCompanyInteractively(): bool
    {
        $companies = Company::pluck('name', 'slug')->toArray();

        if (empty($companies)) {
            error("❌ Nenhuma empresa cadastrada!");
            return false;
        }

        $companySlug = select(
            label: 'Selecione a empresa:',
            options: $companies
        );

        return $this->findCompanyBySlug($companySlug);
    }

    /**
     * Encontra a empresa pelo slug
     *
     * @param string $companySlug Slug da empresa
     * @return bool true se a empresa foi encontrada, false caso contrário
     */
    private function findCompanyBySlug(string $companySlug): bool
    {
        $this->company = Company::where('slug', $companySlug)->first();

        if (!$this->company) {
            error("❌ Empresa não encontrada: {$companySlug}");
            return false;
        }

        return true;
    }

    /**
     * Lista as Vector Stores da empresa selecionada
     *
     * @return bool true se Vector Stores foram encontradas, false caso contrário
     */
    private function listCompanyVectorStores(): bool
    {
        $vectorStores = $this->getCompanyVectorStores();

        if ($vectorStores->isEmpty()) {
            info("ℹ️ Nenhuma base de conhecimento encontrada para a empresa {$this->company->name}");
            return false;
        }

        $this->displayVectorStoresTable($vectorStores);
        return true;
    }

    /**
     * Busca as Vector Stores da empresa selecionada
     *
     * @return \Illuminate\Database\Eloquent\Collection Coleção de Vector Stores
     */
    private function getCompanyVectorStores()
    {
        return VectorStore::where('company_id', $this->company->id)->get();
    }

    /**
     * Exibe as Vector Stores em formato de tabela
     *
     * @param \Illuminate\Database\Eloquent\Collection $vectorStores Coleção de Vector Stores
     * @return void
     */
    private function displayVectorStoresTable($vectorStores): void
    {
        // Prepara os dados para a tabela
        $tableData = $this->prepareVectorStoresTableData($vectorStores);

        // Exibe a tabela
        table(
            ['ID', 'Nome', 'Descrição', 'Criado em'],
            $tableData
        );
    }

    /**
     * Prepara os dados das Vector Stores para exibição em tabela
     *
     * @param \Illuminate\Database\Eloquent\Collection $vectorStores Coleção de Vector Stores
     * @return array Dados formatados para a tabela
     */
    private function prepareVectorStoresTableData($vectorStores): array
    {
        return $vectorStores->map(function ($vectorStore) {
            return [
                'ID' => $vectorStore->vector_store_id,
                'Nome' => $vectorStore->name,
                'Descrição' => $vectorStore->description ?: 'N/A',
                'Criado em' => $vectorStore->created_at->format('d/m/Y H:i')
            ];
        })->toArray();
    }

    /**
     * Oferece opções adicionais no modo interativo
     *
     * @return void
     */
    private function offerAdditionalOptions(): void
    {
        $vectorStores = $this->getCompanyVectorStores();

        if ($vectorStores->isEmpty()) {
            return;
        }

        $action = select(
            label: 'O que você deseja fazer?',
            options: [
                'view_files' => 'Ver arquivos de uma Vector Store',
                'create' => 'Criar nova Vector Store',
                'delete' => 'Deletar uma Vector Store',
                'attach' => 'Vincular uma Vector Store a um Assistente',
                'exit' => 'Sair'
            ]
        );

        switch ($action) {
            case 'view_files':
                $this->viewVectorStoreFiles();
                break;
            case 'create':
                $this->call('ai:knowledge-add', [
                    'company' => $this->company->slug,
                    '--interactive' => true
                ]);
                break;
            case 'delete':
                $this->call('ai:knowledge-remove', [
                    'company' => $this->company->slug,
                    '--interactive' => true
                ]);
                break;
            case 'attach':
                $this->call('ai:knowledge-link', [
                    'company' => $this->company->slug,
                    '--interactive' => true
                ]);
                break;
            case 'exit':
            default:
                break;
        }
    }

    /**
     * Exibe os arquivos de uma Vector Store selecionada
     *
     * @return void
     */
    private function viewVectorStoreFiles(): void
    {
        $vectorStores = $this->getCompanyVectorStores();
        $vectorStoreChoices = $vectorStores->pluck('name', 'vector_store_id')->toArray();

        $vectorStoreId = select(
            label: 'Selecione a Vector Store:',
            options: $vectorStoreChoices
        );

        $vectorStore = VectorStore::where('vector_store_id', $vectorStoreId)
            ->where('company_id', $this->company->id)
            ->first();

        if (!$vectorStore) {
            error("❌ Vector Store não encontrada!");
            return;
        }

        $files = spin(
            fn() => $this->aiService->vectorStore()->listFiles($vectorStoreId),
            'Listando arquivos...'
        );

        if (empty($files->data)) {
            info("\nEsta Vector Store não possui arquivos anexados.");
            return;
        }

        info("\nArquivos na Vector Store {$vectorStore->name}:");
        foreach ($files->data as $index => $file) {
            $item = $index + 1;
            info(" {$item}. ID: {$file->id}");
        }
    }
}
