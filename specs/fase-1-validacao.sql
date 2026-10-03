-- Validação das migrations da Fase 1 (PostgreSQL 18). Só dados sintéticos (dataset-de-paridade.md).
-- Uso: psql -v ON_ERROR_STOP=0 -f specs/fase-1-validacao.sql  (banco com as 7 migrations aplicadas)
\set ON_ERROR_STOP off
\pset pager off
-- ids são UUIDv7 (default uuidv7() do PG18); o dataset usa ids fixos pg_temp.u(n) só para os casos ficarem legíveis.
create function pg_temp.u(int) returns uuid language sql immutable as $$ select ('00000000-0000-7000-8000-' || lpad($1::text, 12, '0'))::uuid $$;
\echo '== 1. Dataset de paridade (35 devs, 3 canais, 3 tags, 55 vídeos, 2+2 reações)'
truncate devs, channels, tags cascade;
insert into devs (id, username, name, bio, avatar, created_at, updated_at)
select pg_temp.u(n), format('dev%s', lpad(n::text, 2, '0')), format('Dev %s', lpad(n::text, 2, '0')),
       case when n % 3 = 0 then null else format('Bio sintética do dev %s.', lpad(n::text, 2, '0')) end,
       format('https://example.test/avatar/dev%s.png', lpad(n::text, 2, '0')),
       timestamptz '2026-01-01 00:00:00+00' + n * interval '1 minute',
       timestamptz '2026-01-01 00:00:00+00' + n * interval '1 minute'
from generate_series(1, 35) n;
insert into channels (id, name, link, category, created_at, updated_at) values
 (pg_temp.u(1), 'Canal Alpha', 'https://youtube.com/alpha', 'Tecnologia', '2026-01-01 00:00:00+00', '2026-01-01 00:00:00+00'),
 (pg_temp.u(2), 'Canal Beta',  'https://youtube.com/beta',  'Educação',   '2026-01-01 00:00:01+00', '2026-01-01 00:00:01+00'),
 (pg_temp.u(3), 'Canal Zeta',  'https://youtube.com/zeta',  'Testes',     '2026-01-01 00:00:02+00', '2026-01-01 00:00:02+00');
insert into tags (id, name) values (pg_temp.u(1), 'javascript'), (pg_temp.u(2), 'testes'), (pg_temp.u(3), 'react');
insert into channel_tag values (pg_temp.u(1), pg_temp.u(1)), (pg_temp.u(1), pg_temp.u(2)), (pg_temp.u(2), pg_temp.u(3));
insert into videos (youtube_id, title, url, channel_id, thumbnail, created_at, updated_at)
select id, title, 'https://www.youtube.com/watch?v=' || id, pg_temp.u(ch), 'https://i.ytimg.com/vi/' || id || '/hqdefault.jpg',
       timestamptz '2026-02-01 00:00:00+00' + (row_number() over (order by ch, n)) * interval '1 minute',
       timestamptz '2026-02-01 00:00:00+00' + (row_number() over (order by ch, n)) * interval '1 minute'
from (select 1 ch, n, format('vidalpha%s', lpad(n::text, 2, '0')) id, format('Vídeo Alpha %s', lpad(n::text, 2, '0')) title from generate_series(1, 20) n
      union all
      select 2, n, format('vidbeta%s', lpad(n::text, 2, '0')), format('Vídeo Beta %s', lpad(n::text, 2, '0')) from generate_series(1, 35) n) s;
insert into dev_reactions (dev_id, target_dev_id, type) values (pg_temp.u(1), pg_temp.u(2), 'like'), (pg_temp.u(1), pg_temp.u(3), 'dislike');
insert into channel_reactions (dev_id, channel_id, type) values (pg_temp.u(1), pg_temp.u(1), 'follow'), (pg_temp.u(1), pg_temp.u(2), 'ignore');
select (select count(*) from devs) devs, (select count(*) from channels) canais, (select count(*) from tags) tags,
       (select count(*) from videos) videos, (select count(*) from dev_reactions) rd, (select count(*) from channel_reactions) rc;

