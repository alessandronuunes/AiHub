<?php

namespace Modules\AiHub\Console\Ia\VectorStore;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Assistant;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\VectorStore;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class AttachVectorCommand extends Command
{
    protected $signature = 'ai:knowledge-link
        {company? : Slug da empresa}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Associa uma Vector Store a um Assistente';

    /**
     * Empresa selecionada
     */
    protected Company $company;

    /**
     * Assistente selecionado
     */
    protected Assistant $assistant;

    /**
     * Vector Store selecionada
     */
    protected VectorStore $vectorStore;

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
        info("\n🔗 Assistente de Vinculação de Vector Store\n");

        try {
            // Seleciona a empresa
            if (! $this->selectCompany()) {
                return 1;
            }

            // Configura o aiService para a empresa selecionada
            $this->aiService->forCompany($this->company->slug);

            // Seleciona o assistente
            if (! $this->selectAssistant()) {
                return 1;
            }

            // Seleciona a Vector Store
            if (! $this->selectVectorStore()) {
                return 1;
            }

            // Confirma a vinculação
            if (! $this->confirmAttachment()) {
                outro('Operação cancelada.');

                return 0;
            }

            // Executa a vinculação
            if ($this->executeAttachment()) {
                outro('Operação concluída.');

                return 0;
            }

            return 1;

        } catch (\Exception $e) {
            error("\n❌ Erro ao vincular Vector Store: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Seleciona a empresa para a vinculação
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
     * Seleciona o assistente para a vinculação
     *
     * @return bool true se o assistente foi selecionado com sucesso, false caso contrário
     */
    private function selectAssistant(): bool
    {
        // Busca os assistentes da empresa
        $assistants = $this->getCompanyAssistants();

        if ($assistants->isEmpty()) {
            error('❌ Nenhum Assistente encontrado para a empresa!');

            return false;
        }

        // Lista os assistentes para seleção
        $assistantChoices = $assistants->pluck('name', 'assistant_id')->toArray();
        $assistantId = select(
            label: 'Selecione o Assistente:',
            options: $assistantChoices
        );

        return $this->findAssistantById($assistantId);
    }

    /**
     * Recupera os assistentes da empresa selecionada
     *
     * @return \Illuminate\Database\Eloquent\Collection Coleção de assistentes
     */
    private function getCompanyAssistants()
    {
        return Assistant::where('company_id', $this->company->id)->get();
    }

    /**
     * Encontra um assistente pelo ID
     *
     * @param  string  $assistantId  ID do assistente na OpenAI
     * @return bool true se o assistente foi encontrado, false caso contrário
     */
    private function findAssistantById(string $assistantId): bool
    {
        $this->assistant = spin(
            fn () => Assistant::where('assistant_id', $assistantId)
                ->where('company_id', $this->company->id)
                ->first(),
            'Buscando assistente...'
        );

        if (! $this->assistant) {
            error('❌ Assistente não encontrado!');

            return false;
        }

        return true;
    }

    /**
     * Seleciona a Vector Store para a vinculação
     *
     * @return bool true se a Vector Store foi selecionada com sucesso, false caso contrário
     */
    private function selectVectorStore(): bool
    {
        // Busca as Vector Stores da empresa
        $vectorStores = $this->getCompanyVectorStores();

        if ($vectorStores->isEmpty()) {
            error('❌ Nenhuma Vector Store encontrada!');

            return false;
        }

        // Lista as Vector Stores para seleção
        $vectorChoices = $vectorStores->pluck('name', 'vector_store_id')->toArray();
        $vectorStoreId = select(
            label: 'Selecione a Vector Store:',
            options: $vectorChoices
        );

        return $this->findVectorStoreById($vectorStoreId);
    }

    /**
     * Recupera as Vector Stores da empresa selecionada
     *
     * @return \Illuminate\Database\Eloquent\Collection Coleção de Vector Stores
     */
    private function getCompanyVectorStores()
    {
        return VectorStore::where('company_id', $this->company->id)->get();
    }

    /**
     * Encontra uma Vector Store pelo ID
     *
     * @param  string  $vectorStoreId  ID da Vector Store na OpenAI
     * @return bool true se a Vector Store foi encontrada, false caso contrário
     */
    private function findVectorStoreById(string $vectorStoreId): bool
    {
        $this->vectorStore = spin(
            fn () => VectorStore::where('vector_store_id', $vectorStoreId)
                ->where('company_id', $this->company->id)
                ->first(),
            'Buscando Vector Store...'
        );

        if (! $this->vectorStore) {
            error('❌ Vector Store não encontrada!');

            return false;
        }

        return true;
    }

    /**
     * Confirma a vinculação se estiver em modo interativo
     *
     * @return bool true se a vinculação foi confirmada ou não está em modo interativo, false caso contrário
     */
    private function confirmAttachment(): bool
    {
        if ($this->option('interactive')) {
            $this->displayAttachmentSummary();

            return confirm('Deseja vincular a Vector Store ao Assistente?', true);
        }

        return true;
    }

    /**
     * Exibe um resumo da vinculação
     */
    private function displayAttachmentSummary(): void
    {
        info("\nResumo da vinculação:");
        info("Empresa: {$this->company->name}");
        info("Assistente: {$this->assistant->name}");
        info("Vector Store: {$this->vectorStore->name}");
    }

    /**
     * Executa a vinculação entre a Vector Store e o Assistente
     *
     * @return bool true se a vinculação foi executada com sucesso, false caso contrário
     */
    private function executeAttachment(): bool
    {
        info("\n🔄 Atualizando assistente na OpenAI...");

        // Atualiza na API OpenAI
        $this->updateAssistantOnOpenAI();

        // Salva a relação no banco local
        $this->saveRelationshipToDatabase();

        info("\n✅ Vector Store vinculada com sucesso ao Assistente!");

        return true;
    }

    /**
     * Atualiza o assistente na API OpenAI
     *
     * @return object Resposta da API
     */
    private function updateAssistantOnOpenAI()
    {
        return spin(
            fn () => $this->aiService->assistant()->modify($this->assistant->assistant_id, [
                'name' => $this->assistant->name,
                'instructions' => $this->assistant->instructions,
                'tools' => [
                    ['type' => 'code_interpreter'],
                    ['type' => 'file_search'],
                ],
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [$this->vectorStore->vector_store_id],
                    ],
                ],
                'model' => 'gpt-4-turbo-preview',
            ]),
            'Atualizando...'
        );
    }

    /**
     * Salva a relação entre o Assistente e a Vector Store no banco de dados
     */
    private function saveRelationshipToDatabase(): void
    {
        $this->assistant->vectorStores()->attach($this->vectorStore->id);
    }
}
