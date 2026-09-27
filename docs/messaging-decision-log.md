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
