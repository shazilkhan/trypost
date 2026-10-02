# Deploy da branch `codex/independent-social-posts`

Roteiro completo para levar esta branch à produção, na ordem. Todos os comandos, variáveis e
agendamentos citados aqui já existem na branch, incluindo os da importação de
posts externos.

O roteiro detalhado da mídia (snapshot do bucket, Google Drive/Photos, Canva, HEIC,
testes manuais e comunicação) continua em
[`MEDIA_INTEGRATIONS_SETUP.md` §7](MEDIA_INTEGRATIONS_SETUP.md). Este arquivo
referencia aquela seção em vez de repeti-la.

---

## 1. Antes do deploy

1. **Snapshot do storage (R2/S3)** ou versionamento ligado. O `media:adopt-library`
   apaga arquivos que só existiam na biblioteca de mídia. Detalhes em
   `MEDIA_INTEGRATIONS_SETUP.md` §7.1.
2. **Ensaio numa cópia da produção:**
   ```bash
   php artisan media:adopt-library --dry-run
   php artisan posts:split-legacy-active   # na cópia, para ver a saída
   ```
3. **Imagem Docker** reconstruída com o `docker/Dockerfile` novo (Imagick + HEIC).
4. **Variáveis de ambiente** (seção 2).

## 2. Variáveis de ambiente

As da mídia estão em `MEDIA_INTEGRATIONS_SETUP.md` §7.2. Novas desta branch:

| Variável | Padrão | Para quê | Status |
| --- | --- | --- | --- |
| `TELEGRAM_DEEP_LINK` | `https://t.me` | Link do bot no fluxo de conectar o Telegram | já existe |
| `EXTERNAL_POSTS_IMPORT_LIMIT` | `50` | Quantos posts mais recentes por canal viram post em Enviados (`0` desliga a importação) | já existe |
| `X_EXTERNAL_POSTS_IMPORT_DAYS` | `30` | No X, importar só os posts dos últimos N dias | já existe |
| `EXTERNAL_POSTS_MATCH_WINDOW_MINUTES` | `120` | Minutos em torno de um post da rede em que um post do TryPost com o mesmo texto é tratado como esse post, em vez de importar uma cópia | nova |
| `EXTERNAL_POSTS_MEDIA_REQUESTS_PER_MINUTE` | `30` | Chamadas por minuto, por rede, para buscar as mídias dos posts importados (orçamento separado do Analytics) | nova |
| `PUBLICATION_DISCOVERY_INTERVAL_HOURS` | `3` | Intervalo da busca de posts novos nas redes | já existe |
| `X_PUBLICATION_DISCOVERY_INTERVAL_HOURS` | `24` | Mesmo intervalo, só para o X (cobra por leitura) | já existe |
| `PUBLICATION_DISCOVERY_OVERLAP_HOURS` | `6` | Margem para trás em cada busca, para não perder post atrasado | já existe |

Depois de mudar variáveis:

```bash
php artisan config:clear
```

## 3. No deploy, na ordem

```bash
# 1. Esquema: grupos de posts, recorrência, origem do post, etc.
php artisan migrate --force

# 2. Biblioteca de mídia antiga → mídia por post (só neste release)
php artisan media:adopt-library --force

# 3. Divide posts antigos com várias redes em um post por rede,
#    ligando todos pelo mesmo post_group_id. Seguro de rodar de novo.
php artisan posts:split-legacy-active

# 4. Apaga os posts que ficaram sem canal por desconexões antigas (agora
#    desconectar apaga os posts do canal). Seguro de rodar de novo.
php artisan posts:purge-orphaned
```

Migrations que entram nesta branch (entre outras):

- `add_post_group_id_to_posts_table`: posts criados juntos compartilham um grupo.
- `add_recurrence_to_posts_table` e `add_recurrence_origin_at_to_posts_table`: posts recorrentes.
- `add_origin_to_posts_table`, `change_platform_url_to_text_on_post_platforms_table` e `add_post_dismissed_at_to_analytics_publications_table`: origem do post (`trypost` | `network`), permalinks longos e posts importados apagados pelo usuário (não voltam).
- `add_permissions_to_user_workspace_and_invites_tables` → `drop_role_from_user_workspace_and_invites_tables`:
  os papéis Admin / Member / Viewer viram duas flags por membro e por convite
  (`is_admin`, `requires_approval`). Admin → admin; Member → publica direto;
  Viewer → precisa de aprovação; o dono da conta vira admin. Rodam juntas, nessa
  ordem; a segunda apaga a coluna `role`.
- `add_approval_columns_to_posts_table`: `approval_requested_at`, `approved_by`,
  `approved_at`, `approval_queue_position` e o novo status `pending_approval`.
- `add_approval_requested_by_to_posts_table`: `approval_requested_by`, quem pediu
  a aprovação (pode não ser o autor: um membro que edita um post já aprovado de
  outra pessoa). Vira `null` se o usuário for apagado; aí vale o autor do post.
