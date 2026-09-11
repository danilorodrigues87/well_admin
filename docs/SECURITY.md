# Segurança — Well Eco Admin

## Autenticação web

- Sessão PHP (`well-eco-user`), cookie 24h
- Arquivos de sessão: `app/sessions/` (fora do web root idealmente)
- Sync a cada request: `Login::syncSessionFromDatabase()`

## CSRF

- Token em `$_SESSION['well_eco_csrf']`
- Incluir `CsrfHelper::field()` em formulários POST
- Validar com `CsrfHelper::validate($post['_csrf'])`

## RBAC

| Função seed | is_admin | Módulos típicos |
|-------------|----------|-----------------|
| Administrador | sim | Todos |
| Coletor | não | dashboard, coletas, coleta_nova |
| Gestor de Coletas | não | dashboard, coletas, agendamentos, clientes, rotas, relatorios |

Overrides por usuário: tabela `usuario_modulos` (grant/revoke).

## Credenciais seed

Alterar senha do `admin@well.eco` após primeiro login em produção.
