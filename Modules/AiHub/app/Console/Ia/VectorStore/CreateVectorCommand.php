<?php

namespace Modules\AiHub\Console\Ia\VectorStore;

use Illuminate\Console\Command;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\VectorStore;
use Modules\AiHub\Ai\AiService;
use function Laravel\Prompts\{confirm, error, info, outro, select, spin, text};

class CreateVectorCommand extends Command
{
    /**
     * Assinatura do comando com argumentos e opções flexíveis
     */
    protected $signature = 'ai:knowledge-add
        {company? : Slug da empresa}
        {--name= : Nome do vectorStore}
        {--description= : Descrição do vectorStore}
        {--interactive : Modo interativo com perguntas}';

    /**
     * Descrição do comando
     */
    protected $description = 'Cria um novo vectorStore para armazenar documentos';

    /**
     * Empresa selecionada
     */
    protected Company $company;

    /**
     * Serviço de IA
     */
    protected AiService $aiService;

    /**
     * Nome da Vector Store
     */
    protected string $storeName;

    /**
     * Descrição da Vector Store
     */
    protected string $storeDescription;

    /**
     * Caminhos dos arquivos a processar
     */
    protected array $filePaths = [];

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
        $this->info("\n📚 Assistente de Criação de VectorStore\n");

        try {
            // Seleciona a empresa
            if (!$this->selectCompany()) {
                return 1;
            }

            // Configura o aiService para a empresa selecionada
            $this->aiService->forCompany($this->company->slug);

            // Coleta as informações da Vector Store
            if (!$this->collectVectorStoreInfo()) {
                return 1;
            }

            // Confirma a criação da Vector Store
            if (!$this->confirmVectorStoreCreation()) {
                outro('Operação cancelada.');
                return 0;
            }

            // Busca arquivos disponíveis
            $this->findAvailableFiles();

            // Cria a Vector Store
            if ($this->createVectorStore()) {
                outro('Operação concluída.');
                return 0;
            }

            return 1;
        } catch (\Exception $e) {
            error("\n❌ Erro ao criar Vector Store: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Seleciona a empresa para criar a Vector Store
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
     * Coleta as informações para criação da Vector Store
     *
     * @return bool true se as informações foram coletadas com sucesso, false caso contrário
     */
    private function collectVectorStoreInfo(): bool
    {
        // Coleta o nome
        if (!$this->collectVectorStoreName()) {
            return false;
        }

        // Coleta a descrição
        $this->collectVectorStoreDescription();

        return true;
    }

    /**
     * Coleta o nome da Vector Store
     *
     * @return bool true se o nome foi coletado com sucesso, false caso contrário
     */
    private function collectVectorStoreName(): bool
    {
        $name = $this->option('name');

        // Função de validação do nome
        $validateName = function(string $value) {
            if (strlen($value) < 3) {
                return 'O nome deve ter pelo menos 3 caracteres';
            }

            if (VectorStore::where('company_id', $this->company->id)
                ->where('name', $value)
                ->exists()) {
                return 'Já existe um vectorStore com este nome';
            }

            return null;
        };

        // Se o nome foi fornecido via opção, valida ele primeiro
        if ($name) {
            $validationResult = $validateName($name);
            if ($validationResult !== null) {
                error("❌ {$validationResult}");
                return false;
            }
            $this->storeName = $name;
        } else {
            // Se não foi fornecido via opção, solicita interativamente
            $this->storeName = text(
                label: 'Digite o nome do vectorStore:',
                required: true,
                validate: $validateName
            );
        }

        return true;
    }

    /**
     * Coleta a descrição da Vector Store
     *
     * @return void
     */
    private function collectVectorStoreDescription(): void
    {
        $description = $this->option('description');

        if (!$description) {
            $description = text(
                label: 'Digite uma descrição para o vectorStore:',
                required: true,
                validate: fn (string $value) => match (true) {
                    strlen($value) < 10 => 'A descrição deve ter pelo menos 10 caracteres',
                    default => null
                }
            );
        }

        $this->storeDescription = $description;
    }

    /**
     * Confirma a criação da Vector Store se estiver em modo interativo
     *
     * @return bool true se a criação foi confirmada ou não está em modo interativo, false caso contrário
     */
    private function confirmVectorStoreCreation(): bool
    {
        if ($this->option('interactive')) {
            $this->displayVectorStoreSummary();
            return confirm('Deseja criar o vectorStore com estas informações?', true);
        }

        return true;
    }

    /**
     * Exibe um resumo das informações da Vector Store
     *
     * @return void
     */
    private function displayVectorStoreSummary(): void
    {
        info("\nResumo da criação:");
        info("Empresa: {$this->company->name}");
        info("Nome: {$this->storeName}");
        info("Descrição: {$this->storeDescription}");
    }

    /**
     * Busca arquivos disponíveis para processamento
     *
     * @return void
     */
    private function findAvailableFiles(): void
    {
        $storagePath = storage_path("app/companies/{$this->company->slug}/documents");
        info("\n🔍 Verificando arquivos em: {$storagePath}");

        $this->filePaths = $this->getSupportedFiles($storagePath);

        if (!empty($this->filePaths)) {
            $this->handleFoundFiles();
        } else {
            info("\n⚠️ Nenhum arquivo suportado encontrado");
        }
    }

    /**
     * Recupera os arquivos com extensões suportadas no diretório
     *
     * @param string $storagePath Caminho para buscar arquivos
     * @return array Lista de caminhos de arquivos encontrados
     */
    private function getSupportedFiles(string $storagePath): array
    {
        $supportedExtensions = ['pdf', 'docx', 'xlsx', 'pptx', 'txt', 'md', 'json'];
        $filePaths = [];

        if (is_dir($storagePath)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storagePath)) as $file) {
                if ($file->isFile()) {
                    $extension = strtolower($file->getExtension());
                    if (in_array($extension, $supportedExtensions)) {
                        $filePaths[] = $file->getRealPath();
                    }
                }
            }
        }