\echo '== 2. Constraints (cada linha abaixo DEVE falhar, exceto as marcadas OK)'
insert into dev_reactions (dev_id, target_dev_id, type) values (pg_temp.u(5), pg_temp.u(5), 'like');                -- auto-like
insert into dev_reactions (dev_id, target_dev_id, type) values (pg_temp.u(5), pg_temp.u(6), 'amei');                -- type inválido
insert into dev_reactions (dev_id, target_dev_id, type) values (pg_temp.u(1), pg_temp.u(2), 'dislike');             -- OK: like e dislike independentes
insert into dev_reactions (dev_id, target_dev_id, type) values (pg_temp.u(1), pg_temp.u(2), 'like') on conflict do nothing; -- OK: idempotente (0 linhas)
insert into dev_reactions (dev_id, target_dev_id, type) values (pg_temp.u(1), pg_temp.u(999), 'like');              -- FK
insert into channel_reactions (dev_id, channel_id, type) values (pg_temp.u(1), pg_temp.u(1), 'curtir');             -- type inválido
insert into devs (username, name, avatar) values ('DEV01', 'x', 'y');                         -- username duplicado (caixa)
insert into channels (name, link, category) values ('canal alpha', 'https://x.test/1', 'x');  -- name duplicado (caixa)
insert into channels (name, link, category) values ('Canal Álpha', 'https://x.test/acento', 'x');  -- name duplicado (acento)
insert into channels (name, link, category) values ('Outro', 'HTTPS://YOUTUBE.COM/ALPHA', 'x'); -- link duplicado (caixa)
insert into videos (youtube_id, title, url, channel_id, thumbnail) values ('vidalpha01', 't', 'https://x.test/v', pg_temp.u(1), 't'); -- youtube_id duplicado
insert into videos (youtube_id, title, url, channel_id, thumbnail) values ('novo1', 't', 'https://www.youtube.com/watch?v=vidalpha01', pg_temp.u(1), 't'); -- url duplicada
insert into videos (youtube_id, title, url, channel_id, thumbnail) values ('novo2', 't', 'https://x.test/v2', pg_temp.u(999), 't'); -- FK
delete from channels where id = pg_temp.u(1);                                                            -- RESTRICT (tem vídeos)
begin; delete from devs where id = pg_temp.u(1); select count(*) as reacoes_de_dev1_apos_cascade from dev_reactions where dev_id = pg_temp.u(1); rollback; -- CASCADE

\echo '-- Soft delete: apagado libera a chave única; ativo continua protegido'
begin;
update devs set deleted_at = now() where username = 'dev02';
insert into devs (username, name, avatar) values ('Dev02', 'recriado', 'y');                  -- OK: o apagado não ocupa o índice
savepoint sp;
insert into devs (username, name, avatar) values ('dev02', 'duplicado', 'y');                 -- falha: o recriado está ativo
rollback to sp;
select count(*) as dev02_ativos from devs where norm_text(username) = 'dev02' and deleted_at is null; -- 1
update channels set deleted_at = now() where id = pg_temp.u(3);
insert into channels (name, link, category) values ('canal zeta', 'https://youtube.com/zeta', 'x'); -- OK: libera nome e link
update videos set deleted_at = now() where youtube_id = 'vidalpha01';
insert into videos (youtube_id, title, url, channel_id, thumbnail) values ('vidalpha01', 'recriado', 'https://www.youtube.com/watch?v=vidalpha01', pg_temp.u(1), 't'); -- OK
select count(*) as videos_ativos_alpha01 from videos where youtube_id = 'vidalpha01' and deleted_at is null; -- 1
rollback;

\echo '-- Acento e caixa na busca por nome (norm_text)'
select name as achado from channels where norm_text(name) = norm_text('CANAL ÁLPHA');   -- Canal Alpha
select count(*) as educacao_sem_acento from channels where norm_text(category) = norm_text('educacao'); -- 1

\echo '== 3. Planos das consultas do contrato (dataset de paridade)'
\echo '-- GET /devs (anônimo), página 1'
explain (costs off) select * from devs where deleted_at is null order by created_at desc, id desc limit 30 offset 0;
\echo '-- GET /devs (autenticado como dev01): exclui o próprio e quem recebeu like/dislike'
explain (costs off) select * from devs where deleted_at is null and id <> pg_temp.u(1) and id not in (select target_dev_id from dev_reactions where dev_id = pg_temp.u(1) and type in ('like', 'dislike')) order by created_at desc, id desc limit 30;
\echo '-- GET /devs/{username}'
explain (costs off) select * from devs where norm_text(username) = norm_text('DEV01') and deleted_at is null;
\echo '-- GET /feed/trending'
explain (costs off) select v.*, c.name, c.link from videos v join channels c on c.id = v.channel_id where v.deleted_at is null order by v.created_at desc, v.id desc limit 30;
\echo '-- GET /feed/channel'
explain (costs off) select v.* from videos v where v.channel_id = pg_temp.u(2) and v.deleted_at is null order by v.created_at desc, v.id desc limit 30;
\echo '-- GET /feed/subscriptions (dev01)'
explain (costs off) select v.* from videos v join channel_reactions r on r.channel_id = v.channel_id and r.dev_id = pg_temp.u(1) and r.type = 'follow' where v.deleted_at is null order by v.created_at desc, v.id desc limit 30;
\echo '-- GET /video/{id}'
explain (costs off) select * from videos where youtube_id = 'vidalpha01' and deleted_at is null;
\echo '-- GET /channels/{q}'
explain (costs off) select * from channels where deleted_at is null and (norm_text(name) = norm_text('Canal Alpha') or norm_text(link) = norm_text('Canal Alpha') or norm_text(alternative_link) = norm_text('Canal Alpha'));
\echo '-- GET /likes/devs'
explain (costs off) select d.* from dev_reactions r join devs d on d.id = r.target_dev_id where r.dev_id = pg_temp.u(1) and r.type = 'like';

