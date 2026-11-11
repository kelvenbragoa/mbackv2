# PayPay AO (experimento)

Módulo de gateway para pagamentos em kwanzas (live com `price_aoa`).

## Ficheiros

- `app/Services/PayPay/*` — crypto, client HTTP, serviço
- `app/Models/PayPayPayment.php`
- `app/Events/PayPayPaymentPaid.php` — disparado em `TRADE_FINISHED`
- `app/Console/Commands/PayPayReconcile.php`
- `app/Http/Controllers/Api/PayPayWebhookController.php`
- `config/paypay.php`
- Webhook: `POST /api/webhooks/paypay`
- Schedule: `paypay:reconcile` a cada 5 minutos

## Chaves (fora do git)

```bash
cd storage/paypay
openssl genrsa -out private.pem 1024
openssl rsa -in private.pem -pubout -out public.pem
# Carrega só public.pem no portal PayPay (sem header/footer/espaços, se a doc pedir).
# Descarrega a chave pública da PayPay para paypay_public.pem
```

No `.env` (opcional se usares os caminhos por omissão em `storage/paypay/`):

```
PAYPAY_API_URL=
PAYPAY_PARTNER_ID=
PAYPAY_SALE_PRODUCT_CODE=
PAYPAY_PRIVATE_KEY_PATH=
PAYPAY_PUBLIC_KEY_PATH=
PAYPAY_TIMEOUT_EXPRESS=2h
```

## Web (Fase 4)

No checkout do evento (`mfrontv2` → `checkout.vue`):
- Se os bilhetes seleccionados forem **live** com `price_aoa`, aparece **M-Pesa (MT)** | **PayPay (Kz)**
- PayPay: App ou Referência Multicaixa (>1000 Kz)
- Ecrã de espera com polling a `/api/paypay/orders/{id}`
- Sucesso → `/meusbilhetes` (sem PDF)

## Flutter (Fase 5)

App (`mclientv2`):
- `PurchaseFormScreen`: M-Pesa / PayPay quando só live com `price_aoa`
- `PayPayService` → `POST /api/paypay/checkout`, `GET /api/paypay/orders/{id}`
- `PayPayWaitingScreen`: polling 3s, deeplink, QR, entidade/referência
- Sucesso → confirmação → bilhetes

## Checkout experimental (Fase 3)

Auth Sanctum obrigatório (bilhete live).

```
POST /api/paypay/checkout
{
  "method": "app",
  "tickets": [{ "id": 123, "quantity": 1, "answers": [] }],
  "phone": "9xxxxxxxx",
  "customer_mobile": "9xxxxxxxx"
}

GET /api/paypay/orders/{id}
```

Regras:
- só bilhetes `is_live` com `price_aoa` > 0
- total calculado no servidor a partir de `price_aoa`
- no `TRADE_FINISHED` → `Sell` + `SellDetails` + `Transaction(method=paypay)` → `canWatchLive`

## Testes

```bash
php tests/paypay_crypto_selftest.php
php artisan paypay:reconcile
```

URL a registar no portal: `https://backend.mticket.co.mz/api/webhooks/paypay`
