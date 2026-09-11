# Evidências de coleta (fotos)

## Onde ficam os arquivos

| Ambiente | Caminho no disco | URL no admin |
|----------|------------------|--------------|
| Local (XAMPP) | `storage/coletas/{coleta_id}/evidencia_{1-3}.webp` | `/storage/coletas/{id}/{arquivo}` |
| Produção Linux | Mesmo layout relativo à raiz do projeto | Idem |

A pasta `storage/coletas/` **não vai para o Git** (`.gitignore`). Código e mídia sobem separadamente.

Metadados (ordem, caminho, MIME) ficam em `coleta_evidencias` no banco `well_admin`.

---

## Coletas novas (a partir da Etapa 4)

1. Coletor envia até 3 fotos no wizard (JPG, PNG ou WebP, máx. 5 MB cada).
2. `EvidenceStorageService` valida MIME real (`finfo`), redimensiona se > 1920 px de largura e grava em **WebP** (qualidade 85).
3. Requer extensão **GD** com suporte WebP no PHP (`extension=gd` no `php.ini` do XAMPP).

---

## Legado (~1.270 URLs Cloudinary)

No banco `well_antigo`, as colunas `img1`, `img2`, `img3` da tabela `coletas` apontam para URLs públicas do Cloudinary.

### Fluxo recomendado (Etapa 5 — ETL histórico)

```
well_antigo (URLs Cloudinary)
        │
        ▼  script etl_download_evidencias.php (local)
storage/coletas/{coleta_id}/evidencia_N.webp
        │
        ▼  INSERT coleta_evidencias (well_admin)
        │
        ▼  deploy produção: rsync/scp da pasta storage/
Servidor online serve /storage/coletas/...
```

**Passo a passo:**

1. **No XAMPP local** — importar coletas históricas para `well_admin` (migration/script ETL de coletas, Etapa 5).
2. **Baixar imagens** — rodar `php database/scripts/etl_download_evidencias.php`:
   - Lê URLs do legado (ou de colunas temporárias no ETL).
   - Baixa via HTTPS (`EvidenceStorageService::downloadAndSave`).
   - Converte para WebP e grava em `storage/coletas/{id}/`.
   - Registra linhas em `coleta_evidencias`.
3. **Validar localmente** — abrir listagem/detalhe de coletas e conferir thumbnails.
4. **Subir para produção** — duas entregas:
   - **Código:** Git deploy habitual (sem `storage/`).
   - **Mídia:** copiar `storage/coletas/` inteira para o servidor (rsync, SFTP, ou tarball). Exemplo:
     ```bash
     rsync -avz storage/coletas/ user@servidor:/var/www/admin.well.eco/storage/coletas/
     ```
5. **Permissões no Linux:** pasta gravável pelo usuário do PHP (`www-data`), ex.: `chmod 755 storage/coletas`.

### O que NÃO fazer

- Não commitar fotos no repositório.
- Não depender do Cloudinary após o cutover — URLs legadas são só fonte de migração.
- Não misturar IDs: cada arquivo fica na pasta do `coleta_id` do **novo** banco.

---

## Produção contínua (pós-cutover)

Novas coletas gravam direto em `storage/coletas/` no servidor de produção. Backup periódico dessa pasta + banco.

Opcional futuro: S3 ou CDN — manter a mesma interface `EvidenceStorageService` e trocar só o backend de gravação.
