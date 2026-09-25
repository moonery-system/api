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
- **`GeminiLlmClient`** escrito contra o formato `generateContent` que eu conhecia, **não
  validado contra a API real ainda** (a sonda espera autorização). As `parts` cruas do turno do
  modelo ficam em `providerState` e voltam sem alteração, para as *thought signatures*
  atravessarem onde quer que estejam. Tokens de raciocínio contam como saída
  (`totalTokenCount - promptTokenCount`).
- **Problema:** 3 testes do Gemini falharam com "esperava 404, veio 503". Causa: `Http::fake()`
  empilha stubs e o primeiro que casa ganha; um teste que fakeia duas vezes continua ouvindo a
  primeira resposta. **Correção:** o helper de teste recria a factory a cada stub. O código de
  produção não mudou. (Antes disso, um conflito de nome: `client()` do meu teste colidia com o
  `client()` da `TestCase`.)
- **Ferramentas** (`list_my_deliveries`, `get_delivery`, `can_cancel_delivery`,
  `request_cancel_delivery`, `handoff_to_support`) atrás de um `ToolRegistry`. O usuário vem do
  `ToolContext` (da conversa). `ValidatedTool` entrega ao `handle()` só as chaves que as regras
  mencionam, então um `client_id` forjado morre na validação e, de qualquer forma, nenhuma
  ferramenta o lê. Entrega alheia e entrega inexistente devolvem exatamente o mesmo conteúdo.
- **Decisão:** as ferramentas não escrevem no chat; registram o efeito no `ToolContext`
  (confirmação pendente, pedido de handoff) e o runner age. Assim o encaminhamento acontece num
  lugar só, pedido pelo modelo ou forçado por um limite.
- **Verificação dos testes de segurança:** eles passaram de primeira, o que não prova nada. Removi
  temporariamente o `where('client_id', ...)` do repositório: 4 testes de escopo falharam, como
  deviam; restaurei o arquivo.
- **Problema:** `run()` colidia com o método do PHPUnit (mesma família do `client()` anterior).
  Renomeado.
- **`AssistantRunner`** (o laço de ferramentas) e **`AssistantDispatcher`** (decide se enfileira ou
  silencia). `ConversationService::sendAsAssistant()` grava a mensagem do bot sem `auth()`; a
  lógica de gravar+publicar foi extraída para `storeAndPublish()` e é a mesma nos dois caminhos.
  O bot não dispara a si mesmo (o dispatcher não é chamado nesse caminho).
- **Decisão:** quando há confirmação de cancelamento pendente, o texto da resposta é **fixo**
  (config), não o do modelo. Assim o modelo não consegue dizer ao cliente que algo foi cancelado
  quando só foi perguntado. O teste de injeção em resultado de ferramenta usa um modelo
  totalmente obediente ("Cancelei tudo!") e prova que a resposta ao cliente não contém isso e
  que a entrega segue `pending`.
- **Decisão:** toda falha (provedor, autenticação, 429 esgotado, teto diário, prazo, limite por
  usuário, teto de iterações, resposta vazia) termina igual: mensagem fixa de fallback +
  `handed_off` com o motivo. Uma confirmação criada antes da falha é descartada (`superseded`).
- **Decisão:** chamadas de ferramenta em paralelo: só as 5 primeiras são executadas, as demais
  recebem um resultado de erro (o provedor exige uma resposta para cada chamada).
- `ASSISTANT_ENABLED=false` no `phpunit.xml`; os testes do assistente ligam com `config()->set`.
- **Verificação por mutação** dos testes do runner (eles passaram de primeira): removi a
  idempotência → 2 falhas; tornei a elegibilidade sempre verdadeira → 2 falhas (silêncio); deixei
  o texto do modelo prevalecer sobre a confirmação → 2 falhas. Arquivo restaurado depois de cada.
- **Confirmação** em `POST /api/assistant/actions/{id}/confirm|reject` (`can:deliveries.cancel`).
  Confirmar faz claim atômico (`pending → confirmed`, só se não expirou) e chama
  `DeliveryService::cancelDelivery()` — o mesmo caminho do cancelamento manual, então o validador
  barra `picked_up`/`in_transit`/`delivered` (409, ação `failed`, entrega intocada). Um "sim"
  digitado no chat não cancela (teste com o runner de ponta a ponta).
- **Problema (meu):** na primeira versão, quando o claim falhava eu marcava a ação como `expired`
  incondicionalmente. Se o clique perdedor chegasse depois do vencedor, sobrescreveria `confirmed`
  com `expired`. **Correção:** `expireIfDue()`, condicional (só `pending` e vencida).
- **Problema (nos testes):** a mutação "claim sem condição de status" **sobreviveu** aos testes
  de endpoint, porque o service checa o status antes de reivindicar e isso mascara a condição do
  claim em testes sequenciais; contra dois cliques simultâneos, só o update condicional protege.
  **Correção:** testes direto no repositório para o claim e para `expireIfDue`; repeti as
  mutações e agora falham. Também troquei uma asserção minha ilegível (`count() - 0 ? 1 : 0`) por
  uma que diz o que quer dizer.
