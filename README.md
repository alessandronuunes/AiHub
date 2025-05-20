# AiHub Module - Extensible AI Integration for Laravel

This module provides an abstraction layer and Artisan commands to integrate different AI providers (such as OpenAI, Anthropic, etc.) into Laravel applications, with initial focus on Assistant, Threads and Vector Stores functionalities for RAG (Retrieval Augmented Generation).

## Overview

The **AiHub Module** was designed with a flexible architecture to allow easy addition of new AI providers. It acts as a central hub, decoupling your application from each provider's specific implementation.

The module offers commands to manage common AI functionalities, initially implemented for OpenAI:

1. **Assistants**: Create, list, update and delete AI assistants.
2. **Vector Stores**: Manage knowledge bases for RAG (create, list, link, remove).
3. **Threads**: Manage conversations with assistants (create, list messages, send messages).

All commands and service structure follow a consistent design pattern, with clear separation of responsibilities and proper encapsulation.

## Architecture and Extensibility

The strength of this module lies in its architecture, which facilitates the integration of new AI providers:

* **Contracts**: PHP interfaces that define common operations (Assistant, Thread, VectorStore, File, Ai). Your application interacts only with these contracts.
* **Clients**: Specific implementations of contracts for each AI provider (ex: `Modules\AiHub\Ai\Clients\OpenAi\OpenAi`).
* **Factory**: The `AiFactory` class is responsible for creating the correct AI client instance based on configuration or requested provider.
* **Service**: The `AiService` class acts as a facade, using the Factory to get the correct client and expose services (assistant(), thread(), etc.) to your application.

To add a new AI provider, you would typically need to:

1. Create Client classes for the new provider, implementing existing contracts.
2. Add a new entry in `AiFactory` to instantiate the new client.
3. Configure API keys and defaults for the new provider in the module's configuration file.

## Available Commands

### Assistants

| Command | Description |
|---------|-----------|
| `ai:assistant-create [name] [instructions]` | Creates a new OpenAI assistant |
| `ai:assistant-list [company] [--interactive]` | Lists all available assistants |
| `ai:assistant-update [name]` | Updates an existing assistant |
| `ai:assistant-delete [name]` | Removes an existing assistant |

### Vector Stores (Knowledge Bases)

| Command | Description |
|---------|-----------|
| `ai:knowledge-add [company] [--name=] [--description=] [--interactive]` | Creates a new Vector Store for documents |
| `ai:knowledge-list [company] [--interactive]` | Lists all knowledge bases |
| `ai:knowledge-link [company] [--interactive]` | Links a Vector Store to an Assistant |
| `ai:knowledge-remove [company] [--interactive]` | Removes a Vector Store |

### Threads (Conversations)

| Command | Description |
|---------|-----------|
| `ai:chat-start [company] [--interactive]` | Starts a new conversation with an assistant |
| `ai:chat-active [company] [--interactive]` | Lists all active conversations |
| `ai:chat-list [thread_id] [--limit=10] [--interactive]` | Lists messages from a conversation |
| `ai:chat-send [thread_id] [--message=] [--interactive]` | Sends a message to an existing conversation |

## Structure and Design Patterns

All commands were implemented following SOLID principles, especially the Single Responsibility Principle (SRP). Each command follows the same general structure:

1. **Class properties**: Store the command's state during execution
2. **handle() method**: Main entry point, coordinates execution flow
3. **Specific methods**: Each responsibility has its own dedicated method
4. **Error handling**: Implemented at each step to ensure robustness

## Interactive Mode

All commands support an interactive mode (flag `--interactive`), which guides the user through dialogs. This mode is especially useful for beginners.

## Usage Examples

### Create a new assistant:

```bash
php artisan ai:assistant-create "Support Assistant" "This assistant helps with technical support"
```

### List available assistants:

```bash
php artisan ai:assistant-list my-company
```

### Create a knowledge base:

```bash
php artisan ai:knowledge-add my-company --name="Technical Documentation" --description="Knowledge base for technical documentation""
```

### Link a knowledge base to an assistant:
```bash
php artisan ai:knowledge-link my-company
```

### Start a new conversation:
```bash
php artisan ai:chat-start my-company --interactive
```

### Enviar uma mensagem:

```bash
php artisan ai:chat-send --message="Hello, I need help with configuration"
```

## Requirements
- PHP 8.1+
- Laravel 10+
- OpenAI account with access to Assistants API
- AiHub module properly configured
## File Structure

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

## Best Practices Implemented
1. Single Responsibility: Each method and class has a single well-defined responsibility.
2. Encapsulation: State variables are class properties, not local variables.
3. Documentation: All methods have documentation comments (PHPDoc).
4. Error Handling: Failure points are properly handled and reported.
5. User Feedback: Clear messages about operation progress.
6. Input Validation: User-provided data is validated before use.
7. Control Flow: Execution flow is clear and consistent between commands.
8. Extensibility: Contract-based architecture and factory for easy addition of new providers.
## Contributing
This module was built to be extensible! Your contribution is very welcome. If you're interested in the architecture and would like to add support for another AI provider (such as Anthropic, Google AI, etc.), feel free to:

1. Fork this repository.
2. Implement Client classes for the new provider, following existing contracts.
3. Update AiFactory and configuration to include the new provider.
4. Submit a Pull Request with your changes.
   Together, we can make this module a robust hub for integrating various AIs into Laravel projects!

## License
This module is open-source and available under the MIT license.
## Credits  
© 2025 - Developed with Laravel, OpenAI PHP SDK, laravel-modules and the community!