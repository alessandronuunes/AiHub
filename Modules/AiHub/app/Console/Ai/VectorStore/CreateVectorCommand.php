<?php

namespace Modules\AiHub\Console\Ai\VectorStore;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\VectorStore;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CreateVectorCommand extends Command
{
    /**
     * Command signature with flexible arguments and options
     */
    protected $signature = 'ai:knowledge-add
        {company? : Company slug}
        {--name= : Vector Store name}
        {--description= : Vector Store description}
        {--interactive : Interactive mode with questions}';

    /**
     * Command description
     */
    protected $description = 'Creates a new Vector Store for storing documents';

    /**
     * Selected company
     */
    protected Company $company;

    /**
     * AI Service
     */
    protected AiService $aiService;

    /**
     * Vector Store name
     */
    protected string $storeName;

    /**
     * Vector Store description
     */
    protected string $storeDescription;

    /**
     * Paths of files to process
     */
    protected array $filePaths = [];

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
        $this->info("\n📚 Vector Store Creation Assistant\n");

        try {
            // Select the company
            if (! $this->selectCompany()) {
                return 1;
            }

            // Configure aiService for the selected company
            $this->aiService->forCompany($this->company->slug);

            // Collect Vector Store information
            if (! $this->collectVectorStoreInfo()) {
                return 1;
            }

            // Confirm Vector Store creation
            if (! $this->confirmVectorStoreCreation()) {
                outro('Operation cancelled.');

                return 0;
            }

            // Find available files
            $this->findAvailableFiles();

            // Create the Vector Store
            if ($this->createVectorStore()) {
                outro('Operation completed.');

                return 0;
            }

            return 1;
        } catch (\Exception $e) {
            error("\n❌ Error creating Vector Store: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Selects the company for creating the Vector Store
     *
     * @return bool true if the company was successfully selected, false otherwise
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
     * Selects the company interactively
     *
     * @return bool true if the company was successfully selected, false otherwise
     */
    private function selectCompanyInteractively(): bool
    {
        // List available companies
        $companies = Company::pluck('name', 'slug')->toArray();

        if (empty($companies)) {
            error('❌ No companies registered!');

            return false;
        }

        $companySlug = select(
            label: 'Select the company:',
            options: $companies
        );

        return $this->findCompanyBySlug($companySlug);
    }

    /**
     * Finds the company by slug
     *
     * @param  string  $companySlug  Company slug
     * @return bool true if the company was found, false otherwise
     */
    private function findCompanyBySlug(string $companySlug): bool
    {
        $this->company = spin(
            fn () => Company::where('slug', $companySlug)->first(),
            'Searching for company...'
        );

        if (! $this->company) {
            error("❌ Company not found: {$companySlug}");

            return false;
        }

        info("📝 Company selected: {$this->company->name}");

        return true;
    }

    /**
     * Collects information for Vector Store creation
     *
     * @return bool true if the information was successfully collected, false otherwise
     */
    private function collectVectorStoreInfo(): bool
    {
        // Collect the name
        if (! $this->collectVectorStoreName()) {
            return false;
        }

        // Collect the description
        $this->collectVectorStoreDescription();

        return true;
    }

    /**
     * Collects the Vector Store name
     *
     * @return bool true if the name was successfully collected, false otherwise
     */
    private function collectVectorStoreName(): bool
    {
        $name = $this->option('name');

        // Name validation function
        $validateName = function (string $value) {
            if (strlen($value) < 3) {
                return 'The name must have at least 3 characters';
            }

            if (VectorStore::where('company_id', $this->company->id)
                ->where('name', $value)
                ->exists()) {
                return 'A Vector Store with this name already exists';
            }

            return null;
        };

        // If the name was provided via option, validate it first
        if ($name) {
            $validationResult = $validateName($name);
            if ($validationResult !== null) {
                error("❌ {$validationResult}");

                return false;
            }
            $this->storeName = $name;
        } else {
            // If not provided via option, request interactively
            $this->storeName = text(
                label: 'Enter the Vector Store name:',
                required: true,
                validate: $validateName
            );
        }

        return true;
    }

    /**
     * Collects the Vector Store description
     */
    private function collectVectorStoreDescription(): void
    {
        $description = $this->option('description');

        if (! $description) {
            $description = text(
                label: 'Enter a description for the Vector Store:',
                required: true,
                validate: fn (string $value) => match (true) {
                    strlen($value) < 10 => 'The description must have at least 10 characters',
                    default => null
                }
            );
        }

        $this->storeDescription = $description;
    }

    /**
     * Confirms the Vector Store creation if in interactive mode
     *
     * @return bool true if the creation was confirmed or not in interactive mode, false otherwise
     */
    private function confirmVectorStoreCreation(): bool
    {
        if ($this->option('interactive')) {
            $this->displayVectorStoreSummary();

            return confirm('Do you want to create the Vector Store with this information?', true);
        }

        return true;
    }

    /**
     * Displays a summary of the Vector Store information
     */
    private function displayVectorStoreSummary(): void
    {
        info("\nCreation summary:");
        info("Company: {$this->company->name}");
        info("Name: {$this->storeName}");
        info("Description: {$this->storeDescription}");
    }

    /**
     * Finds available files for processing
     */
    private function findAvailableFiles(): void
    {
        $storagePath = storage_path("app/companies/{$this->company->slug}/documents");
        info("\n🔍 Checking files in: {$storagePath}");

        $this->filePaths = $this->getSupportedFiles($storagePath);

        if (! empty($this->filePaths)) {
            $this->handleFoundFiles();
        } else {
            info("\n⚠️ No supported files found");
        }
    }

    /**
     * Retrieves files with supported extensions in the directory
     *
     * @param  string  $storagePath  Path to search for files
     * @return array List of found file paths
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
     * Processes the found files
     */
    private function handleFoundFiles(): void
    {
        info('✅ Found '.count($this->filePaths).' supported files');

        if (! confirm('❓ Do you want to include these files in the Vector Store?', true)) {
            $this->filePaths = [];
        }
    }

    /**
     * Creates the Vector Store
     *
     * @return bool true if the Vector Store was created successfully, false otherwise
     */
    private function createVectorStore(): bool
    {
        if (! empty($this->filePaths)) {
            return $this->createVectorStoreWithFiles();
        } else {
            return $this->createEmptyVectorStore();
        }
    }

    /**
     * Creates a Vector Store with files
     *
     * @return bool true if the Vector Store was created successfully, false otherwise
     */
    private function createVectorStoreWithFiles(): bool
    {
        try {
            info("\n📚 Creating Vector Store with files...");
            info("↪ Vector Store name: {$this->storeName}");
            info('↪ Processing '.count($this->filePaths).' files...');

            // Process files and create Vector Store
            $uploadedFileIds = [];

            // Upload files
            foreach ($this->filePaths as $filePath) {
                $fileResponse = spin(
                    fn () => $this->aiService->file()->upload($filePath, 'assistants'),
                    'Uploading file: '.basename($filePath)
                );
                $uploadedFileIds[] = $fileResponse->id;
            }

            // Create the Vector Store
            $vectorStore = spin(
                fn () => $this->aiService->vectorStore()->create($this->storeName, [
                    'metadata' => [
                        'company_slug' => $this->company->slug,
                        'description' => $this->storeDescription,
                    ],
                ]),
                'Creating Vector Store...'
            );

            // Add files to the Vector Store
            if (! empty($uploadedFileIds)) {
                spin(
                    fn () => $this->aiService->vectorStore()->addFiles($vectorStore->id, $uploadedFileIds),
                    'Associating files to Vector Store...'
                );
            }

            // Save to database
            $this->saveVectorStoreToDatabase($vectorStore, count($uploadedFileIds));

            $this->displaySuccessMessage($vectorStore);

            return true;
        } catch (\Exception $e) {
            error('❌ Error processing files: '.$e->getMessage());
            if (! confirm('❓ Do you want to continue creating the Vector Store without files?', true)) {
                return false;
            }

            // If failed with files, try to create without
            return $this->createEmptyVectorStore();
        }
    }

    /**
     * Creates an empty Vector Store (without files)
     *
     * @return bool true if the Vector Store was created successfully, false otherwise
     */
    private function createEmptyVectorStore(): bool
    {
        info("\n📚 Creating Vector Store without files...");
        $vectorStore = spin(
            fn () => $this->aiService->vectorStore()->create($this->storeName, [
                'metadata' => [
                    'company_slug' => $this->company->slug,
                    'description' => $this->storeDescription,
                ],
            ]),
            'Creating Vector Store...'
        );

        // Save to database
        $this->saveVectorStoreToDatabase($vectorStore, 0);

        $this->displaySuccessMessage($vectorStore);

        return true;
    }

    /**
     * Saves the Vector Store to the database
     *
     * @param  object  $vectorStore  API Response
     * @param  int  $fileCount  Number of processed files
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
                'file_count' => $fileCount,
            ],
        ]);
    }

    /**
     * Displays success message after Vector Store creation
     *
     * @param  object  $vectorStore  API Response with created Vector Store data
     */
    private function displaySuccessMessage(object $vectorStore): void
    {
        info("\n✅ Vector Store created successfully!");
        info("ID: {$vectorStore->id}");
        info("Name: {$this->storeName}");

        // If there are processed files, show the count
        if (! empty($this->filePaths)) {
            info('Processed files: '.count($this->filePaths));
        }
    }
}
