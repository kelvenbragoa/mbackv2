<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\SellDetailShop;
use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PromotorShopProductController extends Controller
{
    use AuthorizesEventAccess;

    public function store(Request $request)
    {
        $this->decodeVariants($request);

        $data = $this->validated($request);

        if ($denied = $this->denyEventAccess($data['event_id'])) {
            return $denied;
        }

        $product = ShopProduct::create([
            'event_id' => $data['event_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sell_price' => $data['sell_price'],
            'qtd' => $data['qtd'] ?? 0,
            'status' => $data['status'] ?? ShopProduct::STATUS_ACTIVE,
            'pickup_only' => $data['pickup_only'] ?? true,
            'image' => $this->storeImage($request),
        ]);

        $this->syncVariants($product, $data['variants'] ?? []);

        return response()->json($this->payload($product), 201);
    }

    public function show(string $id)
    {
        $product = $this->findProduct($id);
        if ($product instanceof \Illuminate\Http\JsonResponse) {
            return $product;
        }

        if ($denied = $this->denyEventAccess($product->event_id)) {
            return $denied;
        }

        return response()->json($this->payload($product));
    }

    public function edit(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $product = $this->findProduct($id);
        if ($product instanceof \Illuminate\Http\JsonResponse) {
            return $product;
        }

        if ($denied = $this->denyEventAccess($product->event_id)) {
            return $denied;
        }

        $this->decodeVariants($request);
        $data = $this->validated($request, $product);

        $product->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sell_price' => $data['sell_price'],
            'qtd' => $data['qtd'] ?? $product->qtd,
            'status' => $data['status'] ?? $product->status,
            'pickup_only' => $data['pickup_only'] ?? $product->pickup_only,
        ]);

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $product->image = $this->storeImage($request);
        }

        $product->save();
        $this->syncVariants($product, $data['variants'] ?? []);

        return response()->json($this->payload($product->fresh('variants')));
    }

    public function destroy(string $id)
    {
        $product = $this->findProduct($id);
        if ($product instanceof \Illuminate\Http\JsonResponse) {
            return $product;
        }

        if ($denied = $this->denyEventAccess($product->event_id)) {
            return $denied;
        }

        if (SellDetailShop::where('shop_product_id', $product->id)->exists()) {
            return response()->json([
                'message' => 'Não é possível eliminar este produto: já existem encomendas associadas.',
            ], 422);
        }

        $this->deleteImage($product->image);
        $product->variants()->delete();
        $product->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?ShopProduct $existing = null): array
    {
        return $request->validate([
            'event_id' => [$existing ? 'sometimes' : 'required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'qtd' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:0,1'],
            'pickup_only' => ['nullable'],
            'image' => ['nullable', 'image', 'max:4096'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:80'],
            'variants.*.qtd' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.sell_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function decodeVariants(Request $request): void
    {
        $variants = $request->input('variants');
        if (is_string($variants)) {
            $request->merge(['variants' => json_decode($variants, true) ?: []]);
        }
    }

    private function syncVariants(ShopProduct $product, array $variants): void
    {
        $keepIds = [];

        foreach ($variants as $row) {
            $payload = [
                'name' => trim($row['name']),
                'qtd' => (int) $row['qtd'],
                'sell_price' => isset($row['sell_price']) && $row['sell_price'] !== '' ? $row['sell_price'] : null,
            ];

            $variant = null;
            if (! empty($row['id'])) {
                $variant = $product->variants()->where('id', $row['id'])->first();
            }

            if ($variant) {
                $variant->update($payload);
            } else {
                $variant = $product->variants()->create($payload);
            }

            $keepIds[] = $variant->id;
        }

        $toDelete = $product->variants()->whereNotIn('id', $keepIds)->get();
        foreach ($toDelete as $variant) {
            if (SellDetailShop::where('shop_product_variant_id', $variant->id)->exists()) {
                continue;
            }
            $variant->delete();
        }

        if ($keepIds !== []) {
            $product->qtd = (int) $product->variants()->sum('qtd');
            $product->save();
        }
    }

    private function findProduct(string $id): ShopProduct|\Illuminate\Http\JsonResponse
    {
        $product = ShopProduct::with('variants')->find($id);
        if (! $product) {
            return response()->json(['message' => 'Produto da loja não encontrado.'], 404);
        }

        return $product;
    }

    private function payload(ShopProduct $product): array
    {
        $product->loadMissing('variants');

        return [
            'product' => [
                'id' => $product->id,
                'event_id' => $product->event_id,
                'name' => $product->name,
                'description' => $product->description,
                'image' => $product->image,
                'sell_price' => (float) $product->sell_price,
                'qtd' => $product->stock(),
                'status' => (int) $product->status,
                'pickup_only' => (bool) $product->pickup_only,
                'variants' => $product->variants,
            ],
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $file = $request->file('image');
        $name = time().'-'.uniqid().'.'.$file->extension();
        $file->storeAs('public/shop', $name);

        return 'shop/'.$name;
    }

    private function deleteImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::delete('public/'.$path);
    }
}
