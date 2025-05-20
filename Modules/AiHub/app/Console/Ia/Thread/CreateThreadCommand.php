<?php

namespace Modules\AiHub\Console\Ia\Thread;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\Thread;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class CreateThreadCommand extends Command
{
    /**
     * Assinatura do comando com argumentos e opções flexíveis
     */
    protected $signature = 'ai:chat-start
        {company? : Slug da empresa}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Cria um novo thread para conversação com o assistente';

    /**
     * Empresa selecionada
     */
    protected Company $company;

    /**
     * Serviço de Thread (agora AiService)
     */
    protected AiService $aiService; // Propriedade já declarada como AiService

    /**
     * Construtor para injetar dependências
     */
    public function __construct(AiService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService; // Injeta a instância de AiService
    }

    /**
     * Ponto de entrada principal do comando
     */
    public function handle()
    {
        $this->info("\n🤖 Assistente de Criação de Thread\n");

        try {
            // Inicializa o serviço de Thread - REMOVIDO, agora injetado
            // $this->initializeThreadService(); // REMOVER esta linha

            // Seleciona a empresa
            if (! $this->selectCompany()) {
                return 1;
            }

            // Verifica se a empresa possui assistente
            if (! $this->validateAssistant()) {
                return 1;
            }

            // Confirma a criação do thread
            if (! $this->confirmThreadCreation()) {
                outro('Operação cancelada.');

                return 0;
            }

            // Cria o thread
            if ($this->createThread()) {
                outro('Operação concluída.');

                return 0;
            }

            return 1;
        } catch (\Exception $e) {
            error("\n❌ Erro ao criar thread: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Inicializa o serviço de Thread
     *
     * @return void
     */
    // REMOVER este método completamente
    // private function initializeThreadService(): void
    // {
    //     $this->threadService = new ThreadService();
    // }

    /**
     * Seleciona a empresa para criar o thread
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
        // Lista empresas disponíveis
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
        $this->company = spin(
            fn () => Company::where('slug', $companySlug)->first(),
            'Buscando empresa...'
        );

        if (! $this->company) {
            error("❌ Empresa não encontrada: {$companySlug}");

            return false;
        }

        info("📝 Empresa selecionada: {$this->company->name}");

        return true;
    }

    /**
     * Verifica se a empresa possui um assistente configurado
     *
     * @return bool true se a empresa possui assistente, false caso contrário
     */
    private function validateAssistant(): bool
    {
        $assistant = spin(
            fn () => $this->company->assistants()->first(),
            'Verificando assistente...'
        );

        if (! $assistant) {
            error("❌ Nenhum assistente encontrado para a empresa {$this->company->name}");

            return false;
        }

        return true;
    }

    /**
     * Confirma a criação do thread se estiver em modo interativo
     *
     * @return bool true se a criação foi confirmada ou não está em modo interativo, false caso contrário
     */
    private function confirmThreadCreation(): bool
    {
        if ($this->option('interactive')) {
            return confirm('Deseja criar um novo thread para esta empresa?', true);
        }

        return true;
    }

    /**
     * Cria um novo thread e salva no banco de dados
     *
     * @return bool true se o thread foi criado com sucesso, false caso contrário
     */
    private function createThread(): bool
    {
        info("\n🔄 Criando thread...");

        // Usa o serviço AiService injetado para criar o thread
        $threadId = spin(
            fn () => $this->aiService->thread()->create()->id, // Acessa o serviço de thread através de AiService
            'Aguarde...'
        );

        // Recupera o primeiro assistente da empresa
        $assistant = $this->company->assistants()->first();

        // Salva no banco
        $this->saveThreadToDatabase($threadId, $assistant->id);

        $this->displaySuccessMessage($threadId);

        return true;
    }

    /**
     * Salva o thread no banco de dados
     *
     * @param  string  $threadId  ID do thread criado na API
     * @param  int  $assistantId  ID do assistente no banco local
     */
    private function saveThreadToDatabase(string $threadId, int $assistantId): void
    {
        Thread::create([
            'company_id' => $this->company->id,
            'assistant_id' => $assistantId,
            'thread_id' => $threadId,
            'status' => 'active',
            'metadata' => [
                'created_by' => 'console',
                'company_slug' => $this->company->slug,
            ],
        ]);
    }

    /**
     * Exibe mensagem de sucesso após a criação do thread
     *
     * @param  string  $threadId  ID do thread criado
     */
    private function displaySuccessMessage(string $threadId): void
    {
        info("\n✅ Thread criado com sucesso!");
        info("ID: {$threadId}");
    }
}
