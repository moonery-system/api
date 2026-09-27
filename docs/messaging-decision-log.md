# Log de decisões — retry e DLQ na mensageria

Registro do que realmente aconteceu: data, decisão, problema encontrado, correção. Só o que
de fato aconteceu, nada retroativo.

## 2026-09-27

- **Achado fora de escopo, não corrigido:** `tests/Unit` foi apagado num commit anterior
  (`bc4135c`, 21/09) mas `phpunit.xml` ainda lista essa suíte. `php artisan test` puro
  quebra ("Test directory ... not found") antes de rodar qualquer teste — provavelmente
  quebra o CI também, já que um checkout limpo não recria diretório vazio. Usei
  `php artisan test --testsuite=Feature` como contorno em toda a verificação deste
  trabalho. Não mexi em `phpunit.xml` sem autorização, por ser fora do escopo aprovado.
- **`RetryPolicy`/`RetryDecision`/`PermanentFailureException`** (`app/Messaging/`): decide
  patamar de retry (1/2/3, config `messaging.retry.tiers_ms`) ou DLQ, sem I/O algum. Falha
  permanente (`PermanentFailureException`) vai para DLQ mesmo com patamar disponível.
- **Verificação por mutação:** removi a checagem de exceção permanente, a de esgotamento de
  patamares e travei sempre no patamar 1 — as três vezes os testes certos falharam. Na
  primeira tentativa da mutação de esgotamento, o `sed` não bateu no texto exato (nenhuma
  mudança real) e os testes passaram por isso, não porque a lógica resistisse; refiz com um
  `replace` que confirma a âncora antes de gravar, e aí sim os dois testes esperados
  falharam. Registro isso porque quase virou um falso positivo na verificação.

- **`RabbitMQPublisher::publishToQueue()`** (exchange padrão, routing key = nome da fila) e
  **`RabbitMQConsumer::declareRetryLadder()`** (3 filas de espera + DLQ por fila principal).
  `FakeRabbitMQPublisher` ganhou o espelho (`$publishedToQueue`). `config/messaging.php` com
  os 3 patamares (5s/30s/5min, env-configurável).
- **Verificado contra o broker real** (não dá para testar isso sem I/O): declarei uma fila
  de sondagem (`zz.probe.queue` + patamares), publiquei na `.retry.1` e confirmei via
  `rabbitmqctl list_queues … arguments` que os argumentos batem
  (`x-message-ttl`/`x-dead-letter-exchange`/`x-dead-letter-routing-key`). **Achado no
  processo:** minha primeira tentativa declarou a fila principal DEPOIS de publicar na
  fila de retry — a mensagem expirou e dead-letterou para uma fila que ainda não existia,
  e se perdeu (exchange padrão descarta em silêncio se o destino não existe). Não é bug do
  código: no fluxo real a fila principal já existe (é o próprio consumidor que a declara ao
  subir, antes de qualquer coisa poder falhar). Refiz com a ordem certa e a mensagem voltou
  para `zz.probe.queue` depois dos 5s. Apaguei as 5 filas de sondagem ao final
  (`rabbitmqctl delete_queue`); as 4 filas do projeto não foram tocadas.

- **Marcas de idempotência:** `invites.email_sent_at` (separada de `used_at`, que é outra
  coisa: o convidado clicou o link) e `user_notifications.emailed_at` (por destinatário,
  ao lado de `read_at`). Migrations aditivas rodadas no banco de dev com autorização
  explícita — só adicionam coluna nullable, nenhum dado tocado.

- **`ConsumeEmailQueue` reescrito.** `process(AMQPMessage): string` público, sem console
  nem canal — a lição da flag de header (seção "o que encontrei") virou o teste
  `test_the_original_routing_key_survives_the_bounce_through_a_retry_queue`. Payload que
  não é JSON válido e routing key desconhecida vão direto para a DLQ, sem gastar patamar.
  Entrega/notificação inexistente e payload sem o id esperado seguem como no-op silencioso
  (decisão registrada no plano: não é bug, é escopo — "sem overengineering" para um caso
  que praticamente não acontece na prática, dado que o convite é criado antes de publicar).
