<?php

namespace Modules\AiHub\Console\Ai\VectorStore;

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
        {company? : Company slug}
        {--interactive : Interactive mode with questions}';

    protected $description = 'Associates a Vector Store with an Assistant';

    /**
     * Selected company
     */
    protected Company $company;

    /**
     * Selected assistant
     */
    protected Assistant $assistant;

    /**
     * Selected Vector Store
     */
    protected VectorStore $vectorStore;

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
        info("\n🔗 Vector Store Linking Assistant\n");

        try {
            // Select the company
            if (! $this->selectCompany()) {
                return 1;
            }

            // Configure aiService for the selected company
            $this->aiService->forCompany($this->company->slug);

            // Select the assistant
            if (! $this->selectAssistant()) {
                return 1;
            }

            // Select the Vector Store
            if (! $this->selectVectorStore()) {
                return 1;
            }

            // Confirm the linking
            if (! $this->confirmAttachment()) {
                outro('Operation cancelled.');

                return 0;
            }

            // Execute the linking
            if ($this->executeAttachment()) {
                outro('Operation completed.');

                return 0;
            }

            return 1;

        } catch (\Exception $e) {
            error("\n❌ Error linking Vector Store: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Selects the company for linking
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
     * Selects the assistant for linking
     *
     * @return bool true if the assistant was successfully selected, false otherwise
     */
    private function selectAssistant(): bool
    {
        // Get the company's assistants
        $assistants = $this->getCompanyAssistants();

        if ($assistants->isEmpty()) {
            error('❌ No Assistants found for the company!');

            return false;
        }

        // List assistants for selection
        $assistantChoices = $assistants->pluck('name', 'assistant_id')->toArray();
        $assistantId = select(
            label: 'Select the Assistant:',
            options: $assistantChoices
        );

        return $this->findAssistantById($assistantId);
    }

    /**
     * Retrieves the assistants of the selected company
     *
     * @return \Illuminate\Database\Eloquent\Collection Collection of assistants
     */
    private function getCompanyAssistants()
    {
        return Assistant::where('company_id', $this->company->id)->get();
    }

    /**
     * Finds an assistant by ID
     *
     * @param  string  $assistantId  Assistant ID in OpenAI
     * @return bool true if the assistant was found, false otherwise
     */
    private function findAssistantById(string $assistantId): bool
    {
        $this->assistant = spin(
            fn () => Assistant::where('assistant_id', $assistantId)
                ->where('company_id', $this->company->id)
                ->first(),
            'Searching for assistant...'
        );

        if (! $this->assistant) {
            error('❌ Assistant not found!');

            return false;
        }

        return true;
    }

    /**
     * Selects the Vector Store for linking
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
            label: 'Select the Vector Store:',
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
     * Confirms the linking if in interactive mode
     *
     * @return bool true if the linking was confirmed or not in interactive mode, false otherwise
     */
    private function confirmAttachment(): bool
    {
        if ($this->option('interactive')) {
            $this->displayAttachmentSummary();

            return confirm('Do you want to link the Vector Store to the Assistant?', true);
        }

        return true;
    }

    /**
     * Displays a summary of the linking
     */
    private function displayAttachmentSummary(): void
    {
        info("\nLinking summary:");
        info("Company: {$this->company->name}");
        info("Assistant: {$this->assistant->name}");
        info("Vector Store: {$this->vectorStore->name}");
    }

    /**
     * Executes the linking between the Vector Store and the Assistant
     *
     * @return bool true if the linking was executed successfully, false otherwise
     */
    private function executeAttachment(): bool
    {
        info("\n🔄 Updating assistant in OpenAI...");

        // Update in OpenAI API
        $this->updateAssistantOnOpenAI();

        // Save the relationship in the local database
        $this->saveRelationshipToDatabase();

        info("\n✅ Vector Store successfully linked to the Assistant!");

        return true;
    }

    /**
     * Updates the assistant in the OpenAI API
     *
     * @return object API Response
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
            'Updating...'
        );
    }

    /**
     * Saves the relationship between the Assistant and the Vector Store in the database
     */
    private function saveRelationshipToDatabase(): void
    {
        $this->assistant->vectorStores()->attach($this->vectorStore->id);
    }
}
