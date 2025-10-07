# Migração para CodeIgniter4-develop

## Resumo das Alterações

Este documento descreve as alterações realizadas para migrar o backend do projeto WIS4 para usar o CodeIgniter4-develop em vez da instalação via Composer.

## Arquivos Copiados

### Controladores
- ✅ `AuthController.php` - Controlador de autenticação
- ✅ `TaskController.php` - Controlador de tarefas
- ✅ `TaskExecutionController.php` - Controlador de execuções de tarefas

### Modelos
- ✅ `UserModel.php` - Modelo de usuários
- ✅ `TaskModel.php` - Modelo de tarefas
- ✅ `TaskExecutionModel.php` - Modelo de execuções de tarefas

### Filtros
- ✅ `CorsFilter.php` - Filtro CORS para APIs

### Configurações
- ✅ `Routes.php` - Configuração de rotas da API
- ✅ `Database.php` - Configuração do banco de dados
- ✅ `Filters.php` - Configuração de filtros
- ✅ `App.php` - Configurações principais da aplicação
- ✅ `.env` - Variáveis de ambiente

## Alterações Realizadas

### 1. Estrutura de Diretórios
```
CodeIgniter4-develop/
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── TaskController.php
│   │   └── TaskExecutionController.php
│   ├── Models/
│   │   ├── UserModel.php
│   │   ├── TaskModel.php
│   │   └── TaskExecutionModel.php
│   ├── Filters/
│   │   └── CorsFilter.php
│   └── Config/
│       ├── Routes.php
│       ├── Database.php
│       ├── Filters.php
│       └── App.php
├── public/
│   └── index.php
├── writable/
│   └── uploads/
├── .env
├── composer.json
└── Dockerfile
```

### 2. Composer.json
Adicionada dependência do AWS SDK:
```json
{
  "require": {
    "aws/aws-sdk-php": "^3.0"
  }
}
```

### 3. Docker Configuration
- Criado novo `Dockerfile` no CodeIgniter4-develop
- Atualizado `docker-compose.yml` para usar CodeIgniter4-develop em vez de backend

### 4. Configuração da Aplicação

#### App.php
- `baseURL` = 'http://localhost/api'
- `indexPage` = ''
- CSRF desabilitado para API

#### Database.php
- hostname: mariadb
- database: wis4_db
- username: wis4_user
- password: wis4_password

#### .env
Configurações de ambiente incluindo:
- CI_ENVIRONMENT = development
- Configurações de banco de dados
- Configurações de S3/S3Ninja

### 5. Routes.php
Rotas da API mantidas:
- `/api/auth/*` - Autenticação
- `/api/tasks/*` - Gerenciamento de tarefas
- `/api/executions/*` - Execuções de tarefas

## Como Usar

### 1. Instalar Dependências
```bash
cd CodeIgniter4-develop
composer install
```

### 2. Construir e Iniciar Containers
```bash
cd ..
docker-compose down
docker-compose build
docker-compose up -d
```

### 3. Verificar Status
```bash
docker-compose ps
```

### 4. Acessar Aplicação
- Frontend: http://localhost
- API: http://localhost/api
- S3Ninja: http://localhost:9000

## Testes

### Testar Autenticação
```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=admin@wis4.com&password=admin123"
```

### Verificar Status de Autenticação
```bash
curl http://localhost/api/auth/check
```

## Diferenças do Backend Antigo

### Vantagens do CodeIgniter4-develop
1. **Código Fonte Completo**: Acesso direto ao código fonte do framework
2. **Debugging Facilitado**: Mais fácil debugar e customizar o framework
3. **Atualizações Manuais**: Controle total sobre quando atualizar
4. **Desenvolvimento**: Ideal para contribuir com o framework

### Estrutura do Index.php
O arquivo `public/index.php` agora usa a classe `Boot` moderna:
```php
exit(Boot::bootWeb($paths));
```

## Notas Importantes

1. **Permissões**: O diretório `writable/` precisa ter permissões 777 no container
2. **Uploads**: Arquivos são enviados para S3Ninja por padrão
3. **Sessões**: Usando FileHandler com 7200 segundos de expiração
4. **CORS**: Habilitado globalmente via CorsFilter

## Troubleshooting

### Erro: "Class not found"
```bash
cd CodeIgniter4-develop
composer dump-autoload
```

### Erro: "Database connection failed"
Verificar se o container MariaDB está rodando:
```bash
docker-compose logs mariadb
```

### Erro: "S3 upload failed"
Verificar se o S3Ninja está rodando:
```bash
docker-compose logs s3ninja
```

## Próximos Passos

1. ✅ Migração completa para CodeIgniter4-develop
2. ⏳ Testar todas as rotas da API
3. ⏳ Validar upload de arquivos para S3
4. ⏳ Testar workflow completo (admin → colaborador → execução)

## Rollback

Se necessário reverter para o backend antigo:
```bash
# Editar docker-compose.yml
# Alterar:
#   - ./CodeIgniter4-develop:/var/www/html/backend
# Para:
#   - ./backend:/var/www/html/backend

docker-compose down
docker-compose build
docker-compose up -d
```

