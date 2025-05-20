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
use function Laravel\Prompts\text;

class SendMessageCommand extends Command
{
    /**
     * Command signature with flexible arguments and options
     */
    protected $signature = 'ai:chat-send
        {thread_id? : Thread ID}
        {--message= : Message to be sent}
        {--interactive : Interactive mode with questions}';

    protected $description = 'Send a message to an existing thread';

    /**
     * Selected thread
     */
    protected Thread $thread;

    /**
     * AI Service
     */
    protected AiService $aiService;

    /**
     * Message to be sent
     *
     * Allowing null to avoid initialization error
     */
    protected ?string $message = null;

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
        $this->info("\n💬 Message Assistant\n");

        try {
            // Select the thread
            if (! $this->selectThread()) {
                return 1;
            }

            // Get the message to be sent
            if (! $this->collectMessage()) {
                outro('Operation cancelled.');

                return 0;
            }

            // Confirm message sending
            if (! $this->confirmMessageSending()) {
                outro('Operation cancelled.');

                return 0;
            }

            // Send the message and process the response
            if (! $this->sendMessageAndProcessResponse()) {
                return 1;
            }

            // Ask if you want to continue the conversation
            if ($this->shouldContinueConversation()) {
                return $this->handle(); // Restart the process
            }

            outro('Conversation ended.');

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Error sending message: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Select the thread to send a message
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
                return [
                    $thread->thread_id => "Thread {$thread->thread_id} ({$thread->company->name})",
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
     * Collect the message to be sent
     *
     * @return bool true if the message was successfully collected, false otherwise
     */
    private function collectMessage(): bool
    {
        $message = $this->option('message');

        if (! $message) {
            $message = text(
                label: 'Enter your message:',
                required: true,
                validate: fn (string $value) => match (true) {
                    strlen($value) < 2 => 'The message must have at least 2 characters',
                    default => null
                }
            );
        }

        // Check if the message is valid
        if (! $message || strlen(trim($message)) < 2) {
            error('❌ Invalid message. The message must have at least 2 characters.');

            return false;
        }

        $this->message = $message;

        return true;
    }

    /**
     * Confirm message sending if in interactive mode
     *
     * @return bool true if sending was confirmed or not in interactive mode, false otherwise
     */
    private function confirmMessageSending(): bool
    {
        if ($this->option('interactive')) {
            return confirm('Do you want to send this message?', true);
        }

        return true;
    }

    /**
     * Send the message and process the assistant's response
     *
     * @return bool true if the message was sent and processed successfully, false otherwise
     */
    private function sendMessageAndProcessResponse(): bool
    {
        // Check if the message is defined before proceeding
        if (! $this->message) {
            error('❌ No message to send.');

            return false;
        }

        info("\n🔄 Sending message...");

        // Send the message to OpenAI
        $messageId = $this->sendMessageToOpenAI();

        // Save the message to the database
        $this->saveMessageToDatabase($messageId, 'user', $this->message);

        // Run the assistant to get the response
        $run = $this->runAssistant();

        // Wait for and retrieve the assistant's response
        $response = $this->waitForAssistantResponse($run['run_id']);

        if (! $response) {
            error('❌ Timeout waiting for assistant response.');

            return false;
        }

        // Save the assistant's response
        $this->saveMessageToDatabase(
            $response['message_id'],
            'assistant',
            $response['content'],
            [
                'created_by' => 'assistant',
                'run_id' => $run['run_id'],
                'timestamp' => now()->toIso8601String(),
            ]
        );

        $this->displayAssistantResponse($response['content']);

        return true;
    }

    /**
     * Send the message to the OpenAI API
     *
     * @return string ID of the sent message
     */
    private function sendMessageToOpenAI(): string
    {
        return spin(
            fn () => $this->aiService->thread()->addMessage($this->thread->thread_id, $this->message)->id,
            'Please wait...'
        );
    }

    /**
     * Save the message to the database
     *
     * @param  string  $messageId  Message ID in the API
     * @param  string  $role  Message role (user/assistant)
     * @param  string  $content  Message content
     * @param  array  $metadata  Additional metadata (optional)
     */
    private function saveMessageToDatabase(string $messageId, string $role, string $content, ?array $metadata = null): void
    {
        Message::create([
            'thread_id' => $this->thread->id,
            'message_id' => $messageId,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata ?? [
                'created_by' => 'console',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Run the assistant to process the message
     *
     * @return array Execution data
     */
    private function runAssistant(): array
    {
        $run = $this->aiService->thread()->runAssistant(
            $this->thread->thread_id,
            $this->thread->assistant->assistant_id
        );

        return [
            'run_id' => $run->id,
            'status' => $run->status,
        ];
    }

    /**
     * Wait for and retrieve the assistant's response
     *
     * @param  string  $runId  Run ID
     * @return array|false Response data or false in case of timeout
     */
    private function waitForAssistantResponse(string $runId)
    {
        info("\n⏳ Waiting for assistant response...");

        // Wait for response using aiService
        $response = spin(
            fn () => $this->aiService->thread()->waitForResponse($this->thread->thread_id, $runId),
            'Processing...'
        );

        // If there's no response, return false
        if (! $response) {
            return false;
        }

        // Adaptation of the return format to the format expected by the functions that consume this response
        return [
            'message_id' => $response->id,
            'content' => $response->content[0]->text->value,
            'role' => $response->role,
        ];
    }

    /**
     * Display the assistant's response
     *
     * @param  string  $content  Response content
     */
    private function displayAssistantResponse(string $content): void
    {
        info("\n✅ Message sent successfully!");
        info("\n📨 Assistant's response:");
        info($content);
    }

    /**
     * Check if the conversation should continue
     *
     * @return bool true if it should continue, false otherwise
     */
    private function shouldContinueConversation(): bool
    {
        if ($this->option('interactive')) {
            return confirm('Do you want to send another message?', true);
        }

        return false;
    }
}
