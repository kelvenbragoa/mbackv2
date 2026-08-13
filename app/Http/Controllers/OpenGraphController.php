<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class OpenGraphController extends Controller
{
    public function event(Request $request, string $slug)
    {
        $event = Event::with(['user', 'province', 'city'])
            ->where(function ($query) use ($slug) {
                $query->where('slug', $slug);

                if (is_numeric($slug)) {
                    $query->orWhere('id', (int) $slug);
                }
            })
            ->first();

        if (! $event) {
            return redirect()->away($this->frontendUrl($request, '/eventos/'.$slug));
        }

        return $this->render([
            'title' => $event->name.' | '.config('opengraph.site_name'),
            'description' => $this->eventDescription($event),
            'image' => $this->imageTags($event->image),
            'url' => $this->frontendUrl($request, '/eventos/'.($event->slug ?: $slug)),
            'type' => 'article',
        ]);
    }

    public function promotor(Request $request, string $slug)
    {
        $promotor = User::query()
            ->where('is_promotor', 1)
            ->where('slug', $slug)
            ->first();

        // Em produção a página do promotor vive no subdomínio dele; o /p/{slug}
        // só é usado quando não estamos num host da família mticket.co.mz.
        $url = $this->requestHost($request) === $slug.'.'.config('opengraph.root_domain')
            ? 'https://'.$slug.'.'.config('opengraph.root_domain').'/'
            : $this->frontendUrl($request, '/p/'.$slug);

        if (! $promotor) {
            return redirect()->away($url);
        }

        $name = $promotor->company_name ?: $promotor->name;

        return $this->render([
            'title' => $name.' | '.config('opengraph.site_name'),
            'description' => $this->plainText($promotor->description)
                ?: trim($name.' na Mticket. '.($promotor->company_location ?: 'Vê os eventos e compra o teu bilhete.')),
            'image' => $this->imageTags($promotor->banner ?: $promotor->image),
            'url' => $url,
            'type' => 'profile',
        ]);
    }

    /**
     * JPEG derivative of a stored image, since crawlers (WhatsApp in
     * particular) ignore the WebP files we serve to browsers.
     */
    public function image(string $token)
    {
        $disk = Storage::disk('public');
        $path = ltrim(str_replace('\\', '/', (string) $this->decodePath($token)), '/');

        abort_if($path === '' || str_contains($path, '..') || ! $disk->exists($path), 404);

        $cached = 'og-cache/'.sha1($path).'.jpg';

        if (! $disk->exists($cached) || $disk->lastModified($cached) < $disk->lastModified($path)) {
            try {
                $image = Image::make($disk->path($path));

                if ($image->width() > config('opengraph.image_max_width')) {
                    $image->resize(config('opengraph.image_max_width'), null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }

                $quality = (int) config('opengraph.image_quality');
                $encoded = (string) $image->encode('jpg', $quality);

                // Acima de ~600KB o WhatsApp desiste da imagem e mostra só o texto.
                while (strlen($encoded) > config('opengraph.image_max_bytes') && $quality > 40) {
                    $quality -= 12;
                    $encoded = (string) $image->encode('jpg', $quality);
                }

                $disk->put($cached, $encoded);
            } catch (\Throwable $e) {
                // GD sem suporte a WebP, pasta sem permissões de escrita, etc.
                // Um preview com o ficheiro original é melhor do que um 500.
                report($e);

                return redirect()->away(rtrim(config('opengraph.storage_url'), '/').'/'.$path);
            }
        }

        return response()->file($disk->path($cached), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=2592000',
        ]);
    }

    private function render(array $data)
    {
        return response()
            ->view('og.share', $data)
            ->header('Cache-Control', 'public, max-age=600');
    }

    private function eventDescription(Event $event): string
    {
        $description = $this->plainText($event->description);

        if ($description !== '') {
            return $description;
        }

        $parts = [];

        if ($event->start_date) {
            try {
                $parts[] = Carbon::parse($event->start_date)->format('d/m/Y \à\s H:i');
            } catch (\Throwable) {
                // data inválida em BD: fica só a localização
            }
        }

        $location = collect([$event->city?->name, $event->province?->name])
            ->filter()
            ->implode(', ');

        $parts[] = $location ?: $event->address;

        $summary = collect($parts)->filter()->implode(' • ');

        return $summary !== '' ? $summary : config('opengraph.default_description');
    }

    private function plainText(?string $value, int $limit = 200): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));

        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit - 3).'...'
            : $text;
    }

    /**
     * URL da imagem + dimensões. Sem og:image:width/height o Facebook processa
     * a imagem de forma assíncrona e as primeiras partilhas saem sem preview.
     *
     * @return array{url: string, width: int|null, height: int|null, type: string|null}
     */
    private function imageTags(?string $path): array
    {
        $path = ltrim(trim((string) $path), '/');
        $disk = Storage::disk('public');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return ['url' => $path, 'width' => null, 'height' => null, 'type' => null];
        }

        if ($path === '' || ! $disk->exists($path)) {
            return ['url' => config('opengraph.fallback_image'), 'width' => null, 'height' => null, 'type' => null];
        }

        $size = @getimagesize($disk->path($path));
        $width = $size[0] ?? null;
        $height = $size[1] ?? null;
        $mime = $size['mime'] ?? null;

        // JPEG/PNG dentro do limite vão directos do storage, sem conversão.
        $servableAsIs = in_array($mime, config('opengraph.direct_mime_types'), true)
            && $disk->size($path) <= config('opengraph.image_max_bytes');

        if ($servableAsIs) {
            return [
                'url' => rtrim(config('opengraph.storage_url'), '/').'/'.$path,
                'width' => $width,
                'height' => $height,
                'type' => $mime,
            ];
        }

        return array_merge(
            [
                'url' => rtrim(config('opengraph.base_url'), '/').'/og/imagem/'.$this->encodePath($path),
                'type' => 'image/jpeg',
            ],
            $this->scaledSize($width, $height),
        );
    }

    /**
     * O caminho vai codificado em base64url para a URL não ter pontos: o Nginx
     * do backend intercepta qualquer pedido terminado em .jpg/.webp/... e
     * serve-o como ficheiro estático, sem nunca chegar ao PHP.
     */
    private function encodePath(string $path): string
    {
        return rtrim(strtr(base64_encode(ltrim($path, '/')), '+/', '-_'), '=');
    }

    private function decodePath(string $token): string
    {
        return (string) base64_decode(strtr($token, '-_', '+/'), true);
    }

    /**
     * Dimensões que a conversão vai produzir, sem precisar de a executar.
     *
     * @return array{width: int|null, height: int|null}
     */
    private function scaledSize(?int $width, ?int $height): array
    {
        if (! $width || ! $height) {
            return ['width' => null, 'height' => null];
        }

        $max = (int) config('opengraph.image_max_width');

        if ($width > $max) {
            $height = (int) round($height * $max / $width);
            $width = $max;
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Rebuild the public SPA URL, keeping the promoter subdomain the crawler
     * actually requested (passed through by Nginx as X-Og-Host).
     */
    private function frontendUrl(Request $request, string $path): string
    {
        return 'https://'.$this->requestHost($request).$path;
    }

    private function requestHost(Request $request): string
    {
        $root = config('opengraph.root_domain');
        $host = strtolower(trim(explode(':', (string) $request->header('X-Og-Host'))[0]));

        return $host === $root || str_ends_with($host, '.'.$root) ? $host : $root;
    }
}