- **Problema (meu):** os handlers ainda chamavam `$this->info(...)`, que exige console —
  quebrou toda chamada direta a `process()` em teste ("Call to a member function writeln()
  on null"), a mesma armadilha já documentada no assistente. Troquei por `Log::info`.
- **Verificação por mutação:** cinco garantias centrais — preferir o header de routing key,
  filtrar só destinatário pendente, checar `email_sent_at`, validar JSON, e respeitar a
  decisão do `RetryPolicy` — cada uma removida uma de cada vez; todas quebram o teste certo.
  Duas das tentativas (A e B, a primeira rodada) foram no-op silencioso por um erro meu de
  escape num heredoc (a mesma classe de erro do passo do `RetryPolicy`); refiz com âncora
  verificada e as mutações passaram a falhar como deviam.

- **`ConsumeAssistantQueue` com o mesmo mecanismo.** `process(array $data, int
  $priorFailures = 0)` manteve compatibilidade com os 3 testes já existentes (assinatura
  antiga sem o segundo argumento continua funcionando). Como quase tudo já é absorvido
  dentro do `AssistantRunner`, o único jeito de exercitar o retry aqui em teste é trocar o
  `AssistantRunner` inteiro por um mock cujo `handle()` lança direto — é a rede de
  segurança para falha de infraestrutura *antes* do runner (ex.: `claim()` sem banco),
  não conserto de bug observado, como já dizia o plano.
- **Payload sem `message_id` numérico agora vai para a DLQ**, em vez de só um "Ignoring…"
  que não ficava em lugar nenhum — pequena melhoria de visibilidade, reaproveitando a
  mesma `PermanentFailureException`.
- **Verificação por mutação:** decisão de retry ignorada, payload envenenado voltando a
  ser descartado em silêncio, e o header `x-retry-attempt` ignorado — as três primeiras
  falharam nos testes certos de cara. A terceira (header ignorado) **sobreviveu** na
  primeira rodada: nenhum teste chamava `process()` através do caminho que lê o header
  (todos passavam `priorFailures` já pronto). Tornei `priorFailures(AMQPMessage)` público e
  escrevi dois testes específicos para ele; a mutação passou a falhar como devia.

- **`customs:dlq:list/replay/check`** (`App\Messaging\DlqInspector`, canal cru — não dá
  para testar sem broker). `{queue}` aceita `emails`/`assistant`; para o assistente a
  routing key de replay é fixa (`assistant.requests`), porque o envelope da DLQ dele não
  guarda uma (só existe uma rota possível).
- **Achado no phpstan:** o larastan interpreta `--` dentro da descrição de um argumento de
  `$signature` como início de outra opção, e reprova `$this->argument('queue')` com "does
  not have argument". Troquei o texto da descrição para não usar `--`.
- **Bug real, achado só ao testar contra o broker (não pego por unidade, como o plano já
  previa):** `dlq:list` mostrava a **mesma** mensagem repetida até o `--limit`, quando a
  fila tinha menos mensagens que o limite (testei com 1 mensagem e `--limit=20`: apareceu
  20 vezes). Causa: `peek()` fazia `basic_get` → `nack(requeue: true)` **dentro do mesmo
  laço**, e o `nack` devolve a mensagem para a **frente** da fila antes da próxima
  iteração, então o próximo `basic_get` repescava ela mesma. `replay()` tinha a mesma
  falha, mais grave: uma entrada malformada devolvida por `nack` no meio do laço seria
  repescada para sempre, sem nunca alcançar as outras. **Correção:** buscar tudo primeiro
  (`take()`) — uma mensagem ainda não confirmada não é reentregue no mesmo canal, então
  cada `basic_get` já devolve uma distinta — e só depois decidir/devolver cada uma.
  Reverifiquei ao vivo com 3 mensagens distintas: `list` passou a mostrar as 3 (e repetir
  a listagem não muda nada), `replay --limit=1` tirou uma só, `replay --all` tirou as
  outras duas, e o `dlq:check` refletiu a profundidade certa em cada passo.
- **Roteiro de caos rodado de verdade** (autorizado): parei o Mailpit, criei um usuário e
  convite descartáveis, vi a falha real (`Connection could not be established with host
  "mailpit"`) cair no patamar 1. Religuei o Mailpit — a 2ª tentativa ainda falhou (o DNS do
  Docker para o serviço recém-reiniciado ainda não tinha propagado; não é bug do código),
  e a 3ª (patamar 2, ~30s depois) chegou: `Handled invites.email`, e-mail encontrado no
  Mailpit, `email_sent_at` carimbado. Apaguei o usuário e o convite de teste ao final.

## 2026-09-27 — websocket-api (Hyperf)

- **`App\Amqp\DeadLetterPublisher`** pega um canal cru via `ConnectionFactory` (mesmo
  padrão do lado Laravel), **sem passar pelo `Producer` do Hyperf**: `Producer::produce()`
  sempre tenta declarar a exchange na primeira vez, e a exchange sem nome (a que a
  publicação direta numa fila usa) é reservada — o RabbitMQ recusa um `exchange.declare`
  explícito para ela. Confirmado lendo o vendor (`Builder::declare()`), não só por teoria.
- **`ChatMessageConsumer`/`WebsocketNotificationConsumer`** ganharam `try/catch`: uma
  exceção publica na `.dlq` correspondente, loga estruturado e devolve `Result::DROP`
  (igual a hoje — nenhuma mudança na fila principal). Sem escada de retry aqui: não existe
  falha transitória plausível em empurrar para um WebSocket, e destinatário offline já não
  chega a esse ponto (`Result::DROP` de registro-não-encontrado continua igual).
- **`websocket-api` não tem suíte**, então tudo foi verificado ao vivo contra o broker:
  - Tentativa de rodar o `DeadLetterPublisher` num script avulso (fora do servidor) deu
    `Swoole\Error: API must be called in the coroutine` — o AMQP do Hyperf exige contexto
    de corrotina. Envolvendo em `Swoole\Coroutine\run()` o publish funcionou, mas o
    processo **nunca terminou sozinho** (a conexão do Hyperf sobe corrotinas de
    heartbeat/leitura em segundo plano que não são canceladas) — matei o processo à força e
    apaguei a fila de sondagem que ele criou.
  - **Verificação real, através do servidor Hyperf já rodando** (reiniciado para carregar o
    código): publiquei `chat.messages` com `recipient_ids` como string (viola o tipo
    `array` de `WebSocketService::sendToUserIds`) → `TypeError` capturado, registrado em
    `runtime/logs/hyperf.log` (não no stdout — a armadilha já documentada) e publicado em
    `chat.queue.websocket.dlq`. Publiquei `notifications.websocket` com `notification_id`
    como array (viola o tipo `int` de `NotificationRepository::findById`) → mesmo
    resultado em `notifications.queue.websocket.dlq`.
  - Reenviei uma `chat.messages` bem formada depois: processou normal (log de SQL comum,
    sem erro), confirmando que o caminho feliz não regrediu. Limpei as duas DLQs de teste
    (`rabbitmqctl purge_queue`) ao final.
