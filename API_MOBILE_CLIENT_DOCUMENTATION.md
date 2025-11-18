# API MTicket - Mobile Client Endpoints

Este documento descreve todos os endpoints criados para a API mobile do cliente, baseados na estrutura existente do seu projeto Laravel.

## Base URL
```
https://seu-dominio.com/api/client
```

## Headers Obrigatórios
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token} # Para rotas protegidas
```

---

## 🔐 Autenticação (Já existente)

### POST /auth/login
### POST /auth/register
### POST /logout (Protegida)
### GET /me (Protegida)

---

## 🏠 Home Screen

### GET /events/featured
**Descrição:** Eventos em destaque para a home  
**Autenticação:** Opcional  
**Parâmetros:**
- `limit` (opcional): Número de eventos (default: 10, max: 50)
- `category_id` (opcional): Filtrar por categoria

**Exemplo de URL:**
```
GET /api/client/events/featured?limit=20&category_id=1
```

### GET /banners
**Descrição:** Banners promocionais para carrossel  
**Autenticação:** Não requerida  

**Exemplo de URL:**
```
GET /api/client/banners
```

### GET /categories
**Descrição:** Lista todas as categorias disponíveis  
**Autenticação:** Não requerida  

**Exemplo de URL:**
```
GET /api/client/categories
```

---

## 🔍 Search Screen

### GET /events/search
**Descrição:** Buscar eventos com filtros  
**Autenticação:** Opcional  
**Parâmetros:**
- `q` (opcional): Termo de busca
- `category_id` (opcional): ID da categoria
- `city` (opcional): Nome da cidade
- `state` (opcional): Nome do estado/província
- `date_from` (opcional): Data inicial (YYYY-MM-DD)
- `date_to` (opcional): Data final (YYYY-MM-DD)
- `price_min` (opcional): Preço mínimo
- `price_max` (opcional): Preço máximo
- `sort_by` (opcional): `date`, `price`, `popularity` (default: `date`)
- `sort_order` (opcional): `asc`, `desc` (default: `asc`)
- `page` (opcional): Página (default: 1)
- `per_page` (opcional): Itens por página (default: 20, max: 100)

**Exemplo de URL:**
```
GET /api/client/events/search?q=música&category_id=1&city=Luanda&price_max=5000&sort_by=date&per_page=20
```

### GET /events/suggestions
**Descrição:** Sugestões de busca baseadas no termo  
**Autenticação:** Requerida  
**Parâmetros:**
- `q` (requerido): Termo para sugestões (mínimo 2 caracteres)

**Exemplo de URL:**
```
GET /api/client/events/suggestions?q=mús
```

### GET /search/popular
**Descrição:** Termos de busca populares  
**Autenticação:** Não requerida  

---

## ❤️ Favoritos

### GET /favorites
**Descrição:** Listar eventos favoritos do usuário  
**Autenticação:** Requerida  
**Parâmetros:**
- `page` (opcional): Página
- `per_page` (opcional): Itens por página

**Exemplo de URL:**
```
GET /api/client/favorites?page=1&per_page=20
```

### POST /favorites
**Descrição:** Adicionar evento aos favoritos  
**Autenticação:** Requerida  
**Body:**
```json
{
  "event_id": 123
}
```

### DELETE /favorites/{event_id}
**Descrição:** Remover evento dos favoritos  
**Autenticação:** Requerida  

**Exemplo de URL:**
```
DELETE /api/client/favorites/123
```

### GET /favorites/check
**Descrição:** Verificar se eventos estão nos favoritos  
**Autenticação:** Requerida  
**Parâmetros:**
- `event_ids` (requerido): IDs dos eventos separados por vírgula

**Exemplo de URL:**
```
GET /api/client/favorites/check?event_ids=123,456,789
```

### GET /favorites/count
**Descrição:** Contar total de favoritos do usuário  
**Autenticação:** Requerida  

---

## 🎫 Ingressos

### GET /tickets
**Descrição:** Listar ingressos do usuário  
**Autenticação:** Requerida  
**Parâmetros:**
- `status` (opcional): `active`, `used`, `expired`, `cancelled`
- `event_id` (opcional): Filtrar por evento
- `page` (opcional): Página
- `per_page` (opcional): Itens por página

**Exemplo de URL:**
```
GET /api/client/tickets?status=active&page=1&per_page=20
```

### GET /tickets/{ticket_id}
**Descrição:** Detalhes de um ingresso específico  
**Autenticação:** Requerida  

**Exemplo de URL:**
```
GET /api/client/tickets/TKT-2024-001234
```

### POST /tickets/{ticket_id}/validate
**Descrição:** Validar ingresso (para organizadores)  
**Autenticação:** Requerida  
**Body:**
```json
{
  "entrance_gate": "Portão A",
  "validator_name": "João Validador",
  "latitude": -8.838333,
  "longitude": 13.234444
}
```

### GET /tickets/{ticket_id}/transfer-options
**Descrição:** Opções de transferência do ingresso  
**Autenticação:** Requerida  

### GET /tickets/count
**Descrição:** Contagem de ingressos por status  
**Autenticação:** Requerida  

---

## 📋 Categorias e Eventos

### GET /categories/{id}
**Descrição:** Detalhes de uma categoria  
**Autenticação:** Opcional  

### GET /categories/{id}/events
**Descrição:** Eventos de uma categoria específica  
**Autenticação:** Opcional  
**Parâmetros:**
- `page` (opcional): Página
- `per_page` (opcional): Itens por página

### GET /events/{id}
**Descrição:** Detalhes de um evento específico  
**Autenticação:** Opcional  

### GET /events/upcoming
**Descrição:** Próximos eventos baseados na localização  
**Autenticação:** Requerida  
**Parâmetros:**
- `lat` (requerido): Latitude
- `lng` (requerido): Longitude
- `radius` (opcional): Raio em km (default: 25)
- `limit` (opcional): Limite de resultados (default: 20)

---

## 🚀 Exemplo de Integração

### 1. Home Screen
```javascript
// Buscar eventos em destaque
const featuredEvents = await fetch('/api/client/events/featured?limit=10');

