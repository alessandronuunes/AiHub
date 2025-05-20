<?php

namespace Modules\AiHub\Console\Ai\Thread;

use Illuminate\Console\Command;
use Modules\AiHub\Ai\AiService;
use Modules\AiHub\Models\Message;
use Modules\AiHub\Models\Thread;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class ListMessagesCommand extends Command
{
    /**
     * Command signature with flexible arguments and options
     */
    protected $signature = 'ai:chat-list
        {thread_id? : Thread ID}
        {--limit=10 : Number of messages to display}
        {--interactive : Interactive mode with questions}';

    protected $description = 'List messages from a specific thread';

    /**
     * Selected thread
     */
    protected Thread $thread;

    /**
     * AI Service
     */
    protected AiService $aiService;

    /**
     * Limit of messages to be displayed
     */
    protected int $limit;

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
        info("\n📋 Message History\n");

        try {
            // Set the message limit
            $this->setMessageLimit();

            // Select the thread
            if (! $this->selectThread()) {
                return 1;
            }

            // Fetch and display messages
            if (! $this->fetchAndDisplayMessages()) {
                return 0;
            }

            // Offer additional options if in interactive mode
            $this->offerAdditionalOptions();

            outro('Viewing completed.');

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Error listing messages: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Set the limit of messages to be displayed
     */
    private function setMessageLimit(): void
    {
        $this->limit = (int) $this->option('limit');
    }

    /**
     * Select the thread to list messages
     *
     * @return bool true if a thread was successfully selected, false otherwise
     */
    private function selectThread(): bool
    {
        $threadId = $this->argument('thread_id');

        if (! $threadId || $this->option('interactive')) {
            return $this->selectThreadInteractively();
        }

        return $this->findThreadById($threadId);
    }

    /**
     * Select the thread interactively
     *
     * @return bool true if a thread was successfully selected, false otherwise
     */
    private function selectThreadInteractively(): bool
    {
        // List available threads
        $threads = $this->getAvailableThreads();

        if (empty($threads)) {
            error('❌ No active threads found!');

            return false;
        }

        $threadId = select(
            label: 'Select the thread:',
            options: $threads
        );

        return $this->findThreadById($threadId);
    }

    /**
     * Retrieve available threads formatted for selection
     *
     * @return array Associative array of available threads [thread_id => label]
     */
    private function getAvailableThreads(): array
    {
        return Thread::with(['company', 'assistant'])
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(function ($thread) {
                $messageCount = Message::where('thread_id', $thread->id)->count();

                return [
                    $thread->thread_id => "Thread {$thread->thread_id} ({$thread->company->name}) - {$messageCount} messages",
                ];
            })
            ->toArray();
    }

    /**
     * Find a thread by ID
     *
     * @param  string  $threadId  Thread ID
     * @return bool true if the thread was found, false otherwise
     */
    private function findThreadById(string $threadId): bool
    {
        $this->thread = spin(
            fn () => Thread::where('thread_id', $threadId)->first(),
            'Searching for thread...'
        );

        if (! $this->thread) {
            error("❌ Thread not found: {$threadId}");

            return false;
        }

        info("📝 Thread selected: {$this->thread->thread_id} ({$this->thread->company->name})");

        return true;
    }

    /**
     * Fetch and display thread messages
     *
     * @return bool true if messages were found and displayed, false otherwise
     */
    private function fetchAndDisplayMessages(): bool
    {
        info("\n🔄 Retrieving messages...");

        // Use aiService instead of threadService
        $messages = spin(
            fn () => $this->aiService->thread()->listMessages($this->thread->thread_id, ['limit' => $this->limit]),
            'Please wait...'
        );

        if (empty($messages->data)) {
            info("\n⚠️ No messages found in this thread.");

            return false;
        }

        $this->displayMessages($messages->data);

        return true;
    }

    /**
     * Display messages in chronological order
     *
     * @param  array  $messages  Array of messages
     */
    private function displayMessages(array $messages): void
    {
        info("\n📨 Message History:");
        info(str_repeat('-', 50));

        // Sorting in chronological order (oldest first)
        foreach (array_reverse($messages) as $message) {
            $this->displaySingleMessage($message);
        }
    }

    /**
     * Display a single formatted message
     *
     * @param  object  $message  Message object
     */
    private function displaySingleMessage(object $message): void
    {
        $role = $message->role === 'user' ? '👤 User' : '🤖 Assistant';
        $content = $message->content[0]->text->value;

        // Using the correct property for timestamp
        $timestamp = now()->format('d/m/Y H:i:s');

        info("\n{$role} - {$timestamp}");
        $this->line($content);
        info(str_repeat('-', 50));
    }

    /**
     * Offer additional options if in interactive mode
     */
    private function offerAdditionalOptions(): void
    {
        if ($this->option('interactive')) {
            if (confirm('Do you want to send a new message to this thread?', true)) {
                $this->call('thread:message', [
                    'thread_id' => $this->thread->thread_id,
                    '--interactive' => true,
                ]);
            }
        }
    }
}
