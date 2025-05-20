<?php

namespace Modules\AiHub\Console\Ia\Thread;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\Thread;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class ListThreadCommand extends Command
{
    protected $signature = 'ai:chat-active
        {company? : Slug da empresa}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Lista todas as conversas ativas';

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
            info("\n💬 Listagem de Conversas\n");

            // Seleciona a empresa
            if (! $this->selectCompany()) {
                return 1;
            }

            // Lista os threads da empresa
            if (! $this->listCompanyThreads()) {
                return 0;
            }

            // Oferecer opção para visualizar mensagens de um thread específico
            if ($this->option('interactive')) {
                $this->offerViewMessages();
            }

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Erro ao listar conversas: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Seleciona a empresa para listar threads
     *
     * @return bool true se a empresa foi selecionada com sucesso, false caso contrário
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
     * @return bool true se a empresa foi selecionada com sucesso, false caso contrário
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
     * Encontra a empresa pelo slug
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
     * Lista os threads da empresa selecionada
     *
     * @return bool true se threads foram encontrados, false caso contrário
     */
    private function listCompanyThreads(): bool
    {
        $threads = $this->getCompanyThreads();

        if ($threads->isEmpty()) {
            info("ℹ️ Nenhuma conversa encontrada para a empresa {$this->company->name}");

            return false;
        }

        $this->displayThreadsTable($threads);

        return true;
    }

    /**
     * Busca os threads da empresa selecionada
     *
     * @return \Illuminate\Database\Eloquent\Collection Coleção de threads
     */
    private function getCompanyThreads()
    {
        return Thread::where('company_id', $this->company->id)
            ->with('assistant')
            ->get();
    }

    /**
     * Exibe os threads em formato de tabela
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $threads  Coleção de threads
     */
    private function displayThreadsTable($threads): void
    {
        // Prepara os dados para a tabela
        $tableData = $this->prepareThreadsTableData($threads);

        // Exibe a tabela
        table(
            ['ID', 'Assistente', 'Criado em', 'Status'],
            $tableData
        );
    }

    /**
     * Prepara os dados dos threads para exibição em tabela
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $threads  Coleção de threads
     * @return array Dados formatados para a tabela
     */
    private function prepareThreadsTableData($threads): array
    {
        return $threads->map(function ($thread) {
            return [
                'ID' => $thread->thread_id,
                'Assistente' => $thread->assistant->name,
                'Criado em' => $thread->created_at->format('d/m/Y H:i'),
                'Status' => $thread->status,
            ];
        })->toArray();
    }

    /**
     * Oferece opção para visualizar mensagens de um thread específico
     */
    private function offerViewMessages(): void
    {
        $threads = $this->getCompanyThreads();

        if ($threads->isEmpty()) {
            return;
        }

        $threadOptions = $threads->pluck('thread_id')->toArray();
        $threadOptions['cancel'] = 'Cancelar';

        $selectedThread = select(
            label: 'Selecione um thread para visualizar mensagens:',
            options: $threadOptions
        );

        if ($selectedThread !== 'cancel') {
            $this->call('ai:chat-list', [
                'thread_id' => $selectedThread,
                '--interactive' => true,
            ]);
        }
    }
}
