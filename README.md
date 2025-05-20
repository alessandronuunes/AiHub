# OpenAI RAG Module - Comandos Artisan

Este módulo fornece uma série de comandos Artisan para gerenciar Assistentes, Threads e Vector Stores no OpenAI RAG (Retrieval Augmented Generation).

## Visão Geral

O módulo oferece comandos para:

1. **Assistentes**: Criar, listar, atualizar e deletar assistentes OpenAI
2. **Vector Stores**: Criar, listar, vincular e remover bases de conhecimento
3. **Threads**: Criar conversas, listar mensagens e enviar mensagens para assistentes

Todos os comandos seguem um padrão de design consistente, com separação clara de responsabilidades e encapsulamento adequado.

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
- Módulo OpenAiRag configurado corretamente

## Estrutura de Arquivos

```
Modules/OpenAiRag/
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
└── Services/
    ├── OpenAIService.php
    ├── ThreadService.php
    └── VectorStoreService.php
```

## Boas Práticas Implementadas

1. **Responsabilidade Única**: Cada método tem uma única responsabilidade bem definida
2. **Encapsulamento**: Variáveis de estado são propriedades da classe, não variáveis locais
3. **Documentação**: Todos os métodos possuem comentários de documentação (PHPDoc)
4. **Tratamento de Erros**: Pontos de falha são tratados e reportados adequadamente
5. **Feedback ao Usuário**: Mensagens claras sobre o progresso da operação
6. **Validação de Entrada**: Dados fornecidos pelo usuário são validados antes do uso
7. **Fluxo de Controle**: O fluxo de execução é claro e consistente entre comandos

## Contribuindo

Para contribuir com este módulo:

1. Siga o mesmo padrão de design implementado nos comandos existentes
2. Certifique-se de implementar tratamento de erros adequado
3. Inclua comentários de documentação (PHPDoc) em todos os métodos
4. Execute testes antes de submeter alterações

---

© 2025 - Desenvolvido com Laravel, PHP e OpenAI