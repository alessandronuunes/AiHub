<?php

namespace Modules\AiHub\Console\Ai\Thread;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Company;
use Modules\AiHub\Models\Thread;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class ListThreadCommand extends Command
{
    protected $signature = 'ai:chat-active
        {company? : Company slug}
        {--interactive : Interactive mode with questions}';

    protected $description = 'List all active conversations';

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
            info("\n💬 Conversation Listing\n");

            // Select the company
            if (! $this->selectCompany()) {
                return 1;
            }

            // List company threads
            if (! $this->listCompanyThreads()) {
                return 0;
            }

            // Offer option to view messages from a specific thread
            if ($this->option('interactive')) {
                $this->offerViewMessages();
            }

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Error listing conversations: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Select the company to list threads
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
     * Select the company interactively
     *
     * @return bool true if the company was successfully selected, false otherwise
     */
    private function selectCompanyInteractively(): bool
    {
        $companies = Company::pluck('name', 'slug')->toArray();

        if (empty($companies)) {
            error('❌ No registered companies!');

            return false;
        }

        $companySlug = select(
            label: 'Select the company:',
            options: $companies
        );

        return $this->findCompanyBySlug($companySlug);
    }

    /**
     * Find the company by slug
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
     * List threads from the selected company
     *
     * @return bool true if threads were found, false otherwise
     */
    private function listCompanyThreads(): bool
    {
        $threads = $this->getCompanyThreads();

        if ($threads->isEmpty()) {
            info("ℹ️ No conversations found for company {$this->company->name}");

            return false;
        }

        $this->displayThreadsTable($threads);

        return true;
    }

    /**
     * Fetch threads from the selected company
     *
     * @return \Illuminate\Database\Eloquent\Collection Collection of threads
     */
    private function getCompanyThreads()
    {
        return Thread::where('company_id', $this->company->id)
            ->with('assistant')
            ->get();
    }

    /**
     * Display threads in table format
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $threads  Collection of threads
     */
    private function displayThreadsTable($threads): void
    {
        // Prepare data for the table
        $tableData = $this->prepareThreadsTableData($threads);

        // Display the table
        table(
            ['ID', 'Assistant', 'Created at', 'Status'],
            $tableData
        );
    }

    /**
     * Prepare thread data for table display
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $threads  Collection of threads
     * @return array Formatted data for the table
     */
    private function prepareThreadsTableData($threads): array
    {
        return $threads->map(function ($thread) {
            return [
                'ID' => $thread->thread_id,
                'Assistant' => $thread->assistant->name,
                'Created at' => $thread->created_at->format('d/m/Y H:i'),
                'Status' => $thread->status,
            ];
        })->toArray();
    }

    /**
     * Offer option to view messages from a specific thread
     */
    private function offerViewMessages(): void
    {
        $threads = $this->getCompanyThreads();

        if ($threads->isEmpty()) {
            return;
        }

        $threadOptions = $threads->pluck('thread_id')->toArray();
        $threadOptions['cancel'] = 'Cancel';

        $selectedThread = select(
            label: 'Select a thread to view messages:',
            options: $threadOptions
        );

        if ($selectedThread !== 'cancel') {
            $this->call('ai:chat-list', [
                'thread_id' => $selectedThread,
                '--interactive' => true,
            ]);
        }
    }
}
