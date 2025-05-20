<?php

namespace Modules\AiHub\Console\Ia\Assistant;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Assistant;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\VectorStore;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CreateChatCommand extends Command
{
    protected $signature = 'ai:assistant-create {name? : Nome do assistente} {instructions? : Instruções para o assistente}';

    protected $description = 'Cria um novo assistente OpenAI';

    /**
     * Companhia associada ao assistente
     */
    protected Company $company;

    /**
     * Serviço de IA
     */
    protected AiService $aiService;

    /**
     * ID da Vector Store (se criada)
     */
    protected ?string $vectorStoreId = null;

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
        info("\n🤖 Assistente de Criação\n");

        // Coleta as informações do assistente
        $assistantInfo = $this->collectAssistantInfo();
        $name = $assistantInfo['name'];
        $instructions = $assistantInfo['instructions'];

        $this->info("\n🔍 Iniciando criação do assistente '{$name}'...");

        // Busca ou cria a empresa
        $companySlug = strtolower($name);
        if (! $this->findOrCreateCompany($companySlug)) {
            return 1;
        }

        // Configura o aiService para a empresa selecionada
        $this->aiService->forCompany($this->company->slug);

        // Verifica e processa arquivos para Vector Store
        $this->processFilesForVectorStore($companySlug, $name);

        // Cria assistente na OpenAI e salva no banco de dados
        if ($this->createAssistantOnOpenAI($name, $instructions)) {
            return 0;
        }

