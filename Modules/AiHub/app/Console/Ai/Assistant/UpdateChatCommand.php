<?php

namespace Modules\AiHub\Console\Ai\Assistant;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Assistant;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class UpdateChatCommand extends Command
{
    protected $signature = 'ai:assistant-update {name? : Name of the assistant to be updated}';

    protected $description = 'Updates an existing OpenAI assistant';

    /**
     * Selected assistant for update
     */
    protected Assistant $assistant;

    /**
     * New assistant data
     */
    protected array $updatedData = [];

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
        info("\n🔄 Update Assistant\n");

        // Find the assistant by name or list for selection
        if (! $this->findOrSelectAssistant()) {
            return 1;
        }

        // Configure aiService for the selected assistant's company
        $this->aiService->forCompany($this->assistant->company->slug);

        // Collect new information
        $this->collectUpdatedInformation();

        // Confirm changes
        if (! $this->confirmChanges()) {
            outro('Operation cancelled.');

            return 0;
        }

        // Execute the update
        if ($this->executeAssistantUpdate()) {
            outro('Operation completed.');

            return 0;
        }

        return 1;
    }

    /**
     * Find the assistant by name or allow interactive selection
     *
     * @return bool true if the assistant was found, false otherwise
     */
    private function findOrSelectAssistant(): bool
    {
        $name = $this->argument('name');

        // If no name was provided, display list for selection
        if (! $name) {
            return $this->selectAssistantInteractively();
        }

        // Search by the provided name
        $this->assistant = Assistant::where('name', $name)->first();

        if (! $this->assistant) {
            error("Assistant '{$name}' not found!");

            // Offer the option to select an assistant from the list
            return $this->askToSelectFromList();
        }

        $this->displayAssistantDetails();

        return true;
    }

    /**
     * Ask the user if they want to select an assistant from the list
     *
     * @return bool true if the assistant was selected, false otherwise
     */
    private function askToSelectFromList(): bool
    {
        if (confirm('Do you want to select an assistant from the list?', true)) {
            return $this->selectAssistantInteractively();
        }

        return false;
    }

    /**
     * Allow interactive selection of an assistant from the list
     *
     * @return bool true if an assistant was selected, false otherwise
     */
    private function selectAssistantInteractively(): bool
    {
        // Get all assistants
        $allAssistants = Assistant::with('company')->get();

        if ($allAssistants->isEmpty()) {
            error('❌ No assistants found in the system!');

            return false;
        }

        info("📝 Listing all available assistants:\n");

        // Prepare options for selection
        $choices = $allAssistants->mapWithKeys(function ($assistant) {
            return [$assistant->id => "{$assistant->name} (Company: {$assistant->company->name})"];
        })->toArray();

        // Allow the user to choose which assistant to update
        $selectedId = select(
            label: 'Select the assistant you want to update:',
            options: $choices
        );

        $this->assistant = $allAssistants->firstWhere('id', $selectedId);

        $this->displayAssistantDetails();

        return true;
    }

    /**
     * Display the details of the found assistant
     */
    private function displayAssistantDetails(): void
    {
        info("📝 Assistant found: {$this->assistant->name}");
        info("🏢 Company: {$this->assistant->company->name}");
    }

    /**
     * Collect new information for assistant update
     */
    private function collectUpdatedInformation(): void
    {
        // Request new information
        $this->updatedData['name'] = text(
            label: 'New assistant name (Press Enter to keep current)',
            default: $this->assistant->name,
            required: false
        );

        $this->updatedData['instructions'] = text(
            label: 'New instructions (Press Enter to keep current)',
            default: $this->assistant->instructions,
            required: false
        );
    }

    /**
     * Request confirmation of changes
     *
     * @return bool true if confirmed, false otherwise
     */
    private function confirmChanges(): bool
    {
        return confirm('Confirm changes?', true);
    }

    /**
     * Execute the assistant update in the API and database
     *
     * @return bool true if the update was successful, false otherwise
     */
    private function executeAssistantUpdate(): bool
    {
        try {
            // Update in OpenAI API
            $this->updateAssistantOnOpenAI();

            // Update in local database
            $this->updateAssistantInDatabase();

            $this->displaySuccessMessage();

            return true;
        } catch (\Exception $e) {
            error("\n❌ Error updating assistant: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Update the assistant in the OpenAI API
     *
     * @return object API Response
     */
    private function updateAssistantOnOpenAI()
    {
        return spin(
            callback: fn () => $this->aiService->assistant()->modify($this->assistant->assistant_id, [
                'name' => $this->updatedData['name'],
                'instructions' => $this->updatedData['instructions'],
            ]),
            message: 'Updating assistant...'
        );
    }

    /**
     * Update the assistant in the local database
     */
    private function updateAssistantInDatabase(): void
    {
        $this->assistant->update([
            'name' => $this->updatedData['name'],
            'instructions' => $this->updatedData['instructions'],
        ]);
    }

    /**
     * Display success message after update
     */
    private function displaySuccessMessage(): void
    {
        info("\n✅ Assistant updated successfully!");
    }
}
