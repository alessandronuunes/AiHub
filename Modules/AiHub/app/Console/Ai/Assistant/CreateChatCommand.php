<?php

namespace Modules\AiHub\Console\Ai\Assistant;

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
    protected $signature = 'ai:assistant-create {name? : Name of the assistant} {instructions? : Instructions for the assistant}';

    protected $description = 'Creates a new OpenAI assistant';

    /**
     * Company associated with the assistant
     */
    protected Company $company;

    /**
     * AI Service
     */
    protected AiService $aiService;

    /**
     * Vector Store ID (if created)
     */
    protected ?string $vectorStoreId = null;

    /**
     * Constructor to inject dependencies
     */
    public function __construct(AiService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    /**
     * Main entry point of the command
     */
    public function handle()
    {
        info("\n🤖 Creation Assistant\n");

        // Collect assistant information
        $assistantInfo = $this->collectAssistantInfo();
        $name = $assistantInfo['name'];
        $instructions = $assistantInfo['instructions'];

        $this->info("\n🔍 Starting creation of assistant '{$name}'...");

        // Find or create the company
        $companySlug = strtolower($name);
        if (! $this->findOrCreateCompany($companySlug)) {
            return 1;
        }

        // Configure aiService for the selected company
        $this->aiService->forCompany($this->company->slug);

        // Check and process files for Vector Store
        $this->processFilesForVectorStore($companySlug, $name);

        // Create assistant on OpenAI and save to database
        if ($this->createAssistantOnOpenAI($name, $instructions)) {
            return 0;
        }

        return 1;
    }

    /**
     * Collects assistant information through the CLI
     *
     * @return array Assistant information
     */
    private function collectAssistantInfo(): array
    {
        // Check if the name was provided as an argument
        $nameArg = $this->argument('name');

        if (! $nameArg) {
            info("Please provide the assistant information:\n");
        }

        $name = text(
            label: 'Assistant name',
            required: true,
            default: $nameArg ?? ''
        );

        $instructions = text(
            label: 'Instructions for the assistant',
            required: true,
            default: $this->argument('instructions') ?? '',
            validate: fn (string $value) => match (true) {
                strlen($value) < 10 => 'Instructions must be at least 10 characters long',
                default => null
            }
        );

        return [
            'name' => $name,
            'instructions' => $instructions,
        ];
    }

    /**
     * Finds or creates a company to associate with the assistant
     *
     * @param  string  $companySlug  Company slug
     * @return bool Operation success
     */
    private function findOrCreateCompany(string $companySlug): bool
    {
        $this->line("Checking company with slug: {$companySlug}...");
        $this->company = Company::where('slug', $companySlug)->first();

        // If the company doesn't exist, ask if you want to create it
        if (! $this->company) {
            if ($this->confirm("❓ Company '{$companySlug}' not found. Do you want to create a new company?", true)) {
                $companyName = $this->ask('Enter the company name:', ucfirst($companySlug));

                $this->line('📝 Creating new company...');
                $this->company = Company::create([
                    'name' => $companyName,
                    'slug' => $companySlug,
                    'active' => true,
                ]);

                $this->info("✅ Company '{$companyName}' created successfully!");

                return true;
            } else {
                $this->error('❌ Operation cancelled. You need a valid company to create an assistant.');

                return false;
            }
        } else {
            $this->info("✅ Company found: {$this->company->name}");

            return true;
        }
    }

    /**
     * Searches for files and creates a Vector Store if necessary
     *
     * @param  string  $companySlug  Company slug
     * @param  string  $assistantName  Assistant name
     */
    private function processFilesForVectorStore(string $companySlug, string $assistantName): void
    {
        // Check if there are files for the company
        $storagePath = storage_path("app/companies/{$companySlug}/documents");
        $this->line("\n🔍 Checking files in: {$storagePath}");

        $filePaths = $this->findSupportedFiles($storagePath);

        if (! empty($filePaths)) {
            info('✅ Found '.count($filePaths).' supported files');

            if (confirm('❓ Do you want to create a Vector Store with these files?', true)) {
                $this->createVectorStore($companySlug, $assistantName, $filePaths);
            }
        } else {
            info('⚠️ No supported files found');
        }
    }

    /**
     * Finds files with supported extensions in the specified directory
     *
     * @param  string  $storagePath  Path to search for files
     * @return array List of found file paths
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
     * Creates a Vector Store with the provided files
     *
     * @param  string  $companySlug  Company slug
     * @param  string  $assistantName  Assistant name
     * @param  array  $filePaths  List of file paths
     */
    private function createVectorStore(string $companySlug, string $assistantName, array $filePaths): void
    {
        try {
            info("\n📚 Creating Vector Store...");
            $vectorStoreName = "{$assistantName}_vector_store";

            info("↪ Vector Store name: {$vectorStoreName}");
            info('↪ Processing '.count($filePaths).' files...');

            // Upload files first
            $uploadedFileIds = [];
            foreach ($filePaths as $filePath) {
                try {
                    $fileResponse = spin(
                        fn () => $this->aiService->file()->upload($filePath, 'assistants'),
                        'Uploading file: '.basename($filePath)
                    );
                    $uploadedFileIds[] = $fileResponse->id;
                } catch (\Exception $e) {
                    error('Error uploading file '.basename($filePath).': '.$e->getMessage());
                }
            }

            if (empty($uploadedFileIds)) {
                error('No files were uploaded successfully. Do you want to continue without Vector Store?');

                return;
            }

            // Create the Vector Store
            $vectorStoreResponse = spin(
                fn () => $this->aiService->vectorStore()->create($vectorStoreName, [
                    'metadata' => [
                        'company_slug' => $companySlug,
                        'description' => "Vector Store for assistant {$assistantName}",
                    ],
                ]),
                'Creating Vector Store...'
            );

            // Add files to the Vector Store
            spin(
                fn () => $this->aiService->vectorStore()->addFiles($vectorStoreResponse->id, $uploadedFileIds),
                'Associating files with Vector Store...'
            );

            // Save the Vector Store in the database
            VectorStore::create([
                'company_id' => $this->company->id,
                'vector_store_id' => $vectorStoreResponse->id,
                'name' => $vectorStoreName,
                'description' => "Vector Store for assistant {$assistantName}",
                'metadata' => [
                    'company_slug' => $companySlug,
                    'has_files' => true,
                    'file_count' => count($uploadedFileIds),
                ],
            ]);

            $this->vectorStoreId = $vectorStoreResponse->id;

            info("✅ Vector Store created successfully! (ID: {$this->vectorStoreId})");
        } catch (\Exception $e) {
            error('❌ Error creating Vector Store: '.$e->getMessage());
            if (! confirm('❓ Do you want to continue creating the assistant without the Vector Store?', true)) {
                throw $e; // Propagate the exception to cancel the operation
            }
        }
    }

    /**
     * Creates the assistant in the OpenAI API and saves it in the local database
     *
     * @param  string  $name  Assistant name
     * @param  string  $instructions  Instructions for the assistant
     * @return bool Operation success
     */
    private function createAssistantOnOpenAI(string $name, string $instructions): bool
    {
        try {
            $this->line("\n🤖 Creating assistant in OpenAI...");

            $assistantParams = $this->buildAssistantParameters($name, $instructions);
            $response = spin(
                fn () => $this->aiService->assistant()->create($assistantParams),
                'Creating assistant...'
            );

            // Save the assistant in the database
            $assistant = $this->saveAssistantToDatabase($name, $instructions, $response->id);

            // Associate with Vector Store if it exists
            if ($this->vectorStoreId) {
                $this->associateVectorStoreWithAssistant($assistant);
            }

            $this->displaySuccessMessage($name, $response->id);

            return true;
        } catch (\Exception $e) {
            $this->error("\n❌ Error creating assistant: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Builds the necessary parameters for assistant creation
     *
     * @param  string  $name  Assistant name
     * @param  string  $instructions  Instructions for the assistant
     * @return array Formatted parameters
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
            $this->line('↪ Configuring Vector Store...');
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
     * Saves the assistant in the local database
     *
     * @param  string  $name  Assistant name
     * @param  string  $instructions  Assistant instructions
     * @param  string  $assistantId  Assistant ID in OpenAI
     * @return Assistant Instance of the Assistant model
     */
    private function saveAssistantToDatabase(string $name, string $instructions, string $assistantId): Assistant
    {
        $this->line('↪ Saving to database...');

        return Assistant::create([
            'company_id' => $this->company->id,
            'assistant_id' => $assistantId,
            'name' => $name,
            'instructions' => $instructions,
        ]);
    }

    /**
     * Associates the Vector Store with the assistant in the database
     *
     * @param  Assistant  $assistant  Instance of the Assistant model
     */
    private function associateVectorStoreWithAssistant(Assistant $assistant): void
    {
        $vectorStore = VectorStore::where('vector_store_id', $this->vectorStoreId)
            ->where('company_id', $this->company->id)
            ->first();

        if ($vectorStore) {
            $assistant->vectorStores()->attach($vectorStore->id);
            $this->line('↪ Vector Store linked to the assistant in the database');
        }
    }

    /**
     * Displays success message for assistant creation
     *
     * @param  string  $name  Assistant name
     * @param  string  $assistantId  Assistant ID in OpenAI
     */
    private function displaySuccessMessage(string $name, string $assistantId): void
    {
        $this->info("\n✅ Assistant '{$name}' created successfully!");
        $this->line("↪ Assistant ID: {$assistantId}");
        if ($this->vectorStoreId) {
            $this->line("↪ Associated Vector Store: {$this->vectorStoreId}");
        }
    }
}
