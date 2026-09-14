# Identidade visual — Well Coletas / Well Eco Admin

Paleta compartilhada entre o app **FlutterFlow (Well Coletas)** e o **painel web admin**.

Implementação web: [`resources/css/panel-theme.css`](../resources/css/panel-theme.css) (CSS variables Bootstrap 5).

## Tokens

| Token | Light | Dark | Uso |
|-------|-------|------|-----|
| Primary | `#2E7D32` | `#66BB6A` | Botões, links, AppBar |
| Secondary | `#1B5E20` | `#388E3C` | Navbar, sidebar, apoio |
| Tertiary | `#00897B` | `#4DB6AC` | Tags, destaques teal |
| Alternate | `#E8F5E9` | `#263328` | Seleções, banners suaves |
| Primary Background | `#F8FAF8` | `#121814` | Fundo da aplicação |
| Secondary Background | `#FFFFFF` | `#1E2620` | Cards, modais, inputs |
| Primary Text | `#1A241B` | `#E8F5E9` | Títulos e texto principal |
| Secondary Text | `#5F6D61` | `#A3B8A5` | Labels e subtítulos |
| Success | `#00875A` | `#57D9A3` | Sucesso (esmeralda) |
| Error | `#D32F2F` | `#FF6B6B` | Erro / destrutivo |
| Warning | `#E65100` | `#FF9800` | Atenção (laranja) |
| Info | `#0288D1` | `#29B6F6` | Informativo |
| Accent 4 | `#F5F5F5` | `#2C352E` | Bordas e divisores |

Cores de status foram deslocadas em matiz para melhor distinção em daltônicos (verde Success ≠ verde Primary).

## Mapeamento Bootstrap (painel web)

| Token FF | Variável CSS |
|----------|----------------|
| Primary | `--bs-primary` |
| Secondary | `--bs-secondary`, `--well-nav-bg` |
| Success / Error / Warning / Info | `--bs-success` … `--bs-info` |
| Primary Background | `--bs-body-bg` |
| Secondary Background | `--painel-surface` |
| Primary Text | `--bs-body-color`, `--painel-text` |
| Secondary Text | `--bs-secondary-color`, `--painel-text-muted` |

## Tema padrão

- Sem preferência salva: segue `prefers-color-scheme` do sistema operacional
- Toggle manual: menu usuário → **Tema escuro/claro** (persiste em `localStorage` → `well-eco-theme`)

## Fora do escopo

- **Impressão MTR** ([`resources/css/mtr-print.css`](../resources/css/mtr-print.css)): documento branco, sem tema escuro

## Alterar cores

Editar apenas `panel-theme.css` (`:root` = light, `html[data-bs-theme="dark"]` = dark). Não editar `styles.css` (Bootstrap compilado).