        return $filePaths;
    }

    /**
     * Processa os arquivos encontrados
     *
     * @return void
     */
    private function handleFoundFiles(): void
    {
        info("✅ Encontrados " . count($this->filePaths) . " arquivos suportados");

        if (!confirm("❓ Deseja incluir estes arquivos na Vector Store?", true)) {
            $this->filePaths = [];
        }
    }

    /**
     * Cria a Vector Store
     *
     * @return bool true se a Vector Store foi criada com sucesso, false caso contrário
     */
    private function createVectorStore(): bool
    {
        if (!empty($this->filePaths)) {
            return $this->createVectorStoreWithFiles();
        } else {
            return $this->createEmptyVectorStore();
        }
    }

    /**
     * Cria uma Vector Store com arquivos
     *
     * @return bool true se a Vector Store foi criada com sucesso, false caso contrário
     */
    private function createVectorStoreWithFiles(): bool
    {
        try {
            info("\n📚 Criando Vector Store com arquivos...");
            info("↪ Nome da Vector Store: {$this->storeName}");
            info("↪ Processando " . count($this->filePaths) . " arquivos...");

            // Processa arquivos e cria Vector Store
            $uploadedFileIds = [];

            // Upload dos arquivos
            foreach ($this->filePaths as $filePath) {
                $fileResponse = spin(
                    fn() => $this->aiService->file()->upload($filePath, 'assistants'),
                    "Enviando arquivo: " . basename($filePath)
                );
                $uploadedFileIds[] = $fileResponse->id;
            }

            // Cria a Vector Store
            $vectorStore = spin(
                fn() => $this->aiService->vectorStore()->create($this->storeName, [
                    'metadata' => [
                        'company_slug' => $this->company->slug,
                        'description' => $this->storeDescription
                    ]
                ]),
                'Criando Vector Store...'
            );

            // Adiciona os arquivos à Vector Store
            if (!empty($uploadedFileIds)) {
                spin(
                    fn() => $this->aiService->vectorStore()->addFiles($vectorStore->id, $uploadedFileIds),
                    'Associando arquivos à Vector Store...'
                );
            }

            // Salva no banco de dados
            $this->saveVectorStoreToDatabase($vectorStore, count($uploadedFileIds));

            $this->displaySuccessMessage($vectorStore);
            return true;
        } catch (\Exception $e) {
            error("❌ Erro ao processar arquivos: " . $e->getMessage());
            if (!confirm("❓ Deseja continuar criando a Vector Store sem arquivos?", true)) {
                return false;
            }

            // Se falhou com arquivos, tenta criar sem
            return $this->createEmptyVectorStore();
        }
    }

    /**
     * Cria uma Vector Store vazia (sem arquivos)
     *
     * @return bool true se a Vector Store foi criada com sucesso, false caso contrário
     */
    private function createEmptyVectorStore(): bool
    {
        info("\n📚 Criando Vector Store sem arquivos...");
        $vectorStore = spin(
            fn() => $this->aiService->vectorStore()->create($this->storeName, [
                'metadata' => [
                    'company_slug' => $this->company->slug,
                    'description' => $this->storeDescription
                ]
            ]),
            'Criando Vector Store...'
        );

        // Salva no banco de dados
        $this->saveVectorStoreToDatabase($vectorStore, 0);

        $this->displaySuccessMessage($vectorStore);
        return true;
    }

    /**
     * Salva a Vector Store no banco de dados
     *
     * @param object $vectorStore Resposta da API
     * @param int $fileCount Número de arquivos processados
     * @return void
     */
    private function saveVectorStoreToDatabase($vectorStore, $fileCount): void
    {
        VectorStore::create([
            'company_id' => $this->company->id,
            'vector_store_id' => $vectorStore->id,
            'name' => $this->storeName,
            'description' => $this->storeDescription,
            'metadata' => [
                'company_slug' => $this->company->slug,
                'has_files' => $fileCount > 0,
                'file_count' => $fileCount
            ]
        ]);
    }

    /**
     * Exibe mensagem de sucesso após a criação da Vector Store
     *
     * @param object $vectorStore Resposta da API com os dados da Vector Store criada
     * @return void
     */
    private function displaySuccessMessage(object $vectorStore): void
    {
        info("\n✅ Vector Store criada com sucesso!");
        info("ID: {$vectorStore->id}");
        info("Nome: {$this->storeName}");

        // Se tiver arquivos processados, mostra a contagem
        if (!empty($this->filePaths)) {
            info("Arquivos processados: " . count($this->filePaths));
        }
    }
}