- `add_collaboration_to_notification_preferences_table`: preferência
  "Colaboração" (ligada por padrão).
- `flatten_replies_and_drop_reactions_from_post_notes_table`: notas ficam
  simples (sem respostas nem reações). Respostas viram notas normais do mesmo
  post, na ordem em que foram criadas; as colunas `parent_id` e `reactions` são
  apagadas (reações somem).
- `make_time_format_required_on_users_table`: `users.time_format` passa a ser
  obrigatório (12h ou 24h; acaba o "seguir o idioma"). Quem estava sem formato
  recebe o que já via: `12h` em inglês, `24h` nos outros idiomas. Ninguém percebe
  mudança.

Sem migração de dados para a fila: posts já na fila mantêm os horários que têm
(a fila não é mais compactada nos primeiros horários livres), então nada muda
para quem já tem posts enfileirados.

## 4. Filas e scheduler

Os workers/Horizon precisam consumir todas estas filas (`config/horizon.php`):

| Fila | Para quê |
| --- | --- |
| `default`, `posthog`, `broadcasts` | App em geral |
| `webhooks` | Webhooks de saída |
| `analytics` | Analytics e, com a importação, a criação dos posts externos |
| `media-imports` | Google Drive, Photos, Canva e, com a importação, as mídias dos posts externos |
| `rss-feeds` | Feeds RSS |

Com `queue:work` em vez de Horizon:

```bash
php artisan queue:work --queue=default,posthog,broadcasts,webhooks,analytics,media-imports,rss-feeds
```

Agendamentos (o scheduler já cuida, só confirme que ele está rodando):

| Quando (UTC) | O quê | Status |
| --- | --- | --- |
| a cada 3h (X: a cada 24h, às 00:00) | Busca posts novos nas redes: importa para Enviados e Analytics | já existe |
| 02:00 | Snapshot de seguidores | já existe |
| 03:00 | Atualização das métricas dos últimos 30 dias (X: 20) | já existe |
| 23:30 / 00:30 | Fechamento do snapshot do dia / recuperação do dia anterior | já existe |
| de hora em hora | `media:prune-uploads` | já existe |
| diário | `posts:prune-history` | já existe |
| a cada 5 min | `rss-feeds:poll`, `repurposes:poll` | já existe |

## 5. Backfill do Analytics + importação dos posts externos

Roda **depois** dos passos da seção 3. A ordem importa: o comando primeiro liga os
posts que o TryPost já publicou às publicações do Analytics e só então importa os
posts feitos direto nas redes. Ao contrário, um post do TryPost viraria um
"importado" duplicado.

Comandos (já existem):

```bash
# Todos os workspaces assinantes
php artisan analytics:backfill-existing

# Um workspace só (bom para testar primeiro)
php artisan analytics:backfill-existing --workspace=<uuid>

# Incluir workspaces sem assinatura reconhecida pelo Cashier
php artisan analytics:backfill-existing --include-unsubscribed
```

Opções para rodar em lotes e controlar custo. `--chunk` conta **contas** por lote
e `--delay` atrasa os jobs de cada lote em segundos; o comando despacha tudo e
termina, não fica parado esperando:

```bash
# 50 contas por lote, um lote a cada 5 minutos
php artisan analytics:backfill-existing --chunk=50 --delay=300

# Só algumas redes (ex.: deixar o X, que cobra por leitura, para depois)
php artisan analytics:backfill-existing --platforms=instagram,instagram-facebook,facebook,threads
php artisan analytics:backfill-existing --platforms=x --chunk=10 --delay=600
```

O que acontece por conta:

1. O Analytics busca o histórico de **365 dias** (no X, no máximo 3.200 posts).
2. Os **50 posts mais recentes** de cada canal (no X, os dos **últimos 30 dias**)
   viram post em Enviados e no calendário, com origem `network`.
3. As mídias completas desses posts (carrossel, vídeo, capa) são baixadas para o
   bucket na fila `media-imports`.
4. As métricas são buscadas pelo Analytics.
5. No Instagram, os **stories** entram também, mas só os que estão no ar (últimas
   24 horas): a API não devolve stories mais antigos. Eles têm um limite próprio
   de 50, separado de feed e reels.

Stories não precisam de comando próprio. Para contas que já tinham feito o backfill
antes deste release, a busca de posts novos (a cada 3h) passa a ler os stories e
traz os que estiverem no ar. Para trazer na hora, sem esperar a próxima busca:

```bash
php artisan analytics:dispatch-publication-discovery --platform=instagram --platform=instagram-facebook
```

Rodar de novo é seguro: nenhum post é criado em dobro, e mídias já baixadas não são
baixadas outra vez.

Acompanhe pelo Horizon (filas `analytics` e `media-imports`) e pelo resumo que o
comando imprime, uma linha por contador:

