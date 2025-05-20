<?php

namespace Modules\AiHub\Console\Ai\Assistant;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Assistant;
use Modules\AiHub\Models\Company;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class ListAssistantCommand extends Command
{
    protected $signature = 'ai:assistant-list
        {company? : Company slug}
        {--interactive : Interactive mode with questions}';

    protected $description = 'Lists all available assistants';

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
            info("\n📋 Assistant Listing\n");

            // Select the company
            if (! $this->selectCompany()) {
                return 1;
            }

            // Configure aiService for the selected company
            $this->aiService->forCompany($this->company->slug);

            // List the company's assistants
            if (! $this->listCompanyAssistants()) {
                return 0;
            }

            // Offer additional options in interactive mode
            if ($this->option('interactive')) {
                $this->offerAdditionalOptions();
            }

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Error listing assistants: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Selects the company to be used for listing assistants
     *
     * @return bool true if a company was selected, false otherwise
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
     * @return bool true if a company was selected, false otherwise
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
     * Finds a company by slug
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
     * Lists the assistants of the selected company
     *
     * @return bool true if assistants were found, false otherwise
     */
    private function listCompanyAssistants(): bool
    {
        $assistants = $this->getCompanyAssistants();

        if ($assistants->isEmpty()) {
            info("ℹ️ No assistants found for company {$this->company->name}");

            return false;
        }

        $this->displayAssistantsTable($assistants);

        return true;
    }

    /**
     * Gets the assistants of the selected company
     *
     * @return \Illuminate\Database\Eloquent\Collection Collection of assistants
     */
    private function getCompanyAssistants()
    {
        return Assistant::where('company_id', $this->company->id)->get();
    }

    /**
     * Displays the assistants in table format
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $assistants  Collection of assistants
     */
    private function displayAssistantsTable($assistants): void
    {
        // Prepare data for the table
        $tableData = $this->prepareAssistantsTableData($assistants);

        // Display the table
        table(
            ['ID', 'Name', 'Vector Stores'],
            $tableData
        );
    }

    /**
     * Prepares assistant data for table display
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $assistants  Collection of assistants
     * @return array Formatted data for the table
     */
    private function prepareAssistantsTableData($assistants): array
    {
        return $assistants->map(function ($assistant) {
            $vectorStores = $assistant->vectorStores->pluck('name')->join(', ');

            return [
                'ID' => $assistant->assistant_id,
                'Name' => $assistant->name,
                'Vector Stores' => $vectorStores ?: 'None',
            ];
        })->toArray();
    }

    /**
     * Offers additional options in interactive mode
     */
    private function offerAdditionalOptions(): void
    {
        $assistants = $this->getCompanyAssistants();

        if ($assistants->isEmpty()) {
            return;
        }

        $action = select(
            label: 'What would you like to do?',
            options: [
                'view_details' => 'View details of an assistant',
                'create_thread' => 'Create a new conversation with an assistant',
                'create_assistant' => 'Create a new assistant',
                'create_vector' => 'Create a new Vector Store',
                'exit' => 'Exit',
            ]
        );

        switch ($action) {
            case 'view_details':
                $this->viewAssistantDetails();
                break;
            case 'create_thread':
                $this->call('ai:chat-start', [
                    'company' => $this->company->slug,
                    '--interactive' => true,
                ]);
                break;
            case 'create_assistant':
                $this->call('ai:assistant-create');
                break;
            case 'create_vector':
                $this->call('ai:knowledge-add', [
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
     * Displays details of a selected assistant
     */
    private function viewAssistantDetails(): void
    {
        $assistants = $this->getCompanyAssistants();
        $assistantChoices = $assistants->pluck('name', 'assistant_id')->toArray();

        $assistantId = select(
            label: 'Select the assistant:',
            options: $assistantChoices
        );

        $assistant = Assistant::where('assistant_id', $assistantId)
            ->where('company_id', $this->company->id)
            ->first();

        if (! $assistant) {
            error('❌ Assistant not found!');

            return;
        }

        // Get updated details from the API
        $assistantDetails = spin(
            fn () => $this->aiService->assistant()->retrieve($assistantId),
            'Fetching assistant details...'
        );

        info("\nAssistant Details:");
        info("Name: {$assistant->name}");
        info("ID: {$assistant->assistant_id}");
        info("Instructions: {$assistant->instructions}");
        info("Model: {$assistantDetails->model}");

        $toolsList = collect($assistantDetails->tools)->pluck('type')->join(', ');
        info("Tools: {$toolsList}");

        $vectorStores = $assistant->vectorStores->pluck('name')->join(', ');
        info('Vector Stores: '.($vectorStores ?: 'None'));
    }
}