// Buscar banners
const banners = await fetch('/api/client/banners');

// Buscar categorias
const categories = await fetch('/api/client/categories');
```

### 2. Search Screen
```javascript
// Buscar eventos
const searchResults = await fetch('/api/client/events/search?q=música&city=Luanda', {
  headers: {
    'Authorization': 'Bearer ' + userToken
  }
});

// Buscar sugestões
const suggestions = await fetch('/api/client/events/suggestions?q=mús', {
  headers: {
    'Authorization': 'Bearer ' + userToken
  }
});
```

### 3. Favoritos
```javascript
// Listar favoritos
const favorites = await fetch('/api/client/favorites', {
  headers: {
    'Authorization': 'Bearer ' + userToken
  }
});

// Adicionar favorito
await fetch('/api/client/favorites', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + userToken,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ event_id: 123 })
});
```

### 4. Ingressos
```javascript
// Listar ingressos ativos
const activeTickets = await fetch('/api/client/tickets?status=active', {
  headers: {
    'Authorization': 'Bearer ' + userToken
  }
});
```

---

## 📊 Estrutura das Respostas

Todas as respostas seguem o padrão definido no `BaseController`:

### Sucesso (2xx)
```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": {
    // Dados específicos do endpoint
  },
  "meta": {
    // Metadados opcionais (paginação, etc)
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "total_pages": 5,
      "has_more_pages": true
    }
  }
}
```

### Erro (4xx/5xx)
```json
{
  "success": false,
  "message": "Descrição do erro",
  "errors": {
    "field_name": [
      "Mensagem de erro específica"
    ]
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

---

## ⚠️ Observações Importantes

1. **Autenticação**: Algumas rotas são públicas, outras requerem autenticação via Bearer Token
2. **Paginação**: Máximo de 100 itens por página
3. **Rate Limiting**: Implementar conforme necessário
4. **Validação**: Todos os inputs são validados
5. **Timezone**: Datas em formato ISO 8601 UTC
6. **Imagens**: URLs completas para todas as imagens

Esta API está totalmente integrada com sua estrutura existente e não altera nenhuma funcionalidade atual do sistema.