- `accounts_dispatched`: contas que tiveram o backfill despachado.
- `publications_to_link`: publicações do TryPost sem registro no Analytics, que o comando liga a ele.
- `posts_imported`: posts importados que existem agora. É a contagem atual; a
  importação é assíncrona, então o número cresce enquanto as filas drenam.
- `historical_identity_unrecoverable`: publicações antigas sem canal associado, cuja
  identidade não dá para recuperar e que por isso ficam fora do Analytics.

## 6. Depois do deploy

```bash
# Auditoria da mídia (só leitura; todas as checagens devem dar 0)
php artisan media:audit
```

- Acompanhe os jobs `AdoptWorkspaceLibraryJob` até zerarem
  (`MEDIA_INTEGRATIONS_SETUP.md` §7.4).
- Confira em alguns canais que Enviados e o calendário mostram os posts importados,
  com mídia e métricas.

### Canais desconectados

- Rodar `php artisan posts:purge-orphaned` de novo deve imprimir `0 orphaned post(s) deleted, 0 orphaned target(s) removed from posts on other channels.`
- Desconectar um canal apaga todos os posts dele, sem disparar o webhook `post.deleted`
  para cada post; reconectar a mesma conta importa de novo os posts recentes.

### Aprovação de posts

- Em Configurações → Membros, confira que quem era Viewer aparece como
  "Precisa de aprovação" e quem era Admin aparece como Admin.
- Com um membro que precisa de aprovação: crie um post na fila e confira que ele
  aparece na aba Aprovações (e não na Fila), que o e-mail chega para os
  aprovadores e que "Adicionar à fila" coloca o post no próximo horário.
- `posts:process-scheduled` nunca publica `pending_approval`; nada a configurar.
- Em uma automação de repurpose criada por um membro que precisa de aprovação, o
  item continua marcado como Publicado enquanto os posts dele aguardam aprovação:
  o item representa o envio à fila, não a publicação nas redes.

## 7. Se precisar voltar atrás

Apagar só os posts importados (origem `network`) e as mídias deles. O Analytics fica
intacto: as publicações apenas voltam a ficar sem ligação.

Antes, defina `EXTERNAL_POSTS_IMPORT_LIMIT=0` e rode `php artisan config:clear`;
sem isso a próxima busca importa os posts de novo.

```bash
php artisan posts:purge-imported            # já existe
php artisan posts:purge-imported --workspace=<uuid>
```

### Aprovação de posts: papéis

O `migrate:rollback` das migrations de papéis recria a coluna `role` em
`user_workspace` e em `invites` e preenche pelas flags: `is_admin` →
Admin; `requires_approval` → Viewer; os demais → Member. O dono da conta volta
como Admin. Posts em `pending_approval` não existem no esquema antigo: antes de
voltar atrás, aprove ou rejeite todos (ou mova-os para rascunho), senão ficam com
um status desconhecido.

As outras migrations da aprovação também voltam atrás sem perda fora delas:

- `add_approval_requested_by_to_posts_table`: o `down()` apaga a chave
  estrangeira e a coluna `approval_requested_by`.
- `add_approval_columns_to_posts_table`: o `down()` apaga `approved_by` (com a
  chave estrangeira), `approval_requested_at`, `approved_at` e
  `approval_queue_position`. Quem aprovou e quando se perde.
- `add_collaboration_to_notification_preferences_table`: o `down()` apaga a
  preferência `collaboration`; os e-mails de aprovação deixam de existir junto com
  o código.

## 8. Teste manual antes do release

- **X:** uma sincronização real com uma conta de verdade, conferindo os campos
  `note_tweet` (texto longo) e as variantes de mídia.
- **Threads:** um carrossel, conferindo a expansão de `children{}`.
- **Pinterest:** um pin, conferindo a imagem 1200x e o `video_url`.

## 9. Comunicação

Changelog, docs.trypost.it, instruções do MCP e e-mail do release:
`MEDIA_INTEGRATIONS_SETUP.md` §7.6. Somar a isso:

- Enviados e o calendário passam a mostrar também os posts feitos direto nas redes
  (últimos 50 por canal; no X, último mês).
- A API e o MCP ganham o campo `origin` (`trypost` | `network`) nos posts.

Aprovação de posts (mudança que quebra compatibilidade para quem lê o status):

- Novo status `pending_approval` em posts na API e no MCP. Posts criados ou
  editados por um membro que precisa de aprovação terminam nesse status em vez de
  `scheduled` / `publishing`.
- Novos endpoints `POST /api/posts/{post}/approve` (aceita `scheduled_at` ou
  `publish_now`) e `POST /api/posts/{post}/reject`; novas tools MCP
  `approve-post-tool` e `reject-post-tool`; `list-posts-tool` filtra
  `pending_approval`.
- Os papéis Admin / Member / Viewer deixam de existir: a API e o MCP não expõem
  membros nem convites, então só a documentação e o changelog mudam.
- Atualizar docs.trypost.it (membros e aprovações, API de posts) e o changelog.
