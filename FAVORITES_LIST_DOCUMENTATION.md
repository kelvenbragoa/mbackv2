# 📋 Método de Eventos Favoritos - MTicket API

## 🎯 Novo Endpoint Implementado

### **GET** `/api/client/events/favorites`

**Descrição:** Retorna todos os eventos favoritos do usuário autenticado  
**Autenticação:** Requerida (Bearer Token)  
**Método:** GET  

## 📝 Parâmetros de Query

| Parâmetro | Tipo | Obrigatório | Default | Descrição |
|-----------|------|-------------|---------|-----------|
| `per_page` | integer | Não | 20 | Itens por página (máx: 100) |
| `page` | integer | Não | 1 | Número da página |
| `sort_by` | string | Não | `created_at` | Campo de ordenação |
| `sort_order` | string | Não | `desc` | Direção da ordenação |

### Valores válidos para `sort_by`:
- `created_at` - Data que foi favoritado (padrão)
- `date` - Data do evento (`start_date`)
- `name` - Nome do evento (alfabética)

### Valores válidos para `sort_order`:
- `desc` - Descendente (mais recente primeiro)
- `asc` - Ascendente (mais antigo primeiro)

## 📱 Exemplos de Uso

### Exemplo 1: Buscar favoritos básico
```http
GET /api/client/events/favorites
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Exemplo 2: Com paginação
```http
GET /api/client/events/favorites?page=2&per_page=10
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Exemplo 3: Ordenado por data do evento
```http
GET /api/client/events/favorites?sort_by=date&sort_order=asc
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Exemplo 4: Ordenado alfabeticamente
```http
GET /api/client/events/favorites?sort_by=name&sort_order=asc&per_page=50
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

## 📊 Resposta de Sucesso (200)

### Quando há eventos favoritos:
```json
{
  "success": true,
  "message": "Eventos favoritos recuperados com sucesso",
  "data": {
    "events": [
      {
        "id": 123,
        "title": "Show da Banda XYZ",
        "description": "Um show incrível de rock nacional",
        "short_description": "Um show incrível de rock nacional...",
        "image_url": "https://api.mticket.com/storage/events/show-xyz.jpg",
        "banner_url": "https://api.mticket.com/storage/events/show-xyz-banner.jpg",
        "category": {
          "id": 1,
          "name": "Música",
          "icon": "music",
          "color": "#FF6B6B"
        },
        "venue": {
          "id": 1,
          "name": "Centro de Convenções",
          "address": "Av. Marginal, 1000",
          "city": "Luanda",
          "state": "Luanda",
          "latitude": -8.838333,
          "longitude": 13.234444
        },
        "date_time": "2024-12-25T20:00:00Z",
        "end_date_time": "2024-12-25T23:00:00Z",
        "price_range": {
          "min": 2500.00,
          "max": 15000.00,
          "currency": "AOA"
        },
        "ticket_types": [
          {
            "id": 1,
            "name": "Pista",
            "price": 2500.00,
            "available_quantity": 450,
            "total_quantity": 500
          },
          {
            "id": 2,
            "name": "VIP",
            "price": 15000.00,
            "available_quantity": 18,
            "total_quantity": 50
          }
        ],
        "is_favorite": true,
        "is_sold_out": false,
        "rating": {
          "average": 4.7,
          "total_reviews": 89
        },
        "tags": [],
        "created_at": "2024-11-01T10:30:00Z",
        "updated_at": "2024-11-15T14:22:00Z"
      }
    ],
    "total_favorites": 15
  },
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 15,
      "total_pages": 1,
      "has_more_pages": false
    }
  }
}
```

### Quando não há eventos favoritos:
```json
{
  "success": true,
  "message": "Nenhum evento favorito encontrado",
  "data": {
    "events": []
  },
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 0,
      "total_pages": 0,
      "has_more_pages": false
    }
  }
}
```

## ❌ Respostas de Erro

### 401 - Não Autenticado
```json
{
  "success": false,
  "message": "Usuário não autenticado",
  "timestamp": "2024-11-21T10:30:00Z"
}
```