\echo '== 4. Busca: ILIKE com escape de curingas'
select count(*) as acha_underscore_literal from videos where norm_text(title) like '%\_%' escape '\';   -- 0: "_" literal, não curinga
select count(*) as sem_escape_underscore from videos where norm_text(title) like '%_%';                  -- 55: "_" curinga (o que NÃO queremos)
select count(*) as acha_beta_05 from videos where norm_text(title) like norm_text('%VÍDEO beta 05%') escape '\';  -- sem caixa nem acento

\echo '== 5. Escala: ~100x o v1 (4.000 devs, 2.000 canais, 50.000 vídeos), sintético'
truncate devs, channels, tags cascade;
insert into devs (id, username, name, avatar, created_at, updated_at)
select pg_temp.u(n), 'dev' || n, 'Dev ' || n, 'https://example.test/a/' || n, timestamptz '2026-01-01+00' + n * interval '1 minute', timestamptz '2026-01-01+00' + n * interval '1 minute' from generate_series(1, 4000) n;
insert into channels (id, name, link, category, created_at, updated_at)
select pg_temp.u(n), 'Canal ' || n || ' ' || md5(n::text), 'https://youtube.com/c' || n, 'Cat ' || (n % 20), now(), now() from generate_series(1, 2000) n;
insert into videos (youtube_id, title, url, channel_id, thumbnail, created_at, updated_at)
select 'v' || n, 'Vídeo ' || n || ' ' || md5((n * 7)::text), 'https://www.youtube.com/watch?v=v' || n, pg_temp.u(1 + (n % 2000)), 't',
       timestamptz '2026-02-01+00' + n * interval '1 minute', timestamptz '2026-02-01+00' + n * interval '1 minute' from generate_series(1, 50000) n;
insert into dev_reactions (dev_id, target_dev_id, type) select pg_temp.u(1), pg_temp.u(n), 'like' from generate_series(2, 200) n;
insert into channel_reactions (dev_id, channel_id, type) select pg_temp.u(1), pg_temp.u(n), 'follow' from generate_series(1, 50) n;
-- VACUUM (não só ANALYZE): esvazia a lista pendente do GIN após a carga; sem isso o planner a custeia e escolhe varredura (150 ms).
vacuum analyze;
\echo '-- GET /devs página 134 (offset 3990)'
explain (analyze, costs off, timing off, summary on) select * from devs where deleted_at is null order by created_at desc, id desc limit 30 offset 3990;
\echo '-- GET /feed/trending página 1'
explain (analyze, costs off, timing off, summary on) select v.*, c.name, c.link from videos v join channels c on c.id = v.channel_id where v.deleted_at is null order by v.created_at desc, v.id desc limit 30;
\echo '-- GET /feed/channel'
explain (analyze, costs off, timing off, summary on) select v.* from videos v where v.channel_id = pg_temp.u(7) and v.deleted_at is null order by v.created_at desc, v.id desc limit 30;
\echo '-- GET /feed/subscriptions (dev01 segue 50 canais)'
explain (analyze, costs off, timing off, summary on) select v.* from videos v join channel_reactions r on r.channel_id = v.channel_id and r.dev_id = pg_temp.u(1) and r.type = 'follow' where v.deleted_at is null order by v.created_at desc, v.id desc limit 30;
\echo '-- GET /channels/{q}'
explain (analyze, costs off, timing off, summary on) select * from channels where deleted_at is null and (norm_text(name) = norm_text('Canal 7') or norm_text(link) = norm_text('Canal 7') or norm_text(alternative_link) = norm_text('Canal 7'));
\echo '-- Busca com GIN trigram sobre norm_text() (P-5), termo de 4 caracteres'
explain (analyze, costs off, timing off, summary on) select youtube_id, title from videos where deleted_at is null and norm_text(title) like '%a1b2%' escape '\' limit 30;
explain (analyze, costs off, timing off, summary on) select name from channels where deleted_at is null and (norm_text(name) like '%a1b2%' escape '\' or norm_text(link) like '%a1b2%' escape '\') limit 30;
\echo '-- Termo de 2 caracteres (sem trigram utilizável): varredura'
explain (analyze, costs off, timing off, summary on) select youtube_id, title from videos where deleted_at is null and norm_text(title) like '%a1%' escape '\' limit 30;
