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
