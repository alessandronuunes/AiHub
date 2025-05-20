<?php

namespace Modules\AiHub\Console\Ai\VectorStore;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\VectorStore;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class ListVectorCommand extends Command
{
    protected $signature = 'ai:knowledge-list
        {company? : Company slug}
        {--interactive : Interactive mode with questions}';

    protected $description = 'Lists all knowledge bases (Vector Stores)';

    /**
     * Selected company
     */
    protected Company $company;

    /**
     * AI Service
     */
    protected AiService $aiService;

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
        try {
            info("\n📚 Knowledge Bases Listing\n");

            // Select the company
            if (! $this->selectCompany()) {
                return 1;
            }

            // Configure aiService for the selected company
            $this->aiService->forCompany($this->company->slug);

            // List the company's Vector Stores
            if (! $this->listCompanyVectorStores()) {
                return 0;
            }

            // Offer additional options in interactive mode
            if ($this->option('interactive')) {
                $this->offerAdditionalOptions();
            }

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Error listing knowledge bases: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Selects the company to list Vector Stores
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
        $this->company = Company::where('slug', $companySlug)->first();

        if (! $this->company) {
            error("❌ Company not found: {$companySlug}");

            return false;
        }

        return true;
    }

    /**
     * Lists the Vector Stores of the selected company
     *
     * @return bool true if Vector Stores were found, false otherwise
     */
    private function listCompanyVectorStores(): bool
    {
        $vectorStores = $this->getCompanyVectorStores();

        if ($vectorStores->isEmpty()) {
            info("ℹ️ No knowledge bases found for company {$this->company->name}");

            return false;
        }

        $this->displayVectorStoresTable($vectorStores);

        return true;
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
     * Displays the Vector Stores in table format
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $vectorStores  Collection of Vector Stores
     */
    private function displayVectorStoresTable($vectorStores): void
    {
        // Prepare data for the table
        $tableData = $this->prepareVectorStoresTableData($vectorStores);

        // Display the table
        table(
            ['ID', 'Name', 'Description', 'Created at'],
            $tableData
        );
    }

    /**
     * Prepares Vector Stores data for table display
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $vectorStores  Collection of Vector Stores
     * @return array Formatted data for the table
     */
    private function prepareVectorStoresTableData($vectorStores): array
    {
        return $vectorStores->map(function ($vectorStore) {
            return [
                'ID' => $vectorStore->vector_store_id,
                'Name' => $vectorStore->name,
                'Description' => $vectorStore->description ?: 'N/A',
                'Created at' => $vectorStore->created_at->format('d/m/Y H:i'),
            ];
        })->toArray();
    }

    /**
     * Offers additional options in interactive mode
     */
    private function offerAdditionalOptions(): void
    {
        $vectorStores = $this->getCompanyVectorStores();

        if ($vectorStores->isEmpty()) {
            return;
        }

        $action = select(
            label: 'What would you like to do?',
            options: [
                'view_files' => 'View files of a Vector Store',
                'create' => 'Create new Vector Store',
                'delete' => 'Delete a Vector Store',
                'attach' => 'Link a Vector Store to an Assistant',
                'exit' => 'Exit',
            ]
        );

        switch ($action) {
            case 'view_files':
                $this->viewVectorStoreFiles();
                break;
            case 'create':
                $this->call('ai:knowledge-add', [
                    'company' => $this->company->slug,
                    '--interactive' => true,
                ]);
                break;
            case 'delete':
                $this->call('ai:knowledge-remove', [
                    'company' => $this->company->slug,
                    '--interactive' => true,
                ]);
                break;
            case 'attach':
                $this->call('ai:knowledge-link', [
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
     * Displays the files of a selected Vector Store
     */
    private function viewVectorStoreFiles(): void
    {
        $vectorStores = $this->getCompanyVectorStores();
        $vectorStoreChoices = $vectorStores->pluck('name', 'vector_store_id')->toArray();

        $vectorStoreId = select(
            label: 'Select the Vector Store:',
            options: $vectorStoreChoices
        );

        $vectorStore = VectorStore::where('vector_store_id', $vectorStoreId)
            ->where('company_id', $this->company->id)
            ->first();

        if (! $vectorStore) {
            error('❌ Vector Store not found!');

            return;
        }

        $files = spin(
            fn () => $this->aiService->vectorStore()->listFiles($vectorStoreId),
            'Listing files...'
        );

        if (empty($files->data)) {
            info("\nThis Vector Store has no attached files.");

            return;
        }

        info("\nFiles in Vector Store {$vectorStore->name}:");
        foreach ($files->data as $index => $file) {
            $item = $index + 1;
            info(" {$item}. ID: {$file->id}");
        }
    }
}
