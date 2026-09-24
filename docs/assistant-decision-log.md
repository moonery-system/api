# Log de decisões — assistente de IA

Registro do que realmente aconteceu durante a implementação: data, decisão, problema
encontrado, correção. Nada aqui é retroativo nem aspiracional.

## 2026-09-26

- **Leitura do código e plano.** Plano aprovado (arquivo do plano fora do repositório).
  Decisões: bot como usuário de role `Assistant` sem permissões; disparo por permissão
  `assistant.use`; estado de encaminhamento em colunas de `conversations`; confirmação de
  cancelamento em tabela própria; consumidor RabbitMQ (`assistant.requests`); contador diário
  em tabela.
- **Problema:** a árvore de `api/` já estava suja (25 arquivos do trabalho de phpstan, não
  commitados). **Correção:** só faço `git add` dos arquivos deste trabalho.
- **Problema:** a documentação atual do Gemini promove a Interactions API, e o formato das
  *thought signatures* em `generateContent` não pôde ser confirmado pela leitura das páginas.
  **Decisão:** seguir `generateContent` (como pedido) e devolver ao modelo as `parts` cruas do
  turno anterior, sem alteração.
- **Baseline:** `php artisan test` → 29 testes passando antes de qualquer alteração.
- `config/assistant.php` e chaves no `.env.example` (sem valores). `ASSISTANT_ENABLED` tem
  default `true` no config; o `phpunit.xml` vai desligá-lo.
- **Validador:** `checkTransition()` extraído; `assertCanTransition()` passou a chamá-lo. Uma só
  fonte de regra; mensagens idênticas (teste de regressão compara a razão devolvida com a
  exceção lançada).
- **Problema:** `Conversation.php` e `Message.php` continham edições não commitadas do phpstan
  misturadas com as minhas. **Correção:** stage parcial (índice montado a partir do HEAD + só as
  minhas mudanças), com autorização do usuário; a árvore de trabalho dele ficou intacta.
- **Esquema:** 4 migrations (estado do assistente em `conversations`, `assistant_runs`,
  `assistant_pending_actions`, `assistant_usage_daily`). Rodaram só no banco `moonery_test`
  (via `RefreshDatabase`); o banco de desenvolvimento não foi tocado.
- **Bot:** usuário sem senha (não loga) com role `Assistant` sem permissões; disparo por
  `assistant.use`, concedida a Client e Admin. `AssistantSeeder` é idempotente para funcionar
  também num banco já existente. Efeito colateral aceito: `GET /roles` passa a listar `Assistant`.
- **`GuardedLlmClient`:** retry/backoff/espaçamento/tetos num decorator. Cada tentativa (inclusive
  429) conta como chamada no teto diário, porque é assim que a cota do provedor a conta.
- **Problema:** um teste de virada de dia falhou. Era erro meu de aritmética (em março o Pacífico
  é UTC−8, não −7), não do código. **Correção:** o teste, não a implementação.
