# AiHub Module - Integração Extensível de IA para Laravel

Este módulo fornece uma camada de abstração e comandos Artisan para integrar diferentes provedores de IA (como OpenAI, Anthropic, etc.) em aplicações Laravel, com foco inicial em funcionalidades de Assistentes, Threads e Vector Stores para RAG (Retrieval Augmented Generation).

## Visão Geral

O **AiHub Module** foi projetado com uma arquitetura flexível para permitir a fácil adição de novos provedores de IA. Ele atua como um hub central, desacoplando sua aplicação da implementação específica de cada provedor.

O módulo oferece comandos para gerenciar funcionalidades comuns de IA, inicialmente implementadas para OpenAI:

1.  **Assistentes**: Criar, listar, atualizar e deletar assistentes de IA.
2.  **Vector Stores**: Gerenciar bases de conhecimento para RAG (criar, listar, vincular, remover).
3.  **Threads**: Gerenciar conversas com assistentes (criar, listar mensagens, enviar mensagens).

Todos os comandos e a estrutura de serviço seguem um padrão de design consistente, com separação clara de responsabilidades e encapsulamento adequado.
## Arquitetura e Extensibilidade

A força deste módulo reside em sua arquitetura, que facilita a integração de novos provedores de IA:

*   **Contracts (Contratos)**: Interfaces PHP que definem as operações comuns (Assistant, Thread, VectorStore, File, Ai). Sua aplicação interage apenas com esses contratos.
*   **Clients (Clientes)**: Implementações específicas dos contratos para cada provedor de IA (ex: `Modules\AiHub\Ai\Clients\OpenAi\OpenAi`).
*   **Factory (Fábrica)**: A classe `AiFactory` é responsável por criar a instância correta do cliente de IA com base na configuração ou no provedor solicitado.
*   **Service (Serviço Principal)**: A classe `AiService` atua como uma fachada, utilizando a Factory para obter o cliente correto e expor os serviços (assistant(), thread(), etc.) para sua aplicação.

Para adicionar um novo provedor de IA, você geralmente precisaria:

1.  Criar as classes de Cliente para o novo provedor, implementando os contratos existentes.
2.  Adicionar uma nova entrada na `AiFactory` para instanciar o novo cliente.
3.  Configurar as chaves de API e defaults para o novo provedor no arquivo de configuração do módulo.

## Comandos Disponíveis

### Assistentes

| Comando | Descrição |
|---------|-----------|
| `ai:assistant-create [name] [instructions]` | Cria um novo assistente OpenAI |
| `ai:assistant-list [company] [--interactive]` | Lista todos os assistentes disponíveis |
| `ai:assistant-update [name]` | Atualiza um assistente existente |
| `ai:assistant-delete [name]` | Remove um assistente existente |

### Vector Stores (Bases de Conhecimento)

| Comando | Descrição |
|---------|-----------|
| `ai:knowledge-add [company] [--name=] [--description=] [--interactive]` | Cria uma nova Vector Store para documentos |
| `ai:knowledge-list [company] [--interactive]` | Lista todas as bases de conhecimento |
| `ai:knowledge-link [company] [--interactive]` | Associa uma Vector Store a um Assistente |
| `ai:knowledge-remove [company] [--interactive]` | Remove uma Vector Store |

### Threads (Conversas)

| Comando | Descrição |
|---------|-----------|
| `ai:chat-start [company] [--interactive]` | Inicia uma nova conversa com um assistente |
| `ai:chat-active [company] [--interactive]` | Lista todas as conversas ativas |
| `ai:chat-list [thread_id] [--limit=10] [--interactive]` | Lista as mensagens de uma conversa |
| `ai:chat-send [thread_id] [--message=] [--interactive]` | Envia uma mensagem para uma conversa existente |

## Estrutura e Padrões de Design

Todos os comandos foram implementados seguindo princípios SOLID, especialmente o princípio de responsabilidade única (SRP). Cada comando segue a mesma estrutura geral:

1. **Propriedades da classe**: Armazenam o estado do comando durante a execução
2. **Método handle()**: Ponto de entrada principal, coordena o fluxo de execução
3. **Métodos específicos**: Cada responsabilidade tem seu próprio método dedicado
4. **Tratamento de erros**: Implementado em cada etapa para garantir robustez

## Modo Interativo