        return 1;
    }

    /**
     * Coleta as informações do assistente através da CLI
     *
     * @return array Informações do assistente
     */
    private function collectAssistantInfo(): array
    {
        // Verifica se o nome foi fornecido como argumento
        $nameArg = $this->argument('name');

        if (! $nameArg) {
            info("Por favor, forneça as informações do assistente:\n");
        }

        $name = text(
            label: 'Nome do assistente',
            required: true,
            default: $nameArg ?? ''
        );

        $instructions = text(
            label: 'Instruções para o assistente',
            required: true,
            default: $this->argument('instructions') ?? '',
            validate: fn (string $value) => match (true) {
                strlen($value) < 10 => 'As instruções devem ter pelo menos 10 caracteres',
                default => null
            }
        );

        return [
            'name' => $name,
            'instructions' => $instructions,
        ];
    }

    /**
     * Busca ou cria uma empresa para associar ao assistente
     *
     * @param  string  $companySlug  Slug da empresa
     * @return bool Sucesso da operação
     */
    private function findOrCreateCompany(string $companySlug): bool
    {
        $this->line("Verificando empresa com slug: {$companySlug}...");
        $this->company = Company::where('slug', $companySlug)->first();

        // Se a empresa não existir, pergunta se deseja criar
        if (! $this->company) {
            if ($this->confirm("❓ Empresa '{$companySlug}' não encontrada. Deseja criar uma nova empresa?", true)) {
                $companyName = $this->ask('Digite o nome da empresa:', ucfirst($companySlug));

                $this->line('📝 Criando nova empresa...');
                $this->company = Company::create([
                    'name' => $companyName,
                    'slug' => $companySlug,
                    'active' => true,
                ]);

                $this->info("✅ Empresa '{$companyName}' criada com sucesso!");

                return true;
            } else {
                $this->error('❌ Operação cancelada. É necessário ter uma empresa válida para criar um assistente.');

                return false;
            }
        } else {
            $this->info("✅ Empresa encontrada: {$this->company->name}");

            return true;
        }
    }

    /**
     * Busca por arquivos e cria uma Vector Store se necessário
     *
     * @param  string  $companySlug  Slug da empresa
     * @param  string  $assistantName  Nome do assistente
     */
    private function processFilesForVectorStore(string $companySlug, string $assistantName): void
    {
        // Verifica se existem arquivos para a empresa
        $storagePath = storage_path("app/companies/{$companySlug}/documents");
        $this->line("\n🔍 Verificando arquivos em: {$storagePath}");

        $filePaths = $this->findSupportedFiles($storagePath);

        if (! empty($filePaths)) {
            info('✅ Encontrados '.count($filePaths).' arquivos suportados');

            if (confirm('❓ Deseja criar uma Vector Store com estes arquivos?', true)) {
                $this->createVectorStore($companySlug, $assistantName, $filePaths);
            }
        } else {
            info('⚠️ Nenhum arquivo suportado encontrado');
        }
    }

    /**
     * Encontra arquivos com extensões suportadas no diretório especificado
     *
     * @param  string  $storagePath  Caminho para buscar arquivos
     * @return array Lista de caminhos de arquivos encontrados
     */
    private function findSupportedFiles(string $storagePath): array
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
     * Cria uma Vector Store com os arquivos fornecidos
     *
     * @param  string  $companySlug  Slug da empresa
     * @param  string  $assistantName  Nome do assistente
     * @param  array  $filePaths  Lista de caminhos de arquivos
     */
    private function createVectorStore(string $companySlug, string $assistantName, array $filePaths): void
    {
        try {
            info("\n📚 Criando Vector Store...");
            $vectorStoreName = "{$assistantName}_vector_store";

            info("↪ Nome da Vector Store: {$vectorStoreName}");
            info('↪ Processando '.count($filePaths).' arquivos...');

            // Upload dos arquivos primeiro
            $uploadedFileIds = [];
            foreach ($filePaths as $filePath) {
                try {
                    $fileResponse = spin(
                        fn () => $this->aiService->file()->upload($filePath, 'assistants'),
                        'Enviando arquivo: '.basename($filePath)
                    );
                    $uploadedFileIds[] = $fileResponse->id;
                } catch (\Exception $e) {
                    error('Erro ao fazer upload do arquivo '.basename($filePath).': '.$e->getMessage());
                }
            }

            if (empty($uploadedFileIds)) {
                error('Nenhum arquivo foi enviado com sucesso. Deseja continuar sem Vector Store?');

                return;
            }

            // Cria a Vector Store
            $vectorStoreResponse = spin(
                fn () => $this->aiService->vectorStore()->create($vectorStoreName, [
                    'metadata' => [
                        'company_slug' => $companySlug,
                        'description' => "Vector Store para o assistente {$assistantName}",
                    ],
                ]),
                'Criando Vector Store...'
            );

            // Adiciona os arquivos à Vector Store
            spin(
                fn () => $this->aiService->vectorStore()->addFiles($vectorStoreResponse->id, $uploadedFileIds),
                'Associando arquivos à Vector Store...'
            );

            // Salva a Vector Store no banco de dados
            VectorStore::create([
                'company_id' => $this->company->id,
                'vector_store_id' => $vectorStoreResponse->id,
                'name' => $vectorStoreName,
                'description' => "Vector Store para o assistente {$assistantName}",
                'metadata' => [
                    'company_slug' => $companySlug,
                    'has_files' => true,
                    'file_count' => count($uploadedFileIds),
                ],
            ]);

            $this->vectorStoreId = $vectorStoreResponse->id;

            info("✅ Vector Store criada com sucesso! (ID: {$this->vectorStoreId})");
        } catch (\Exception $e) {
            error('❌ Erro ao criar Vector Store: '.$e->getMessage());
            if (! confirm('❓ Deseja continuar criando o assistente sem a Vector Store?', true)) {
                throw $e; // Propaga a exceção para cancelar a operação
            }
        }
    }

    /**
     * Cria o assistente na API da OpenAI e salva no banco local
     *
     * @param  string  $name  Nome do assistente
     * @param  string  $instructions  Instruções para o assistente
     * @return bool Sucesso da operação
     */
    private function createAssistantOnOpenAI(string $name, string $instructions): bool
    {
        try {
            $this->line("\n🤖 Criando assistente na OpenAI...");

            $assistantParams = $this->buildAssistantParameters($name, $instructions);
            $response = spin(
                fn () => $this->aiService->assistant()->create($assistantParams),
                'Criando assistente...'
            );

            // Salva o assistente no banco de dados
            $assistant = $this->saveAssistantToDatabase($name, $instructions, $response->id);

            // Relaciona com Vector Store se existir
            if ($this->vectorStoreId) {
                $this->associateVectorStoreWithAssistant($assistant);
            }

            $this->displaySuccessMessage($name, $response->id);

            return true;
        } catch (\Exception $e) {
            $this->error("\n❌ Erro ao criar assistente: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Constrói os parâmetros necessários para criação do assistente
     *
     * @param  string  $name  Nome do assistente
     * @param  string  $instructions  Instruções para o assistente
     * @return array Parâmetros formatados
     */
    private function buildAssistantParameters(string $name, string $instructions): array
    {
        $assistantParams = [
            'instructions' => $instructions,
            'name' => $name,
            'tools' => [
                ['type' => 'code_interpreter'],
            ],
            'model' => 'gpt-4-turbo-preview',
        ];

        if ($this->vectorStoreId) {
            $this->line('↪ Configurando Vector Store...');
            $assistantParams['tools'][] = ['type' => 'file_search'];
            $assistantParams['tool_resources'] = [
                'file_search' => [
                    'vector_store_ids' => [$this->vectorStoreId],
                ],
            ];
        }

        return $assistantParams;
    }

    /**
     * Salva o assistente no banco de dados local
     *
     * @param  string  $name  Nome do assistente
     * @param  string  $instructions  Instruções do assistente
     * @param  string  $assistantId  ID do assistente na OpenAI
     * @return Assistant Instância do modelo Assistant
     */
    private function saveAssistantToDatabase(string $name, string $instructions, string $assistantId): Assistant
    {
        $this->line('↪ Salvando no banco de dados...');

        return Assistant::create([
            'company_id' => $this->company->id,
            'assistant_id' => $assistantId,
            'name' => $name,
            'instructions' => $instructions,
        ]);
    }

    /**
     * Associa a Vector Store ao assistente no banco de dados
     *
     * @param  Assistant  $assistant  Instância do modelo Assistant
     */
    private function associateVectorStoreWithAssistant(Assistant $assistant): void
    {
        $vectorStore = VectorStore::where('vector_store_id', $this->vectorStoreId)
            ->where('company_id', $this->company->id)
            ->first();

        if ($vectorStore) {
            $assistant->vectorStores()->attach($vectorStore->id);
            $this->line('↪ Vector Store vinculada ao assistente no banco de dados');
        }
    }

    /**
     * Exibe mensagem de sucesso na criação do assistente
     *
     * @param  string  $name  Nome do assistente
     * @param  string  $assistantId  ID do assistente na OpenAI
     */
    private function displaySuccessMessage(string $name, string $assistantId): void
    {
        $this->info("\n✅ Assistente '{$name}' criado com sucesso!");
        $this->line("↪ ID do Assistente: {$assistantId}");
        if ($this->vectorStoreId) {
            $this->line("↪ Vector Store associada: {$this->vectorStoreId}");
        }
    }
}
