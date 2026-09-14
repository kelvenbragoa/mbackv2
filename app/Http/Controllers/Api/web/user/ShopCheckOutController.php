<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Mail\SendShopOrder;
use App\Models\Event;
use App\Models\SellDetailShop;
use App\Models\SellShop;
use App\Models\ShopProduct;
use App\Models\ShopProductVariant;
use App\Models\ShopTransaction;
use App\Models\TemporarySellDetailShop;
use App\Models\TemporarySellShop;
use App\Models\TemporaryShopTransaction;
use App\Support\AccessCode;
use App\Support\ShopOrderPdf;
use App\Support\ShopReceiptFile;
use App\Notifications\ShopOrderPaid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class ShopCheckOutController extends Controller
{
    public function show(string $id)
    {
        $event = Event::with(['province', 'city', 'user'])
            ->where(function ($query) use ($id) {
                $query->where('slug', $id)->orWhere('id', $id);
            })
            ->first();

        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        $products = ShopProduct::where('event_id', $event->id)
            ->active()
            ->with('variants')
            ->orderBy('name')
            ->get()
            ->map->toPublicArray()
            ->values();

        return response()->json([
            'event' => $event,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerMobile' => ['required', 'string', 'max:30'],
            'paymentNumber' => ['required', 'string', 'max:30'],
            'user_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.shop_product_id' => ['required', 'integer'],
            'items.*.shop_product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $event = Event::find($data['event_id']);
        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if ($event->isSalesClosed()) {
            return response()->json(['message' => 'As vendas deste evento já terminaram.'], 422);
        }

        $userId = $request->user('sanctum')?->id ?: Auth::id() ?: ($data['user_id'] ?? null);
        $string = substr(str_shuffle(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', 4)), 0, 4);
        $lastId = (int) (ShopTransaction::orderByDesc('id')->value('id') ?? 0);
        $ref = 'MS'.($lastId + 1).'S'.$string.($lastId + 1);

        try {
            $prepared = DB::transaction(function () use ($data, $event, $userId, $ref) {
                $lines = $this->buildCart($event, $data['items']);
                $total = collect($lines)->sum('total');
                if ($total <= 0) {
                    throw new \RuntimeException('O total da encomenda é inválido.');
                }

                foreach ($lines as $line) {
                    $this->changeStock($line['product'], $line['variant'], (int) $line['quantity'], -1);
                }

                $temporary = TemporarySellShop::create([
                    'user_id' => $userId,
                    'event_id' => $event->id,
                    'total' => $total,
                    'status' => SellShop::STATUS_PENDING,
                    'method' => 'mpesa',
                    'name' => $data['customerName'],
                    'email' => $data['customerEmail'],
                    'mobile' => $data['customerMobile'],
                ]);

                foreach ($lines as $line) {
                    TemporarySellDetailShop::create([
                        'sell_id' => $temporary->id,
                        'user_id' => $userId,
                        'event_id' => $event->id,
                        'shop_product_id' => $line['product']->id,
                        'shop_product_variant_id' => $line['variant']?->id,
                        'qtd' => $line['quantity'],
                        'price' => $line['price'],
                        'total' => $line['total'],
                        'status' => SellShop::STATUS_PENDING,
                        'product_name' => $line['label'],
                    ]);
                }

                TemporaryShopTransaction::create([
                    'sell_id' => $temporary->id,
                    'user_id' => $userId,
                    'reference' => $ref,
                    'method' => 'mpesa',
                    'status' => 0,
                ]);

                return [$temporary, $total];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        /** @var TemporarySellShop $temporary */
        [$temporary, $total] = $prepared;

        try {
            $config = \abdulmueid\mpesa\Config::loadFromFile(public_path('config.php'));
            $mpesa = new \abdulmueid\mpesa\Transaction($config);
            $c2b = $mpesa->c2b((float) $total, $data['paymentNumber'], $ref, $ref);
        } catch (\Throwable $th) {
            $this->releaseTemporary($temporary);
            Log::error('Shop M-Pesa error: '.$th->getMessage());

            return response()->json(['message' => 'Não foi possível contactar o M-Pesa. Tenta novamente.'], 422);
        }

        if ($c2b->getCode() !== 'INS-0') {
            $this->releaseTemporary($temporary);

            return response()->json(['message' => $this->mpesaFailureMessage((string) $c2b->getCode())], 422);
        }

        $sell = DB::transaction(function () use ($temporary, $ref, $userId) {
            $temporary->load('details');
            $sell = SellShop::create([
                'user_id' => $userId,
                'event_id' => $temporary->event_id,
                'total' => $temporary->total,
                'status' => SellShop::STATUS_PAID,
                'method' => 'mpesa',
                'name' => $temporary->name,
                'email' => $temporary->email,
                'mobile' => $temporary->mobile,
            ]);

            foreach ($temporary->details as $line) {
                SellDetailShop::create([
                    'sell_id' => $sell->id,
                    'user_id' => $userId,
                    'event_id' => $temporary->event_id,
                    'shop_product_id' => $line->shop_product_id,
                    'shop_product_variant_id' => $line->shop_product_variant_id,
                    'qtd' => $line->qtd,
                    'price' => $line->price,
                    'total' => $line->total,
                    'status' => SellShop::STATUS_PAID,
                    'product_name' => $line->product_name,
                ]);
            }

            ShopTransaction::create([
                'sell_id' => $sell->id,
                'user_id' => $userId,
                'reference' => $ref,
                'method' => 'mpesa',
            ]);

            $temporary->transaction()->delete();
            $temporary->details()->delete();
            $temporary->delete();

            return $sell;
        });

        $this->notifyBuyer($sell, $event);

        return response()->json([
            'success' => true,
            'order' => $sell->toOrderArray(),
        ]);
    }

    public function receipt(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'qrcode' => ['required', 'string', 'max:32'],
        ]);

        $code = AccessCode::normalize($data['qrcode']);
        $sell = SellShop::with(['details', 'event.province', 'event.city', 'transaction'])
            ->where('id', $data['id'])
            ->where('qrcode', $code)
            ->where('status', SellShop::STATUS_PAID)
            ->first();

        if (! $sell) {
            return response()->json(['message' => 'Recibo não encontrado.'], 404);
        }

        $download = ShopOrderPdf::download($sell);
        if (! $download) {
            return response()->json(['message' => 'Não foi possível gerar o recibo.'], 422);
        }

        return $download;
    }

    private function buildCart(Event $event, array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $productId = (int) $item['shop_product_id'];
            $variantId = ! empty($item['shop_product_variant_id']) ? (int) $item['shop_product_variant_id'] : 0;
            $key = $productId.':'.$variantId;
            $qty = (int) $item['quantity'];
            $merged[$key] = ($merged[$key] ?? 0) + $qty;
        }

        $lines = [];
        foreach ($merged as $key => $qty) {
            [$productId, $variantId] = array_map('intval', explode(':', $key));

            $product = ShopProduct::where('event_id', $event->id)
                ->where('id', $productId)
                ->where('status', ShopProduct::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw new \RuntimeException('Um dos produtos já não está disponível.');
            }

            $product->load('variants');
            $variant = null;
            $price = (float) $product->sell_price;
            $label = $product->name;

            if ($product->variants->isNotEmpty()) {
                if ($variantId <= 0) {
                    throw new \RuntimeException('Escolhe o tamanho de '.$product->name.'.');
                }

                $variant = ShopProductVariant::where('shop_product_id', $product->id)
                    ->where('id', $variantId)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    throw new \RuntimeException('Tamanho indisponível para '.$product->name.'.');
                }

                $price = $variant->price((float) $product->sell_price);
                $label = $product->name.' / '.$variant->name;
                $available = (int) $variant->qtd;
            } else {
                if ($variantId > 0) {
                    throw new \RuntimeException('Este produto não tem tamanhos.');
                }
                $available = (int) $product->qtd;
            }

            if ($qty > $available) {
                throw new \RuntimeException('Stock insuficiente para '.$label.'. Restam '.$available.'.');
            }

            $lines[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $qty,
                'price' => $price,
                'total' => round($price * $qty, 2),
                'label' => $label,
            ];
        }

        return $lines;
    }

    private function changeStock(ShopProduct $product, ?ShopProductVariant $variant, int $qty, int $direction): void
    {
        if ($variant) {
            $next = (int) $variant->qtd + ($direction * $qty);
            if ($next < 0) {
                throw new \RuntimeException('Stock insuficiente para '.$product->name.' / '.$variant->name.'.');
            }
            $variant->qtd = $next;
            $variant->save();
            $product->qtd = (int) $product->variants()->sum('qtd');
            $product->save();

            return;
        }

        $next = (int) $product->qtd + ($direction * $qty);
        if ($next < 0) {
            throw new \RuntimeException('Stock insuficiente para '.$product->name.'.');
        }
        $product->qtd = $next;
        $product->save();
    }

    private function releaseTemporary(TemporarySellShop $temporary): void
    {
        DB::transaction(function () use ($temporary) {
            $temporary->load('details');
            foreach ($temporary->details as $line) {
                $product = ShopProduct::where('id', $line->shop_product_id)->lockForUpdate()->first();
                if (! $product) {
                    continue;
                }
                $variant = $line->shop_product_variant_id
                    ? ShopProductVariant::where('id', $line->shop_product_variant_id)->lockForUpdate()->first()
                    : null;
                $this->changeStock($product, $variant, (int) $line->qtd, 1);
            }

            $temporary->transaction()->delete();
            $temporary->details()->delete();
            $temporary->delete();
        });
    }

    private function notifyBuyer(SellShop $sell, Event $event): void
    {
        try {
            Mail::to($sell->email)->send(new SendShopOrder($sell, $event));
        } catch (\Throwable $th) {
            Log::error('Shop order email failed: '.$th->getMessage());
        }

        $this->sendShopWhatsapp($sell);
    }

    private function sendShopWhatsapp(SellShop $sell): void
    {
        if (! $sell->mobile) {
            return;
        }

        try {
            $url = ShopReceiptFile::temporaryUrl((int) $sell->id);
            if (! $url) {
                return;
            }

            Notification::send($sell->mobile, new ShopOrderPaid($url, (int) $sell->id, (string) $sell->mobile));
        } catch (\Throwable $th) {
            Log::error('Shop WhatsApp failed: '.$th->getMessage());
        }
    }

    private function mpesaFailureMessage(string $code): string
    {
        return match ($code) {
            'INS-5' => 'Transação cancelada no telemóvel.',
            'INS-6' => 'Transação falhou.',
            'INS-9' => 'O tempo expirou. Volta a tentar.',
            'INS-10' => 'Transação duplicada.',
            'INS-16' => 'Erro interno. Tenta mais tarde.',
            'INS-2006' => 'Saldo insuficiente.',
            'INS-2051' => 'Número de telefone inválido.',
            default => 'Não foi possível concluir o pagamento M-Pesa. Tenta novamente.',
        };
    }
}