Todos os comandos suportam um modo interativo (flag `--interactive`), que orienta o usuário através de diálogos. Este modo é especialmente útil para usuários iniciantes.

## Exemplos de Uso

### Criar um novo assistente:

```bash
php artisan ai:assistant-create "Assistente de Suporte" "Este assistente ajuda com suporte técnico"
```

### Listar assistentes disponíveis:

```bash
php artisan ai:assistant-list minha-empresa
```

### Criar uma base de conhecimento:

```bash
php artisan ai:knowledge-add minha-empresa --name="Documentação Técnica" --description="Base de conhecimento para documentação técnica"
```

### Vincular uma base de conhecimento a um assistente:

```bash
php artisan ai:knowledge-link minha-empresa
```

### Iniciar uma nova conversa:

```bash
php artisan ai:chat-start minha-empresa --interactive
```

### Enviar uma mensagem:

```bash
php artisan ai:chat-send --message="Olá, preciso de ajuda com configuração"
```

## Requisitos

- PHP 8.1+
- Laravel 10+
- Conta OpenAI com acesso à API de Assistentes
- Módulo AiHub configurado corretamente

## Estrutura de Arquivos

```
Modules/AiHub/
├── Console/
│   ├── Ia/
│   │   ├── Assistant/
│   │   │   ├── CreateChatCommand.php
│   │   │   ├── DeleteChatCommand.php
│   │   │   ├── ListAssistantCommand.php
│   │   │   └── UpdateChatCommand.php
│   │   ├── Thread/
│   │   │   ├── CreateThreadCommand.php
│   │   │   ├── ListMessagesCommand.php
│   │   │   ├── ListThreadCommand.php
│   │   │   └── SendMessageCommand.php
│   │   └── VectorStore/
│   │       ├── AttachVectorCommand.php
│   │       ├── CreateVectorCommand.php
│   │       ├── DeleteVectorCommand.php
│   │       └── ListVectorCommand.php
├── Models/
│   ├── Assistant.php
│   ├── Company.php
│   ├── Message.php
│   ├── Thread.php
│   └── VectorStore.php
├── Ai/
│   ├── Contracts/
│   │   ├── Ai.php
│   │   ├── Assistant.php
│   │   ├── Thread.php
│   │   ├── VectorStore.php
│   │   └── File.php
│   │
│   ├── Clients/
│   │   ├── OpenAi/
│   │   │   ├── OpenAi.php
│   │   │   ├── OpenAiAssistant.php
│   │   │   ├── OpenAiThread.php
│   │   │   ├── OpenAiVectorStore.php
│   │   │   └── OpenAiFile.php
│   │   │
│   │   └── Anthropic/
│   │       └── ... (Exemplo para futuros provedores)
│   │
│   ├── Factory/
│   │   └── AiFactory.php
│   │
│   ├── AiService.php
│   └── AiServiceProvider.php
└── config/
    └── aihub.php
```

## Boas Práticas Implementadas
1. Responsabilidade Única : Cada método e classe tem uma única responsabilidade bem definida.
2. Encapsulamento : Variáveis de estado são propriedades da classe, não variáveis locais.
3. Documentação : Todos os métodos possuem comentários de documentação (PHPDoc).
4. Tratamento de Erros : Pontos de falha são tratados e reportados adequadamente.
5. Feedback ao Usuário : Mensagens claras sobre o progresso da operação.
6. Validação de Entrada : Dados fornecidos pelo usuário são validados antes do uso.
7. Fluxo de Controle : O fluxo de execução é claro e consistente entre comandos.
8. Extensibilidade : Arquitetura baseada em contratos e fábrica para fácil adição de novos provedores.

## Contribuindo

Este módulo foi construído para ser extensível! Sua contribuição é muito bem-vinda. Se você se interessou pela arquitetura e gostaria de adicionar suporte a outro provedor de IA (como Anthropic, Google AI, etc.), sinta-se à vontade para:

1. Fazer um fork deste repositório.
2. Implementar as classes de Cliente para o novo provedor, seguindo os contratos existentes.
3. Atualizar a AiFactory e a configuração para incluir o novo provedor.
4. Enviar um Pull Request com suas mudanças.
Juntos, podemos tornar este módulo um hub robusto para integração de diversas IAs em projetos Laravel!

---

© 2025 - Desenvolvido com Laravel, OpenAI PHP SDK, laravel-modules e a comunidade!