<?php

namespace Modules\AiHub\Console\Ia\Assistant;

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
    protected $signature = 'ai:assistant-delete {name? : Nome do assistente a ser deletado}';

    protected $description = 'Deleta um assistente OpenAI';

    /**
     * Assistente selecionado para exclusão
     */
    protected Assistant $assistant;

    /**
     * Indica se as Vector Stores associadas devem ser excluídas
     */
    protected bool $deleteVectorStores = false;

    /**
     * Serviço de IA
     */
    protected AiService $aiService;

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
        info("\n🗑️ Assistente de Exclusão\n");

        // Busca todos os assistentes
        $allAssistants = $this->getAllAssistants();

        if ($allAssistants->isEmpty()) {
            error('❌ Nenhum assistente encontrado no sistema!');

            return 1;
        }

        // Seleciona o assistente a ser deletado
        if (! $this->selectAssistantToDelete($allAssistants)) {
            return 0;
        }

        // Configura o aiService para a empresa do assistente selecionado
        $this->aiService->forCompany($this->assistant->company->slug);

        // Processa as Vector Stores associadas
        if (! $this->handleAssociatedVectorStores()) {
            return 0;
        }

        // Confirma a exclusão
        if (! $this->confirmDeletion()) {
            outro('Operação cancelada pelo usuário.');

            return 0;
        }

        // Executa a exclusão
        if ($this->executeAssistantDeletion()) {
            outro('Operação concluída.');

            return 0;
        }

        return 1;
    }

    /**
     * Busca todos os assistentes existentes no banco de dados
     *
     * @return \Illuminate\Database\Eloquent\Collection Coleção de assistentes
     */
    private function getAllAssistants()
    {
        return Assistant::with('company')->get();
    }

    /**
     * Permite ao usuário selecionar um assistente para deletar
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $allAssistants  Coleção de assistentes
     * @return bool true se um assistente foi selecionado, false caso contrário
     */
    private function selectAssistantToDelete($allAssistants): bool
    {
        $name = $this->argument('name');

        // Prepara as opções para seleção do assistente
        $choices = $this->prepareAssistantsChoices($allAssistants, $name);

        // Permite o usuário escolher qual assistente deletar
        $selectedId = select(
            label: 'Selecione o assistente que deseja deletar:',
            options: $choices
        );

        $this->assistant = $allAssistants->firstWhere('id', $selectedId);

        $this->displayAssistantDetails();

        return true;
    }

    /**
     * Prepara as opções de assistentes para exibição no seletor
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $allAssistants  Coleção de assistentes
     * @param  string|null  $name  Nome do assistente para filtrar (opcional)
     * @return array Array de opções para o seletor
     */
    private function prepareAssistantsChoices($allAssistants, ?string $name): array
    {
        if ($name) {
            // Se foi fornecido um nome, busca assistentes com esse nome
            $matchingAssistants = $allAssistants->filter(function ($assistant) use ($name) {
                return stripos($assistant->name, $name) !== false;
            });

            if ($matchingAssistants->isEmpty()) {
                info("⚠️ Nenhum assistente encontrado com o nome '{$name}'");
                info("📝 Listando todos os assistentes disponíveis:\n");

                return $allAssistants->mapWithKeys(function ($assistant) {
                    return [$assistant->id => "{$assistant->name} (Empresa: {$assistant->company->name})"];
                })->toArray();
            } else {
                return $matchingAssistants->mapWithKeys(function ($assistant) {
                    return [$assistant->id => "{$assistant->name} (Empresa: {$assistant->company->name})"];
                })->toArray();
            }
        } else {
            info("📝 Listando todos os assistentes disponíveis:\n");

            return $allAssistants->mapWithKeys(function ($assistant) {
                return [$assistant->id => "{$assistant->name} (Empresa: {$assistant->company->name})"];
            })->toArray();
        }
    }

    /**
     * Exibe os detalhes do assistente selecionado
     */
    private function displayAssistantDetails(): void
    {
        info("\n📌 Detalhes do assistente selecionado:");
        info("Nome: {$this->assistant->name}");
        info("Empresa: {$this->assistant->company->name}");
        info("ID OpenAI: {$this->assistant->assistant_id}");
    }

    /**
     * Processa as Vector Stores associadas ao assistente
     *
     * @return bool true se o processo deve continuar, false se deve ser cancelado
     */
    private function handleAssociatedVectorStores(): bool
    {
        // Busca as Vector Stores associadas
        $vectorStores = $this->assistant->vectorStores;

        if ($vectorStores->isEmpty()) {
            return true;
        }

        info("\n📦 Vector Stores associadas encontradas: ".$vectorStores->count());
        foreach ($vectorStores as $vectorStore) {
            info("- {$vectorStore->name} (ID: {$vectorStore->vector_store_id})");
        }

        $action = select(
            label: 'O que você deseja fazer com as Vector Stores associadas?',
            options: [
                'delete_all' => 'Apagar todas as Vector Stores',
                'keep_all' => 'Manter todas as Vector Stores',
                'cancel' => 'Cancelar operação',
            ]
        );

        if ($action === 'cancel') {
            info('Operação cancelada pelo usuário.');

            return false;
        }

        $this->deleteVectorStores = ($action === 'delete_all');

        return true;
    }

    /**
     * Solicita confirmação antes de executar a exclusão
     *
     * @return bool true se confirmado, false caso contrário
     */
    private function confirmDeletion(): bool
    {
        return confirm("\n⚠️  Tem certeza que deseja deletar este assistente?", false);
    }

    /**
     * Executa a exclusão do assistente e das Vector Stores associadas (se solicitado)
     *
     * @return bool true se a exclusão foi bem-sucedida, false caso contrário
     */
    private function executeAssistantDeletion(): bool
    {
        try {
            info("\n🔄 Deletando assistente...");

            $this->deleteAssociatedVectorStores();
            $this->deleteAssistantFromOpenAI();
            $this->deleteAssistantFromDatabase();

            $this->displaySuccessMessage();

            return true;

        } catch (\Exception $e) {
            error("\n❌ Erro ao deletar assistente: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Exclui as Vector Stores associadas se solicitado
     */
    private function deleteAssociatedVectorStores(): void
    {
        $vectorStores = $this->assistant->vectorStores;

        if ($vectorStores->isNotEmpty() && $this->deleteVectorStores) {
            info("\n🗑️  Removendo Vector Stores associadas...");
            foreach ($vectorStores as $vectorStore) {
                spin(
                    fn () => $this->aiService->vectorStore()->delete($vectorStore->vector_store_id, true),
                    "Removendo Vector Store {$vectorStore->name}..."
                );
            }
        }
    }

    /**
     * Exclui o assistente na API da OpenAI
     */
    private function deleteAssistantFromOpenAI(): void
    {
        spin(
            fn () => $this->aiService->assistant()->delete($this->assistant->assistant_id),
            'Removendo assistente...'
        );
    }

    /**
     * Exclui o assistente do banco de dados local
     */
    private function deleteAssistantFromDatabase(): void
    {
        // Limpa os relacionamentos no banco
        $this->assistant->vectorStores()->detach();

        // Deleta o registro do assistente no banco de dados
        spin(
            fn () => $this->assistant->delete(),
            'Removendo registros do banco de dados...'
        );
    }

    /**
     * Exibe mensagem de sucesso após a exclusão
     */
    private function displaySuccessMessage(): void
    {
        info("\n✅ Assistente deletado com sucesso!");
        if ($this->deleteVectorStores) {
            info('✅ Vector Stores associadas foram removidas.');
        }
    }
}
