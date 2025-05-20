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

class ListMessagesCommand extends Command
{
    /**
     * Assinatura do comando com argumentos e opções flexíveis
     */
    protected $signature = 'ai:chat-list
        {thread_id? : ID do thread}
        {--limit=10 : Número de mensagens a serem exibidas}
        {--interactive : Modo interativo com perguntas}';

    protected $description = 'Lista as mensagens de um thread específico';

    /**
     * Thread selecionado
     */
    protected Thread $thread;

    /**
     * Serviço de IA
     */
    protected AiService $aiService;

    /**
     * Limite de mensagens a serem exibidas
     */
    protected int $limit;

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
        info("\n📋 Histórico de Mensagens\n");

        try {
            // Define o limite de mensagens
            $this->setMessageLimit();

            // Seleciona o thread
            if (! $this->selectThread()) {
                return 1;
            }

            // Recupera e exibe as mensagens
            if (! $this->fetchAndDisplayMessages()) {
                return 0;
            }

            // Oferece opções adicionais se estiver em modo interativo
            $this->offerAdditionalOptions();

            outro('Visualização concluída.');

            return 0;

        } catch (\Exception $e) {
            error("\n❌ Erro ao listar mensagens: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Define o limite de mensagens a serem exibidas
     */
    private function setMessageLimit(): void
    {
        $this->limit = (int) $this->option('limit');
    }

    /**
     * Seleciona o thread para listar mensagens
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
                $messageCount = Message::where('thread_id', $thread->id)->count();

                return [
                    $thread->thread_id => "Thread {$thread->thread_id} ({$thread->company->name}) - {$messageCount} mensagens",
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
     * Recupera e exibe as mensagens do thread
     *
     * @return bool true se mensagens foram encontradas e exibidas, false caso contrário
     */
    private function fetchAndDisplayMessages(): bool
    {
        info("\n🔄 Recuperando mensagens...");

        // Usa o aiService ao invés do threadService
        $messages = spin(
            fn () => $this->aiService->thread()->listMessages($this->thread->thread_id, ['limit' => $this->limit]),
            'Aguarde...'
        );

        if (empty($messages->data)) {
            info("\n⚠️ Nenhuma mensagem encontrada neste thread.");

            return false;
        }

        $this->displayMessages($messages->data);

        return true;
    }

    /**
     * Exibe as mensagens em ordem cronológica
     *
     * @param  array  $messages  Array de mensagens
     */
    private function displayMessages(array $messages): void
    {
        info("\n📨 Histórico de Mensagens:");
        info(str_repeat('-', 50));

        // Ordenando em ordem cronológica (mais antigas primeiro)
        foreach (array_reverse($messages) as $message) {
            $this->displaySingleMessage($message);
        }
    }

    /**
     * Exibe uma única mensagem formatada
     *
     * @param  object  $message  Objeto de mensagem
     */
    private function displaySingleMessage(object $message): void
    {
        $role = $message->role === 'user' ? '👤 Usuário' : '🤖 Assistente';
        $content = $message->content[0]->text->value;

        // Usando a propriedade correta para timestamp
        $timestamp = now()->format('d/m/Y H:i:s');

        info("\n{$role} - {$timestamp}");
        $this->line($content);
        info(str_repeat('-', 50));
    }

    /**
     * Oferece opções adicionais se estiver em modo interativo
     */
    private function offerAdditionalOptions(): void
    {
        if ($this->option('interactive')) {
            if (confirm('Deseja enviar uma nova mensagem para este thread?', true)) {
                $this->call('thread:message', [
                    'thread_id' => $this->thread->thread_id,
                    '--interactive' => true,
                ]);
            }
        }
    }
}
