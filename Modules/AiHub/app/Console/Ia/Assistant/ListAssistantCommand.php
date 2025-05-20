<?php

namespace Modules\AiHub\Console\Ia\Assistant;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Assistant;
use Modules\AiHub\Models\Company;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class ListAssistantCommand extends Command
{
    protected $signature = 'ai:assistant-list
        {company? : Slug da empresa}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Lista todos os assistentes disponíveis';

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
            info("\n📋 Listagem de Assistentes\n");

            // Seleciona a empresa
            if (! $this->selectCompany()) {
                return 1;
            }

            // Configura o aiService para a empresa selecionada
            $this->aiService->forCompany($this->company->slug);

            // Lista os assistentes da empresa
            if (! $this->listCompanyAssistants()) {
                return 0;
            }

            // Oferece opções adicionais no modo interativo
            if ($this->option('interactive')) {
                $this->offerAdditionalOptions();
            }

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Erro ao listar assistentes: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Seleciona a empresa a ser usada para listar assistentes
     *
     * @return bool true se uma empresa foi selecionada, false caso contrário
     */
    private function selectCompany(): bool
    {
        $companySlug = $this->argument('company');

        if (! $companySlug || $this->option('interactive')) {
            return $this->selectCompanyInteractively();
        }

        return $this->findCompanyBySlug($companySlug);
    }

    /**
     * Seleciona a empresa interativamente
     *
     * @return bool true se uma empresa foi selecionada, false caso contrário
     */
    private function selectCompanyInteractively(): bool
    {
        $companies = Company::pluck('name', 'slug')->toArray();

        if (empty($companies)) {
            error('❌ Nenhuma empresa cadastrada!');

            return false;
        }

        $companySlug = select(
            label: 'Selecione a empresa:',
            options: $companies
        );

        return $this->findCompanyBySlug($companySlug);
    }

    /**
     * Encontra uma empresa pelo slug
     *
     * @param  string  $companySlug  Slug da empresa
     * @return bool true se a empresa foi encontrada, false caso contrário
     */
    private function findCompanyBySlug(string $companySlug): bool
    {
        $this->company = Company::where('slug', $companySlug)->first();

        if (! $this->company) {
            error("❌ Empresa não encontrada: {$companySlug}");

            return false;
        }

        return true;
    }

    /**
     * Lista os assistentes da empresa selecionada
     *
     * @return bool true se assistentes foram encontrados, false caso contrário
     */
    private function listCompanyAssistants(): bool
    {
        $assistants = $this->getCompanyAssistants();

        if ($assistants->isEmpty()) {
            info("ℹ️ Nenhum assistente encontrado para a empresa {$this->company->name}");

            return false;
        }

        $this->displayAssistantsTable($assistants);

        return true;
    }

    /**
     * Busca os assistentes da empresa selecionada
     *
     * @return \Illuminate\Database\Eloquent\Collection Coleção de assistentes
     */
    private function getCompanyAssistants()
    {
        return Assistant::where('company_id', $this->company->id)->get();
    }

    /**
     * Exibe os assistentes em formato de tabela
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $assistants  Coleção de assistentes
     */
    private function displayAssistantsTable($assistants): void
    {
        // Prepara os dados para a tabela
        $tableData = $this->prepareAssistantsTableData($assistants);

        // Exibe a tabela
        table(
            ['ID', 'Nome', 'Vector Stores'],
            $tableData
        );
    }

    /**
     * Prepara os dados dos assistentes para exibição em tabela
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $assistants  Coleção de assistentes
     * @return array Dados formatados para a tabela
     */
    private function prepareAssistantsTableData($assistants): array
    {
        return $assistants->map(function ($assistant) {
            $vectorStores = $assistant->vectorStores->pluck('name')->join(', ');

            return [
                'ID' => $assistant->assistant_id,
                'Nome' => $assistant->name,
                'Vector Stores' => $vectorStores ?: 'Nenhuma',
            ];
        })->toArray();
    }

    /**
     * Oferece opções adicionais no modo interativo
     */
    private function offerAdditionalOptions(): void
    {
        $assistants = $this->getCompanyAssistants();

        if ($assistants->isEmpty()) {
            return;
        }

        $action = select(
            label: 'O que você deseja fazer?',
            options: [
                'view_details' => 'Ver detalhes de um assistente',
                'create_thread' => 'Criar uma nova conversa com um assistente',
                'create_assistant' => 'Criar um novo assistente',
                'create_vector' => 'Criar uma nova Vector Store',
                'exit' => 'Sair',
            ]
        );

        switch ($action) {
            case 'view_details':
                $this->viewAssistantDetails();
                break;
            case 'create_thread':
                $this->call('ai:chat-start', [
                    'company' => $this->company->slug,
                    '--interactive' => true,
                ]);
                break;
            case 'create_assistant':
                $this->call('ai:assistant-create');
                break;
            case 'create_vector':
                $this->call('ai:knowledge-add', [
                    'company' => $this->company->slug,
                    '--interactive' => true,
                ]);
                break;
            case 'exit':
            default:
                break;
        }
    }

    /**
     * Exibe detalhes de um assistente selecionado
     */
    private function viewAssistantDetails(): void
    {
        $assistants = $this->getCompanyAssistants();
        $assistantChoices = $assistants->pluck('name', 'assistant_id')->toArray();

        $assistantId = select(
            label: 'Selecione o assistente:',
            options: $assistantChoices
        );

        $assistant = Assistant::where('assistant_id', $assistantId)
            ->where('company_id', $this->company->id)
            ->first();

        if (! $assistant) {
            error('❌ Assistente não encontrado!');

            return;
        }

        // Busca detalhes atualizados na API
        $assistantDetails = spin(
            fn () => $this->aiService->assistant()->retrieve($assistantId),
            'Buscando detalhes do assistente...'
        );

        info("\nDetalhes do Assistente:");
        info("Nome: {$assistant->name}");
        info("ID: {$assistant->assistant_id}");
        info("Instruções: {$assistant->instructions}");
        info("Modelo: {$assistantDetails->model}");

        $toolsList = collect($assistantDetails->tools)->pluck('type')->join(', ');
        info("Ferramentas: {$toolsList}");

        $vectorStores = $assistant->vectorStores->pluck('name')->join(', ');
        info('Vector Stores: '.($vectorStores ?: 'Nenhuma'));
    }
}
