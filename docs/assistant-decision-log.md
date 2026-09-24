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
