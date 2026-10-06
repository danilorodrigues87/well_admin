# Evidências de coleta (fotos e assinatura)

## Onde ficam os arquivos

| Ambiente | Caminho no disco | URL no admin |
|----------|------------------|--------------|
| Local (XAMPP) | `{projeto}/storage/coletas/{id}/…` | `/storage/coletas/{id}/{arquivo}` |
| Produção (EasyPanel) | Volume bindado em `/var/www/html/storage` (ou `STORAGE_PATH`) | Idem |

Arquivos típicos:

- `evidencia_1.webp` … `evidencia_3.webp` (ou `.jpg`/`.png` se GD sem WebP)
- `assinatura_cliente.png` / `.jpg`

A pasta `storage/` **não vai para o Git**. Código e mídia sobem separadamente.

Metadados ficam em `coleta_evidencias` / `coletas.assinatura_cliente_path` no banco `well_admin`.

Resolução de caminho: `App\Common\StoragePath` (env opcional `STORAGE_PATH`).

---

## EasyPanel / Docker — volume persistente

Mesmo padrão do Inter (`docs/INTER.md`): bind mount do host → container.

| Host (exemplo) | Container |
|----------------|-----------|
| `/etc/easypanel/projects/.../well/storage` | `/var/www/html/storage` |

Estrutura no volume:

```
storage/
  coletas/{id}/evidencia_*.webp
  coletas/{id}/assinatura_cliente.png
  inter/certificado.crt
  inter/chave.key
  boletos/
```

**Erro comum:** gravar em pasta recriada no deploy (sem montagem) ou montar só `storage/inter` e esquecer `coletas/`. O PHP grava em `StoragePath::root()/coletas/…`.

Diagnóstico no terminal do serviço:

```bash
php database/scripts/storage_diagnose.php
```

Confirma: pasta existe, é gravável por `www-data`, e se GD tem WebP.

Opcional no `.env` do EasyPanel (só se o volume **não** estiver em `/var/www/html/storage`):

```env
STORAGE_PATH=/caminho/absoluto/do/volume
```

Após alterar Dockerfile (libwebp), faça **rebuild** da imagem — não só redeploy do código.

---

## Coletas novas (wizard)

1. Coletor envia até 3 fotos (JPG/PNG/WebP, máx. 5 MB).
2. `EvidenceStorageService` valida MIME, redimensiona se > 1920 px e grava em **WebP** quando GD+`imagewebp` estão disponíveis; senão mantém o formato original.
3. Assinatura (canvas) grava PNG/JPG no mesmo diretório da coleta.

---

## Legado (~1.270 URLs Cloudinary)

Fluxo ETL: ver histórico em `database/scripts/etl_download_evidencias.php`. Após o cutover, novas coletas gravam só no volume local.

### O que NÃO fazer

- Não commitar fotos no repositório.
- Não depender do Cloudinary após o cutover.
- Não misturar IDs: cada arquivo fica na pasta do `coleta_id` do banco atual.

---

## Produção contínua

Backup periódico de `storage/` (coletas + inter + boletos) + banco.
