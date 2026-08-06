# 🎯 Funcionalidade Toggle de Favoritos - MTicket

## 📋 Implementação Completa..

A funcionalidade de toggle de favoritos foi implementada com sucesso! Agora os usuários podem facilmente favoritar/desfavoritar eventos tocando no ícone de coração.

## 🚀 Endpoint Implementado

### **POST** `/api/client/events/{id}/toggle-favorite`

**Descrição:** Alterna o status de favorito de um evento (favoritar/desfavoritar)  
**Autenticação:** Requerida (Bearer Token)  
**Método:** POST  

**Parâmetros:**
- `{id}` - ID do evento a ser favoritado/desfavoritado

**Response de Sucesso (200):**
```json
{
  "success": true,
  "message": "Evento adicionado aos favoritos", // ou "Evento removido dos favoritos"
  "data": {
    "event_id": 123,
    "is_favorited": true, // true se foi adicionado, false se foi removido
    "favorites_count": 45 // total de usuários que favoritaram este evento
  }
}
```

**Response de Erro (401 - Não autenticado):**
```json
{
  "success": false,
  "message": "Usuário não autenticado",
  "timestamp": "2024-11-21T10:30:00Z"
}
```

**Response de Erro (404 - Evento não encontrado):**
```json
{
  "success": false,
  "message": "Evento não encontrado",
  "timestamp": "2024-11-21T10:30:00Z"
}
```

## 📱 Exemplos de Integração Frontend

### React Native / JavaScript
```javascript
const toggleFavorite = async (eventId, authToken) => {
  try {
    const response = await fetch(`/api/client/events/${eventId}/toggle-favorite`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken}`
      }
    });

    const data = await response.json();
    
    if (data.success) {
      // Atualizar UI baseado em data.is_favorited
      console.log(data.message);
      return {
        isFavorited: data.data.is_favorited,
        favoritesCount: data.data.favorites_count
      };
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Erro ao alterar favorito:', error);
    throw error;
  }
};

// Uso em um componente
const EventCard = ({ event, authToken }) => {
  const [isFavorited, setIsFavorited] = useState(event.is_favorite);
  const [loading, setLoading] = useState(false);

  const handleToggleFavorite = async () => {
    if (loading) return;
    
    setLoading(true);
    try {
      const result = await toggleFavorite(event.id, authToken);
      setIsFavorited(result.isFavorited);
    } catch (error) {
      // Mostrar erro ao usuário
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h3>{event.title}</h3>
      <button 
        onClick={handleToggleFavorite}
        disabled={loading}
        style={{ color: isFavorited ? 'red' : 'gray' }}
      >
        {isFavorited ? '❤️' : '🤍'}
      </button>
    </div>
  );
};
```

### Flutter/Dart
```dart
class FavoriteService {
  static Future<Map<String, dynamic>> toggleFavorite(int eventId, String token) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/client/events/$eventId/toggle-favorite'),
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

// Widget de exemplo
class FavoriteButton extends StatefulWidget {
  final Event event;
  final String authToken;

  const FavoriteButton({Key? key, required this.event, required this.authToken}) 
      : super(key: key);

  @override
  _FavoriteButtonState createState() => _FavoriteButtonState();
}

class _FavoriteButtonState extends State<FavoriteButton> {
  bool _isFavorited = false;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _isFavorited = widget.event.isFavorite;
  }

  Future<void> _toggleFavorite() async {
    if (_loading) return;

    setState(() {
      _loading = true;
    });

    try {
      final result = await FavoriteService.toggleFavorite(
        widget.event.id, 
        widget.authToken
      );
      
      setState(() {
        _isFavorited = result['is_favorited'];
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_isFavorited ? 'Favoritado!' : 'Removido dos favoritos'))
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Erro: $e'))
      );
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: _loading ? null : _toggleFavorite,
      icon: _loading 
        ? SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2)
          )
        : Icon(
            _isFavorited ? Icons.favorite : Icons.favorite_border,
            color: _isFavorited ? Colors.red : Colors.grey,
          ),
    );
  }
}
```

## 🗂️ Estrutura do Banco de Dados

### Tabela `favorite_events`
```sql
CREATE TABLE favorite_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    event_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_user_event (user_id, event_id),
    UNIQUE KEY unique_user_event (user_id, event_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);
```

## 🔧 Características Técnicas

✅ **Idempotente** - Pode ser chamado múltiplas vezes sem efeitos colaterais  
✅ **Atomic** - Operação atômica (adiciona OU remove, nunca ambos)  
✅ **Performance** - Índices otimizados para consultas rápidas  
✅ **Integridade** - Chaves estrangeiras garantem consistência  
✅ **Duplicação** - Unique constraint evita favoritos duplicados  
✅ **Cascata** - Remoção automática se usuário/evento for deletado  

## 📋 Como Executar a Migration

Quando estiver pronto para aplicar as alterações no banco de dados:

```bash
# Executar a migration
php artisan migrate

# Verificar status das migrations
php artisan migrate:status

# Rollback se necessário (apenas para desenvolvimento)
php artisan migrate:rollback --step=1
```

## 🎯 Integração com Lista de Eventos

O campo `is_favorite` já está sendo retornado na formatação de eventos no `BaseController`, então todos os endpoints de listagem de eventos já mostram se o evento é favorito do usuário atual.

**Endpoints que retornam `is_favorite`:**
- `GET /api/client/events/featured`
- `GET /api/client/events/search`
- `GET /api/client/events/{id}`
- `GET /api/client/categories/{id}/events`

## ✨ Funcionalidades Adicionais Disponíveis

Você também pode usar os endpoints de favoritos já implementados:

- `GET /api/client/favorites` - Lista todos os favoritos
- `GET /api/client/favorites/count` - Conta total de favoritos
- `GET /api/client/favorites/check?event_ids=1,2,3` - Verifica múltiplos eventos

A funcionalidade está completa e pronta para uso! 🚀