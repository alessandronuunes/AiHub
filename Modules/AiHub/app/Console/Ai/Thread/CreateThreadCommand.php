<?php

namespace Modules\AiHub\Console\Ai\Thread;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\Thread;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class CreateThreadCommand extends Command
{
    /**
     * Command signature with flexible arguments and options
     */
    protected $signature = 'ai:chat-start
        {company? : Company slug}
        {--interactive : Interactive mode with questions}';

    protected $description = 'Creates a new thread for conversation with the assistant';

    /**
     * Selected company
     */
    protected Company $company;

    /**
     * Thread Service (now AiService)
     */
    protected AiService $aiService; // Property already declared as AiService

    /**
     * Constructor to inject dependencies
     */
    public function __construct(AiService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService; // Injects the AiService instance
    }

    /**
     * Main entry point of the command
     */
    public function handle()
    {
        $this->info("\n🤖 Thread Creation Assistant\n");

        try {
            // Initialize Thread service - REMOVED, now injected
            // $this->initializeThreadService(); // REMOVE this line

            // Select the company
            if (! $this->selectCompany()) {
                return 1;
            }

            // Check if the company has an assistant
            if (! $this->validateAssistant()) {
                return 1;
            }

            // Confirm thread creation
            if (! $this->confirmThreadCreation()) {
                outro('Operation cancelled.');

                return 0;
            }

            // Create the thread
            if ($this->createThread()) {
                outro('Operation completed.');

                return 0;
            }

            return 1;
        } catch (\Exception $e) {
            error("\n❌ Error creating thread: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Initialize the Thread service
     *
     * @return void
     */
    // REMOVE this method completely
    // private function initializeThreadService(): void
    // {
    //     $this->threadService = new ThreadService();
    // }

    /**
     * Selects the company to create the thread
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
     * Checks if the company has a configured assistant
     *
     * @return bool true if the company has an assistant, false otherwise
     */
    private function validateAssistant(): bool
    {
        $assistant = spin(
            fn () => $this->company->assistants()->first(),
            'Checking assistant...'
        );

        if (! $assistant) {
            error("❌ No assistant found for company {$this->company->name}");

            return false;
        }

        return true;
    }

    /**
     * Confirms thread creation if in interactive mode
     *
     * @return bool true if creation was confirmed or not in interactive mode, false otherwise
     */
    private function confirmThreadCreation(): bool
    {
        if ($this->option('interactive')) {
            return confirm('Do you want to create a new thread for this company?', true);
        }

        return true;
    }

    /**
     * Creates a new thread and saves it in the database
     *
     * @return bool true if the thread was created successfully, false otherwise
     */
    private function createThread(): bool
    {
        info("\n🔄 Creating thread...");

        // Uses the injected AiService to create the thread
        $threadId = spin(
            fn () => $this->aiService->thread()->create()->id, // Accesses the thread service through AiService
            'Please wait...'
        );

        // Retrieves the first assistant of the company
        $assistant = $this->company->assistants()->first();

        // Saves to the database
        $this->saveThreadToDatabase($threadId, $assistant->id);

        $this->displaySuccessMessage($threadId);

        return true;
    }

    /**
     * Saves the thread in the database
     *
     * @param  string  $threadId  ID of the thread created in the API
     * @param  int  $assistantId  ID of the assistant in the local database
     */
    private function saveThreadToDatabase(string $threadId, int $assistantId): void
    {
        Thread::create([
            'company_id' => $this->company->id,
            'assistant_id' => $assistantId,
            'thread_id' => $threadId,
            'status' => 'active',
            'metadata' => [
                'created_by' => 'console',
                'company_slug' => $this->company->slug,
            ],
        ]);
    }

    /**
     * Displays success message after thread creation
     *
     * @param  string  $threadId  ID of the created thread
     */
    private function displaySuccessMessage(string $threadId): void
    {
        info("\n✅ Thread created successfully!");
        info("ID: {$threadId}");
    }
}
