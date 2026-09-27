# Roteiro de teste manual — retry e DLQ na mensageria

**Só em desenvolvimento.** Precisa da stack de pé (`docker compose up -d`) e, para os
passos 1–3, do serviço `email-consumer` já reiniciado com o código atual (mudança de
código em consumidor de longa duração só é lida depois de `docker compose restart
email-consumer` / `assistant-consumer` / `hyperf`).

## 1. Retry real: Mailpit fora do ar

```bash
docker compose stop mailpit
```

Dispare um convite (`POST /api/invite` com um e-mail qualquer, ou pelo Tinker via
`InviteService::createForUserId`). Em segundos:

```bash
docker compose logs --tail 5 email-consumer
# Retrying invites.email on retry.1 (attempt 1): Connection could not be established…
```

Religue o Mailpit dentro de ~30s (antes do patamar 2 expirar):

```bash
docker compose start mailpit
```

Em até ~35s o log mostra `Handled invites.email` e o e-mail aparece em
`http://localhost:8025`. Confira `invites.email_sent_at` carimbado.

## 2. Esgotamento: cai na DLQ

Repita o passo 1, mas **não** religue o Mailpit. Depois de 5s + 30s + 5min (~5min35s), o
log mostra `Dead-lettered invites.email after 4 attempts: …` e:

```bash
docker compose exec laravel php artisan customs:dlq:check emails   # exit 1, "has 1 message(s) waiting"
docker compose exec laravel php artisan customs:dlq:list emails    # mostra o envelope
```

Religue o Mailpit e reprocesse:

```bash
docker compose exec laravel php artisan customs:dlq:replay emails
docker compose exec laravel php artisan customs:dlq:check emails   # exit 0, vazia
```

## 3. Payload inválido: DLQ sem gastar patamar

Publique um corpo que não é JSON direto na fila (via Tinker, canal cru — não há endpoint
para isso, é deliberado). O log mostra `Dead-lettered invites.email after 1 attempts`
imediatamente, sem passar por nenhum `retry.*`. `dlq:list emails` mostra `payload.raw_body`
em vez de um payload reconhecível.

## 4. Notificação parcial: só quem falhou é reenviado

Crie uma notificação para 2+ destinatários com o Mailpit fora do ar só durante a 1ª
tentativa do 2º em diante (mais fácil de forçar em teste automatizado — ver
`ConsumeEmailQueueTest::test_a_partial_notification_failure_only_resends_to_the_recipient_still_pending`).
Manualmente: confira `user_notifications.emailed_at` por destinatário depois de cada
tentativa — quem já tem a marca não aparece de novo no `Mail::to()` do consumidor
(acompanhe pelo log `Notification sent to …`).

## 5. Hyperf: DLQ sem escada

Sem endpoint para forjar isso pela API (é deliberado — nenhuma rota publica um payload
malformado). Publicação direta via Tinker, canal cru:

```php
app(App\Services\RabbitMQPublisher::class)->publish('chat.messages', [
    'message_id' => <id de uma mensagem real>,
    'recipient_ids' => 'not-an-array', // viola o tipo array de sendToUserIds()
]);
```

```bash
docker compose exec hyperf tail -5 runtime/logs/hyperf.log   # NÃO no stdout
docker compose exec rabbitmq rabbitmqctl list_queues name messages | grep chat.queue.websocket.dlq
```

Mostra `Chat message consumer failed` e 1 mensagem na `.dlq`. Sem `customs:dlq:*` do lado
Hyperf — inspecione com `rabbitmqctl` ou o painel (`:15672`) e limpe com
`rabbitmqctl purge_queue chat.queue.websocket.dlq` se precisar.

## Restaurar

Nenhum destes passos toca dado de seed. Se sobrar algo nas filas de teste:

```bash
docker compose exec rabbitmq rabbitmqctl list_queues name messages
docker compose exec rabbitmq rabbitmqctl purge_queue <fila>   # esvazia sem apagar a fila
```
