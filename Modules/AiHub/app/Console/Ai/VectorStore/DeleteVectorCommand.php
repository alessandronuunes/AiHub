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

class DeleteVectorCommand extends Command
{
    protected $signature = 'ai:knowledge-remove
        {company? : Company slug}
        {--interactive : Interactive mode with questions}';

    protected $description = 'Deletes a Vector Store from OpenAI';

    /**
     * Selected company
     */
    protected Company $company;

    /**
     * Selected Vector Store
     */
    protected VectorStore $vectorStore;

    /**
     * AI Service
     */
    protected AiService $aiService;

    /**
     * Vector Store files
     */
    protected ?object $files = null;

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
        info("\n🗑️ Vector Store Deletion Assistant\n");

        try {
            // Select the company
            if (! $this->selectCompany()) {
                return 1;
            }

            // Configure aiService for the selected company
            $this->aiService->forCompany($this->company->slug);

            // Select the Vector Store
            if (! $this->selectVectorStore()) {
                return 1;
            }

            // Fetch Vector Store files
            if (! $this->fetchVectorStoreFiles()) {
                return 1;
            }

            // Process deletion
            if ($this->processVectorStoreDeletion()) {
                outro('Operation completed.');

                return 0;
            }

            return 1;

        } catch (\Exception $e) {
            error("\n❌ Error deleting: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Selects the company for deletion
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
     * Selects the Vector Store for deletion
     *
     * @return bool true if the Vector Store was successfully selected, false otherwise
     */
    private function selectVectorStore(): bool
    {
        // Get the company's Vector Stores
        $vectorStores = $this->getCompanyVectorStores();

        if ($vectorStores->isEmpty()) {
            error('❌ No Vector Store found!');

            return false;
        }

        // List Vector Stores for selection
        $vectorChoices = $vectorStores->pluck('name', 'vector_store_id')->toArray();
        $vectorStoreId = select(
            label: 'Select the Vector Store to delete:',
            options: $vectorChoices
        );

        return $this->findVectorStoreById($vectorStoreId);
    }

    /**
     * Retrieves the Vector Stores of the selected company
     *
     * @return \Illuminate\Database\Eloquent\Collection Collection of Vector Stores
     */
    private function getCompanyVectorStores()
    {
        return VectorStore::where('company_id', $this->company->id)->get();
    }

    /**
     * Finds a Vector Store by ID
     *
     * @param  string  $vectorStoreId  Vector Store ID in OpenAI
     * @return bool true if the Vector Store was found, false otherwise
     */
    private function findVectorStoreById(string $vectorStoreId): bool
    {
        $this->vectorStore = spin(
            fn () => VectorStore::where('vector_store_id', $vectorStoreId)
                ->where('company_id', $this->company->id)
                ->first(),
            'Searching for Vector Store...'
        );

        if (! $this->vectorStore) {
            error('❌ Vector Store not found!');

            return false;
        }

        return true;
    }

    /**
     * Fetches the Vector Store files
     *
     * @return bool true if the operation was successful, false otherwise
     */
    private function fetchVectorStoreFiles(): bool
    {
        info("\n📁 Checking files...");
        $this->files = spin(
            fn () => $this->aiService->vectorStore()->listFiles($this->vectorStore->vector_store_id),
            'Listing files...'
        );

        return true;
    }

    /**
     * Processes the deletion of the Vector Store and/or its files
     *
     * @return bool true if the operation was successful, false otherwise
     */
    private function processVectorStoreDeletion(): bool
    {
        if (! empty($this->files->data)) {
            return $this->handleVectorStoreWithFiles();
        } else {
            return $this->deleteEmptyVectorStore();
        }
    }

    /**
     * Processes the deletion of a Vector Store with files
     *
     * @return bool true if the operation was successful, false otherwise
     */
    private function handleVectorStoreWithFiles(): bool
    {
        info("\n⚠️ This Vector Store has ".count($this->files->data).' attached file(s).');

        // Display the files
        $this->displayVectorStoreFiles();

        // Select the action to take
        $action = $this->selectDeletionAction();

        switch ($action) {
            case 'delete_all':
                return $this->deleteAllFilesAndVectorStore();
            case 'delete_one':
                return $this->deleteOneFile();
            default:
                info('Operation cancelled.');

                return false;
        }
    }

    /**
     * Displays the Vector Store files
     */
    private function displayVectorStoreFiles(): void
    {
        foreach ($this->files->data as $file) {
            info("- ID: {$file->id}");
        }
    }

    /**
     * Selects the deletion action
     *
     * @return string Selected action
     */
    private function selectDeletionAction(): string
    {
        return select(
            label: 'What do you want to do?',
            options: [
                'delete_all' => 'Delete all files and the Vector Store',
                'delete_one' => 'Delete only a specific file',
                'cancel' => 'Cancel operation',
            ]
        );
    }

    /**
     * Deletes all files and, optionally, the Vector Store
     *
     * @return bool true if the operation was successful, false otherwise
     */
    private function deleteAllFilesAndVectorStore(): bool
    {
        if (! confirm(' Are you sure you want to delete all files and the Vector Store?', false)) {
            info('Operation cancelled.');

            return false;
        }

        // Delete all files
        $this->deleteAllFiles();

        // Check if the Vector Store should also be removed
        if (confirm('Do you also want to remove the Vector Store?', true)) {
            $this->deleteVectorStore();
            info("\n✅ Vector Store and all files have been deleted!");
        } else {
            info("\n✅ All files have been deleted. Vector Store maintained.");
        }

        return true;
    }

    /**
     * Deletes all files from the Vector Store
     */
    private function deleteAllFiles(): void
    {
        info("\n🗑️ Removing files...");
        $fileIds = collect($this->files->data)->pluck('id')->toArray();
        spin(
            fn () => $this->aiService->vectorStore()->removeFiles($this->vectorStore->vector_store_id, $fileIds),
            'Removing files...'
        );
    }

    /**
     * Deletes only one specific file
     *
     * @return bool true if the operation was successful, false otherwise
     */
    private function deleteOneFile(): bool
    {
        $fileChoices = collect($this->files->data)->pluck('id', 'id')->toArray();
        $fileId = select(
            label: 'Select the file to delete:',
            options: $fileChoices
        );

        spin(
            fn () => $this->aiService->vectorStore()->removeFiles($this->vectorStore->vector_store_id, [$fileId]),
            'Removing file...'
        );
        info("\n✅ File successfully deleted!");

        return true;
    }

    /**
     * Deletes an empty Vector Store
     *
     * @return bool true if the operation was successful, false otherwise
     */
    private function deleteEmptyVectorStore(): bool
    {
        if (! confirm('Confirm the deletion of the Vector Store?', false)) {
            info('Operation cancelled.');

            return false;
        }

        $this->deleteVectorStore();
        info("\n✅ Vector Store successfully deleted!");

        return true;
    }

    /**
     * Deletes the Vector Store from the API
     */
    private function deleteVectorStore(): void
    {
        spin(
            fn () => $this->aiService->vectorStore()->delete($this->vectorStore->vector_store_id),
            'Removing Vector Store...'
        );

        // Also remove from the local database
        $this->vectorStore->delete();
    }
}
