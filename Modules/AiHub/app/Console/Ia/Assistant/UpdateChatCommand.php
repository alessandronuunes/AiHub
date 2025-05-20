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
use function Laravel\Prompts\text;

class UpdateChatCommand extends Command
{
    protected $signature = 'ai:assistant-update {name? : Nome do assistente a ser atualizado}';

    protected $description = 'Atualiza um assistente OpenAI existente';

    /**
     * Assistente selecionado para atualização
     */
    protected Assistant $assistant;

    /**
     * Novos dados do assistente
     */
    protected array $updatedData = [];

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
        info("\n🔄 Assistente de Atualização\n");

        // Busca o assistente pelo nome ou lista para seleção
        if (! $this->findOrSelectAssistant()) {
            return 1;
        }

        // Configura o aiService para a empresa do assistente selecionado
        $this->aiService->forCompany($this->assistant->company->slug);

        // Coleta as novas informações
        $this->collectUpdatedInformation();

        // Confirma as alterações
        if (! $this->confirmChanges()) {
            outro('Operação cancelada.');

            return 0;
        }

        // Executa a atualização
        if ($this->executeAssistantUpdate()) {
            outro('Operação concluída.');

            return 0;
        }

        return 1;
    }

    /**
     * Busca o assistente pelo nome ou permite seleção interativa
     *
     * @return bool true se o assistente foi encontrado, false caso contrário
     */
    private function findOrSelectAssistant(): bool
    {
        $name = $this->argument('name');

        // Se o nome não foi fornecido, exibe lista para seleção
        if (! $name) {
            return $this->selectAssistantInteractively();
        }

        // Busca pelo nome fornecido
        $this->assistant = Assistant::where('name', $name)->first();

        if (! $this->assistant) {
            error("Assistente '{$name}' não encontrado!");

            // Oferece a opção de selecionar um assistente da lista
            return $this->askToSelectFromList();
        }

        $this->displayAssistantDetails();

        return true;
    }

    /**
     * Pergunta ao usuário se deseja selecionar um assistente da lista
     *
     * @return bool true se o assistente foi selecionado, false caso contrário
     */
    private function askToSelectFromList(): bool
    {
        if (confirm('Deseja selecionar um assistente da lista?', true)) {
            return $this->selectAssistantInteractively();
        }

        return false;
    }

    /**
     * Permite seleção interativa de um assistente da lista
     *
     * @return bool true se um assistente foi selecionado, false caso contrário
     */
    private function selectAssistantInteractively(): bool
    {
        // Busca todos os assistentes
        $allAssistants = Assistant::with('company')->get();

        if ($allAssistants->isEmpty()) {
            error('❌ Nenhum assistente encontrado no sistema!');

            return false;
        }

        info("📝 Listando todos os assistentes disponíveis:\n");

        // Prepara as opções para seleção
        $choices = $allAssistants->mapWithKeys(function ($assistant) {
            return [$assistant->id => "{$assistant->name} (Empresa: {$assistant->company->name})"];
        })->toArray();

        // Permite o usuário escolher qual assistente atualizar
        $selectedId = select(
            label: 'Selecione o assistente que deseja atualizar:',
            options: $choices
        );

        $this->assistant = $allAssistants->firstWhere('id', $selectedId);

        $this->displayAssistantDetails();

        return true;
    }

    /**
     * Exibe os detalhes do assistente encontrado
     */
    private function displayAssistantDetails(): void
    {
        info("📝 Assistente encontrado: {$this->assistant->name}");
        info("🏢 Empresa: {$this->assistant->company->name}");
    }

    /**
     * Coleta as novas informações para atualização do assistente
     */
    private function collectUpdatedInformation(): void
    {
        // Solicita novas informações
        $this->updatedData['name'] = text(
            label: 'Novo nome do assistente (Enter para manter o atual)',
            default: $this->assistant->name,
            required: false
        );

        $this->updatedData['instructions'] = text(
            label: 'Novas instruções (Enter para manter as atuais)',
            default: $this->assistant->instructions,
            required: false
        );
    }

    /**
     * Solicita confirmação das alterações
     *
     * @return bool true se confirmado, false caso contrário
     */
    private function confirmChanges(): bool
    {
        return confirm('Confirma as alterações?', true);
    }

    /**
     * Executa a atualização do assistente na API e no banco de dados
     *
     * @return bool true se a atualização foi bem-sucedida, false caso contrário
     */
    private function executeAssistantUpdate(): bool
    {
        try {
            // Atualiza na API OpenAI
            $this->updateAssistantOnOpenAI();

            // Atualiza no banco de dados local
            $this->updateAssistantInDatabase();

            $this->displaySuccessMessage();

            return true;
        } catch (\Exception $e) {
            error("\n❌ Erro ao atualizar assistente: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Atualiza o assistente na API OpenAI
     *
     * @return object Resposta da API
     */
    private function updateAssistantOnOpenAI()
    {
        return spin(
            callback: fn () => $this->aiService->assistant()->modify($this->assistant->assistant_id, [
                'name' => $this->updatedData['name'],
                'instructions' => $this->updatedData['instructions'],
            ]),
            message: 'Atualizando assistente...'
        );
    }

    /**
     * Atualiza o assistente no banco de dados local
     */
    private function updateAssistantInDatabase(): void
    {
        $this->assistant->update([
            'name' => $this->updatedData['name'],
            'instructions' => $this->updatedData['instructions'],
        ]);
    }

    /**
     * Exibe mensagem de sucesso após a atualização
     */
    private function displaySuccessMessage(): void
    {
        info("\n✅ Assistente atualizado com sucesso!");
    }
}
