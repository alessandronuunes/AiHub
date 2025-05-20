<?php

namespace Modules\AiHub\Console\Ia\VectorStore;

use Illuminate\Console\Command;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\VectorStore;
use Modules\AiHub\Ai\AiService;
use function Laravel\Prompts\{confirm, error, info, outro, select, spin};

class DeleteVectorCommand extends Command
{
    protected $signature = 'ai:knowledge-remove
        {company? : Slug da empresa}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Deleta uma Vector Store da OpenAI';

    /**
     * Empresa selecionada
     */
    protected Company $company;

    /**
     * Vector Store selecionada
     */
    protected VectorStore $vectorStore;

    /**
     * Serviço de IA
     */
    protected AiService $aiService;

    /**
     * Arquivos da Vector Store
     */
    protected ?object $files = null;

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
        info("\n🗑️ Assistente de Exclusão de Vector Store\n");

        try {
            // Seleciona a empresa
            if (!$this->selectCompany()) {
                return 1;
            }

            // Configura o aiService para a empresa selecionada
            $this->aiService->forCompany($this->company->slug);

            // Seleciona a Vector Store
            if (!$this->selectVectorStore()) {
                return 1;
            }

            // Busca os arquivos da Vector Store
            if (!$this->fetchVectorStoreFiles()) {
                return 1;
            }

            // Processa a exclusão
            if ($this->processVectorStoreDeletion()) {
                outro('Operação concluída.');
                return 0;
            }

            return 1;

        } catch (\Exception $e) {
            error("\n❌ Erro ao deletar: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Seleciona a empresa para a exclusão
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
        // Lista empresas disponíveis
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
        $this->company = spin(
            fn() => Company::where('slug', $companySlug)->first(),
            'Buscando empresa...'
        );

        if (!$this->company) {
            error("❌ Empresa não encontrada: {$companySlug}");
            return false;
        }

        info("📝 Empresa selecionada: {$this->company->name}");
        return true;
    }

    /**
     * Seleciona a Vector Store para exclusão
     *
     * @return bool true se a Vector Store foi selecionada com sucesso, false caso contrário
     */
    private function selectVectorStore(): bool
    {
        // Busca as Vector Stores da empresa
        $vectorStores = $this->getCompanyVectorStores();

        if ($vectorStores->isEmpty()) {
            error("❌ Nenhuma Vector Store encontrada!");
            return false;
        }

        // Lista as Vector Stores para seleção
        $vectorChoices = $vectorStores->pluck('name', 'vector_store_id')->toArray();
        $vectorStoreId = select(
            label: 'Selecione a Vector Store para deletar:',
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
     * @param string $vectorStoreId ID da Vector Store na OpenAI
     * @return bool true se a Vector Store foi encontrada, false caso contrário
     */
    private function findVectorStoreById(string $vectorStoreId): bool
    {
        $this->vectorStore = spin(
            fn() => VectorStore::where('vector_store_id', $vectorStoreId)
                ->where('company_id', $this->company->id)
                ->first(),
            'Buscando Vector Store...'
        );

        if (!$this->vectorStore) {
            error("❌ Vector Store não encontrada!");
            return false;
        }

        return true;
    }

    /**
     * Busca os arquivos da Vector Store
     *
     * @return bool true se a operação foi bem-sucedida, false caso contrário
     */
    private function fetchVectorStoreFiles(): bool
    {
        info("\n📁 Verificando arquivos...");
        $this->files = spin(
            fn() => $this->aiService->vectorStore()->listFiles($this->vectorStore->vector_store_id),
            'Listando arquivos...'
        );

        return true;
    }

    /**
     * Processa a exclusão da Vector Store e/ou seus arquivos
     *
     * @return bool true se a operação foi bem-sucedida, false caso contrário
     */
    private function processVectorStoreDeletion(): bool
    {
        if (!empty($this->files->data)) {
            return $this->handleVectorStoreWithFiles();
        } else {
            return $this->deleteEmptyVectorStore();
        }
    }

    /**
     * Processa a exclusão de uma Vector Store com arquivos
     *
     * @return bool true se a operação foi bem-sucedida, false caso contrário
     */
    private function handleVectorStoreWithFiles(): bool
    {
        info("\n⚠️ Esta Vector Store possui " . count($this->files->data) . " arquivo(s) anexado(s).");

        // Exibe os arquivos
        $this->displayVectorStoreFiles();

        // Seleciona a ação a ser tomada
        $action = $this->selectDeletionAction();

        switch ($action) {
            case 'delete_all':
                return $this->deleteAllFilesAndVectorStore();
            case 'delete_one':
                return $this->deleteOneFile();
            default:
                info('Operação cancelada.');
                return false;
        }
    }

    /**
     * Exibe os arquivos da Vector Store
     *
     * @return void
     */
    private function displayVectorStoreFiles(): void
    {
        foreach ($this->files->data as $file) {
            info("- ID: {$file->id}");
        }
    }

    /**
     * Seleciona a ação de exclusão
     *
     * @return string Ação selecionada
     */
    private function selectDeletionAction(): string
    {
        return select(
            label: 'O que você deseja fazer?',
            options: [
                'delete_all' => 'Apagar todos os arquivos e a Vector Store',
                'delete_one' => 'Apagar apenas um arquivo específico',
                'cancel' => 'Cancelar operação'
            ]
        );
    }

    /**
     * Exclui todos os arquivos e, opcionalmente, a Vector Store
     *
     * @return bool true se a operação foi bem-sucedida, false caso contrário
     */
    private function deleteAllFilesAndVectorStore(): bool
    {
        if (!confirm(' Tem certeza que deseja apagar todos os arquivos e a Vector Store?', false)) {
            info('Operação cancelada.');
            return false;
        }

        // Deleta todos os arquivos
        $this->deleteAllFiles();

        // Verifica se deve remover também a Vector Store
        if (confirm('Deseja também remover a Vector Store?', true)) {
            $this->deleteVectorStore();
            info("\n✅ Vector Store e todos os arquivos foram deletados!");
        } else {
            info("\n✅ Todos os arquivos foram deletados. Vector Store mantida.");
        }

        return true;
    }

    /**
     * Exclui todos os arquivos da Vector Store
     *
     * @return void
     */
    private function deleteAllFiles(): void
    {
        info("\n🗑️ Removendo arquivos...");
        $fileIds = collect($this->files->data)->pluck('id')->toArray();
        spin(
            fn() => $this->aiService->vectorStore()->removeFiles($this->vectorStore->vector_store_id, $fileIds),
            "Removendo arquivos..."
        );
    }

    /**
     * Exclui apenas um arquivo específico
     *
     * @return bool true se a operação foi bem-sucedida, false caso contrário
     */
    private function deleteOneFile(): bool
    {
        $fileChoices = collect($this->files->data)->pluck('id', 'id')->toArray();
        $fileId = select(
            label: 'Selecione o arquivo para deletar:',
            options: $fileChoices
        );

        spin(
            fn() => $this->aiService->vectorStore()->removeFiles($this->vectorStore->vector_store_id, [$fileId]),
            'Removendo arquivo...'
        );
        info("\n✅ Arquivo deletado com sucesso!");

        return true;
    }

    /**
     * Exclui uma Vector Store sem arquivos
     *
     * @return bool true se a operação foi bem-sucedida, false caso contrário
     */
    private function deleteEmptyVectorStore(): bool
    {
        if (!confirm('Confirma a exclusão da Vector Store?', false)) {
            info('Operação cancelada.');
            return false;
        }

        $this->deleteVectorStore();
        info("\n✅ Vector Store deletada com sucesso!");
        return true;
    }

    /**
     * Exclui a Vector Store da API
     *
     * @return void
     */
    private function deleteVectorStore(): void
    {
        spin(
            fn() => $this->aiService->vectorStore()->delete($this->vectorStore->vector_store_id),
            'Removendo Vector Store...'
        );

        // Remove do banco de dados local também
        $this->vectorStore->delete();
    }
}
