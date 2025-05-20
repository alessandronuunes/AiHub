<?php

namespace Modules\AiHub\Console\Ia\Thread;

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
     * Assinatura do comando com argumentos e opções flexíveis
     */
    protected $signature = 'ai:chat-send
        {thread_id? : ID do thread}
        {--message= : Mensagem a ser enviada}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Envia uma mensagem para um thread existente';

    /**
     * Thread selecionado
     */
    protected Thread $thread;

    /**
     * Serviço de IA
     */
    protected AiService $aiService;

    /**
     * Mensagem a ser enviada
     *
     * Permitindo null para evitar erro ao inicializar
     */
    protected ?string $message = null;

    /**
     * Construtor para injetar dependências
     */
    public function __construct(AiService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    /**
     * Ponto de entrada principal do comando
     */
    public function handle()
    {
        $this->info("\n💬 Assistente de Mensagens\n");

        try {
            // Seleciona o thread
            if (! $this->selectThread()) {
                return 1;
            }

            // Obtém a mensagem a ser enviada
            if (! $this->collectMessage()) {
                outro('Operação cancelada.');

                return 0;
            }

            // Confirma o envio da mensagem
            if (! $this->confirmMessageSending()) {
                outro('Operação cancelada.');

                return 0;
            }

            // Envia a mensagem e processa a resposta
            if (! $this->sendMessageAndProcessResponse()) {
                return 1;
            }

            // Pergunta se deseja continuar a conversa
            if ($this->shouldContinueConversation()) {
                return $this->handle(); // Reinicia o processo
            }

            outro('Conversa finalizada.');

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Erro ao enviar mensagem: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Seleciona o thread para enviar mensagem
     *
     * @return bool true se um thread foi selecionado com sucesso, false caso contrário
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
     * Seleciona o thread interativamente
     *
     * @return bool true se um thread foi selecionado com sucesso, false caso contrário
     */
    private function selectThreadInteractively(): bool
    {
        // Lista threads disponíveis
        $threads = $this->getAvailableThreads();

        if (empty($threads)) {
            error('❌ Nenhum thread ativo encontrado!');

            return false;
        }

        $threadId = select(
            label: 'Selecione o thread:',
            options: $threads
        );

        return $this->findThreadById($threadId);
    }

    /**
     * Recupera os threads disponíveis formatados para seleção
     *
     * @return array Array associativo de threads disponíveis [thread_id => label]
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
     * Encontra um thread pelo ID
     *
     * @param  string  $threadId  ID do thread
     * @return bool true se o thread foi encontrado, false caso contrário
     */
    private function findThreadById(string $threadId): bool
    {
        $this->thread = spin(
            fn () => Thread::where('thread_id', $threadId)->first(),
            'Buscando thread...'
        );

        if (! $this->thread) {
            error("❌ Thread não encontrado: {$threadId}");

            return false;
        }

        info("📝 Thread selecionado: {$this->thread->thread_id} ({$this->thread->company->name})");

        return true;
    }

    /**
     * Coleta a mensagem a ser enviada
     *
     * @return bool true se a mensagem foi coletada com sucesso, false caso contrário
     */
    private function collectMessage(): bool
    {
        $message = $this->option('message');

        if (! $message) {
            $message = text(
                label: 'Digite sua mensagem:',
                required: true,
                validate: fn (string $value) => match (true) {
                    strlen($value) < 2 => 'A mensagem deve ter pelo menos 2 caracteres',
                    default => null
                }
            );
        }

        // Verifica se a mensagem é válida
        if (! $message || strlen(trim($message)) < 2) {
            error('❌ Mensagem inválida. A mensagem deve ter pelo menos 2 caracteres.');

            return false;
        }

        $this->message = $message;

        return true;
    }

    /**
     * Confirma o envio da mensagem se estiver em modo interativo
     *
     * @return bool true se o envio foi confirmado ou não está em modo interativo, false caso contrário
     */
    private function confirmMessageSending(): bool
    {
        if ($this->option('interactive')) {
            return confirm('Deseja enviar esta mensagem?', true);
        }

        return true;
    }

    /**
     * Envia a mensagem e processa a resposta do assistente
     *
     * @return bool true se a mensagem foi enviada e processada com sucesso, false caso contrário
     */
    private function sendMessageAndProcessResponse(): bool
    {
        // Verifica se a mensagem está definida antes de prosseguir
        if (! $this->message) {
            error('❌ Não há mensagem para enviar.');

            return false;
        }

        info("\n🔄 Enviando mensagem...");

        // Envia a mensagem para a OpenAI
        $messageId = $this->sendMessageToOpenAI();

        // Salva a mensagem no banco
        $this->saveMessageToDatabase($messageId, 'user', $this->message);

        // Executa o assistente para obter a resposta
        $run = $this->runAssistant();

        // Aguarda e recupera a resposta do assistente
        $response = $this->waitForAssistantResponse($run['run_id']);

        if (! $response) {
            error('❌ Tempo limite excedido ao aguardar resposta do assistente.');

            return false;
        }

        // Salva a resposta do assistente
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
     * Envia a mensagem para a API da OpenAI
     *
     * @return string ID da mensagem enviada
     */
    private function sendMessageToOpenAI(): string
    {
        return spin(
            fn () => $this->aiService->thread()->addMessage($this->thread->thread_id, $this->message)->id,
            'Aguarde...'
        );
    }

    /**
     * Salva a mensagem no banco de dados
     *
     * @param  string  $messageId  ID da mensagem na API
     * @param  string  $role  Papel da mensagem (user/assistant)
     * @param  string  $content  Conteúdo da mensagem
     * @param  array  $metadata  Metadados adicionais (opcional)
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
     * Executa o assistente para processar a mensagem
     *
     * @return array Dados da execução
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
     * Aguarda e recupera a resposta do assistente
     *
     * @param  string  $runId  ID da execução
     * @return array|false Dados da resposta ou false em caso de timeout
     */
    private function waitForAssistantResponse(string $runId)
    {
        info("\n⏳ Aguardando resposta do assistente...");

        // Aguarda resposta usando o aiService
        $response = spin(
            fn () => $this->aiService->thread()->waitForResponse($this->thread->thread_id, $runId),
            'Processando...'
        );

        // Se não houver resposta, retorna false
        if (! $response) {
            return false;
        }

        // Adaptação do formato de retorno para o formato esperado pelas funções que consomem esta resposta
        return [
            'message_id' => $response->id,
            'content' => $response->content[0]->text->value,
            'role' => $response->role,
        ];
    }

    /**
     * Exibe a resposta do assistente
     *
     * @param  string  $content  Conteúdo da resposta
     */
    private function displayAssistantResponse(string $content): void
    {
        info("\n✅ Mensagem enviada com sucesso!");
        info("\n📨 Resposta do assistente:");
        info($content);
    }

    /**
     * Verifica se deve continuar a conversa
     *
     * @return bool true se deve continuar, false caso contrário
     */
    private function shouldContinueConversation(): bool
    {
        if ($this->option('interactive')) {
            return confirm('Deseja enviar outra mensagem?', true);
        }

        return false;
    }
}
