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

class DeleteChatCommand extends Command
{
    protected $signature = 'ai:assistant-delete {name? : Name of the assistant to be deleted}';

    protected $description = 'Deletes an OpenAI assistant';

    /**
     * Selected assistant for deletion
     */
    protected Assistant $assistant;

    /**
     * Indicates if associated Vector Stores should be deleted
     */
    protected bool $deleteVectorStores = false;

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
        info("\n🗑️ Deletion Assistant\n");

        // Get all assistants
        $allAssistants = $this->getAllAssistants();

        if ($allAssistants->isEmpty()) {
            error('❌ No assistants found in the system!');

            return 1;
        }

        // Select the assistant to be deleted
        if (! $this->selectAssistantToDelete($allAssistants)) {
            return 0;
        }

        // Configure aiService for the selected assistant's company
        $this->aiService->forCompany($this->assistant->company->slug);

        // Process associated Vector Stores
        if (! $this->handleAssociatedVectorStores()) {
            return 0;
        }

        // Confirm deletion
        if (! $this->confirmDeletion()) {
            outro('Operation cancelled by the user.');

            return 0;
        }

        // Execute deletion
        if ($this->executeAssistantDeletion()) {
            outro('Operation completed.');

            return 0;
        }

        return 1;
    }

    /**
     * Gets all existing assistants from the database
     *
     * @return \Illuminate\Database\Eloquent\Collection Collection of assistants
     */
    private function getAllAssistants()
    {
        return Assistant::with('company')->get();
    }

    /**
     * Allows the user to select an assistant to delete
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $allAssistants  Collection of assistants
     * @return bool true if an assistant was selected, false otherwise
     */
    private function selectAssistantToDelete($allAssistants): bool
    {
        $name = $this->argument('name');

        // Prepare options for assistant selection
        $choices = $this->prepareAssistantsChoices($allAssistants, $name);

        // Allow the user to choose which assistant to delete
        $selectedId = select(
            label: 'Select the assistant you want to delete:',
            options: $choices
        );

        $this->assistant = $allAssistants->firstWhere('id', $selectedId);

        $this->displayAssistantDetails();

        return true;
    }

    /**
     * Prepares assistant options for display in the selector
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $allAssistants  Collection of assistants
     * @param  string|null  $name  Assistant name to filter (optional)
     * @return array Array of options for the selector
     */
    private function prepareAssistantsChoices($allAssistants, ?string $name): array
    {
        if ($name) {
            // If a name was provided, search for assistants with that name
            $matchingAssistants = $allAssistants->filter(function ($assistant) use ($name) {
                return stripos($assistant->name, $name) !== false;
            });

            if ($matchingAssistants->isEmpty()) {
                info("⚠️ No assistant found with the name '{$name}'");
                info("📝 Listing all available assistants:\n");

                return $allAssistants->mapWithKeys(function ($assistant) {
                    return [$assistant->id => "{$assistant->name} (Company: {$assistant->company->name})"];
                })->toArray();
            } else {
                return $matchingAssistants->mapWithKeys(function ($assistant) {
                    return [$assistant->id => "{$assistant->name} (Company: {$assistant->company->name})"];
                })->toArray();
            }
        } else {
            info("📝 Listing all available assistants:\n");

            return $allAssistants->mapWithKeys(function ($assistant) {
                return [$assistant->id => "{$assistant->name} (Company: {$assistant->company->name})"];
            })->toArray();
        }
    }

    /**
     * Displays the details of the selected assistant
     */
    private function displayAssistantDetails(): void
    {
        info("\n📌 Selected assistant details:");
        info("Name: {$this->assistant->name}");
        info("Company: {$this->assistant->company->name}");
        info("OpenAI ID: {$this->assistant->assistant_id}");
    }

    /**
     * Processes Vector Stores associated with the assistant
     *
     * @return bool true if the process should continue, false if it should be cancelled
     */
    private function handleAssociatedVectorStores(): bool
    {
        // Get associated Vector Stores
        $vectorStores = $this->assistant->vectorStores;

        if ($vectorStores->isEmpty()) {
            return true;
        }

        info("\n📦 Associated Vector Stores found: ".$vectorStores->count());
        foreach ($vectorStores as $vectorStore) {
            info("- {$vectorStore->name} (ID: {$vectorStore->vector_store_id})");
        }

        $action = select(
            label: 'What do you want to do with the associated Vector Stores?',
            options: [
                'delete_all' => 'Delete all Vector Stores',
                'keep_all' => 'Keep all Vector Stores',
                'cancel' => 'Cancel operation',
            ]
        );

        if ($action === 'cancel') {
            info('Operation cancelled by the user.');

            return false;
        }

        $this->deleteVectorStores = ($action === 'delete_all');

        return true;
    }

    /**
     * Requests confirmation before executing the deletion
     *
     * @return bool true if confirmed, false otherwise
     */
    private function confirmDeletion(): bool
    {
        return confirm("\n⚠️  Are you sure you want to delete this assistant?", false);
    }

    /**
     * Executes the deletion of the assistant and associated Vector Stores (if requested)
     *
     * @return bool true if the deletion was successful, false otherwise
     */
    private function executeAssistantDeletion(): bool
    {
        try {
            info("\n🔄 Deleting assistant...");

            $this->deleteAssociatedVectorStores();
            $this->deleteAssistantFromOpenAI();
            $this->deleteAssistantFromDatabase();

            $this->displaySuccessMessage();

            return true;

        } catch (\Exception $e) {
            error("\n❌ Error deleting assistant: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Deletes associated Vector Stores if requested
     */
    private function deleteAssociatedVectorStores(): void
    {
        $vectorStores = $this->assistant->vectorStores;

        if ($vectorStores->isNotEmpty() && $this->deleteVectorStores) {
            info("\n🗑️  Removing associated Vector Stores...");
            foreach ($vectorStores as $vectorStore) {
                spin(
                    fn () => $this->aiService->vectorStore()->delete($vectorStore->vector_store_id, true),
                    "Removing Vector Store {$vectorStore->name}..."
                );
            }
        }
    }

    /**
     * Deletes the assistant in the OpenAI API
     */
    private function deleteAssistantFromOpenAI(): void
    {
        spin(
            fn () => $this->aiService->assistant()->delete($this->assistant->assistant_id),
            'Removing assistant...'
        );
    }

    /**
     * Deletes the assistant from the local database
     */
    private function deleteAssistantFromDatabase(): void
    {
        // Clear relationships in the database
        $this->assistant->vectorStores()->detach();

        // Delete the assistant record from the database
        spin(
            fn () => $this->assistant->delete(),
            'Removing database records...'
        );
    }

    /**
     * Displays success message after deletion
     */
    private function displaySuccessMessage(): void
    {
        info("\n✅ Assistant deleted successfully!");
        if ($this->deleteVectorStores) {
            info('✅ Associated Vector Stores were removed.');
        }
    }
}