### 500 - Erro Interno
```json
{
  "success": false,
  "message": "Erro ao buscar eventos favoritos",
  "errors": {
    "error": "Mensagem detalhada do erro"
  },
  "timestamp": "2024-11-21T10:30:00Z"
}
```

## 💻 Implementação Frontend

### JavaScript/React Native
```javascript
const getFavoriteEvents = async (options = {}) => {
  const {
    page = 1,
    perPage = 20,
    sortBy = 'created_at',
    sortOrder = 'desc',
    authToken
  } = options;

  try {
    const queryParams = new URLSearchParams({
      page: page.toString(),
      per_page: perPage.toString(),
      sort_by: sortBy,
      sort_order: sortOrder
    });

    const response = await fetch(`/api/client/events/favorites?${queryParams}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken}`
      }
    });

    const data = await response.json();
    
    if (data.success) {
      return {
        events: data.data.events,
        totalFavorites: data.data.total_favorites,
        pagination: data.meta.pagination
      };
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Erro ao buscar eventos favoritos:', error);
    throw error;
  }
};

// Exemplo de uso em um componente React
const FavoritesScreen = ({ authToken }) => {
  const [favorites, setFavorites] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState(null);

  useEffect(() => {
    loadFavorites();
  }, []);

  const loadFavorites = async (page = 1) => {
    try {
      setLoading(true);
      const result = await getFavoriteEvents({
        page,
        perPage: 10,
        sortBy: 'date', // Ordenar por data do evento
        sortOrder: 'asc',
        authToken
      });
      
      setFavorites(result.events);
      setPagination(result.pagination);
    } catch (error) {
      // Tratar erro
    } finally {
      setLoading(false);
    }
  };

  const handleLoadMore = () => {
    if (pagination?.has_more_pages) {
      loadFavorites(pagination.current_page + 1);
    }
  };

  return (
    <div>
      <h2>Meus Eventos Favoritos ({pagination?.total || 0})</h2>
      {loading ? (
        <div>Carregando...</div>
      ) : favorites.length > 0 ? (
        <>
          {favorites.map(event => (
            <EventCard key={event.id} event={event} />
          ))}
          {pagination?.has_more_pages && (
            <button onClick={handleLoadMore}>
              Carregar Mais
            </button>
          )}
        </>
      ) : (
        <div>Você ainda não tem eventos favoritos</div>
      )}
    </div>
  );
};
```

### Flutter/Dart
```dart
class FavoriteEventsService {
  static Future<Map<String, dynamic>> getFavoriteEvents({
    int page = 1,
    int perPage = 20,
    String sortBy = 'created_at',
    String sortOrder = 'desc',
    required String token
  }) async {
    final queryParams = {
      'page': page.toString(),
      'per_page': perPage.toString(),
      'sort_by': sortBy,
      'sort_order': sortOrder,
    };

    final uri = Uri.parse('$baseUrl/api/client/events/favorites')
        .replace(queryParameters: queryParams);

    final response = await http.get(
      uri,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );

    final data = json.decode(response.body);
    
    if (data['success']) {
      return data['data'];
    } else {
      throw Exception(data['message']);
    }
  }
}
```

## 🔗 Endpoints Relacionados

- `POST /api/client/events/{id}/toggle-favorite` - Adicionar/remover favorito
- `GET /api/client/favorites/count` - Contar total de favoritos (FavoriteController)
- `GET /api/client/favorites/check?event_ids=1,2,3` - Verificar status de múltiplos eventos

## ✨ Características

✅ **Paginação** - Suporte completo a paginação  
✅ **Ordenação Flexível** - Por data de favorito, data do evento ou nome  
✅ **Performance** - Consulta otimizada com índices  
✅ **Informações Completas** - Todos os dados do evento formatados  
✅ **Contagem Total** - Retorna total de favoritos do usuário  
✅ **Tratamento de Vazio** - Resposta adequada quando não há favoritos  
✅ **Campo is_favorite** - Sempre `true` para todos os eventos retornados  

O endpoint está pronto para uso e totalmente integrado com o sistema de favoritos! 🎯✨