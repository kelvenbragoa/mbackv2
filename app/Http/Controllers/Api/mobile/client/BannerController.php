<?php

namespace App\Http\Controllers\Api\Mobile\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BannerController extends BaseController
{
    /**
     * Get promotional banners for home screen.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Por enquanto, retornar banners estáticos
            // No futuro, pode ser criada uma tabela 'banners' no banco de dados
            $banners = [
                [
                    'id' => 1,
                    'title' => 'Eventos de Verão',
                    'image_url' => asset('storage/banners/summer.jpg'),
                    'link_type' => 'category',
                    'link_value' => '1', // ID da categoria música
                    'is_active' => true,
                    'order' => 1,
                    'expires_at' => '2024-12-31T23:59:59Z'
                ],
                [
                    'id' => 2,
                    'title' => 'Shows Especiais',
                    'image_url' => asset('storage/banners/special-shows.jpg'),
                    'link_type' => 'event',
                    'link_value' => '123', // ID de um evento específico
                    'is_active' => true,
                    'order' => 2,
                    'expires_at' => '2024-12-31T23:59:59Z'
                ],
                [
                    'id' => 3,
                    'title' => 'Desconto Black Friday',
                    'image_url' => asset('storage/banners/black-friday.jpg'),
                    'link_type' => 'url',
                    'link_value' => 'https://mticket.com/promocoes',
                    'is_active' => true,
                    'order' => 3,
                    'expires_at' => '2024-11-30T23:59:59Z'
                ]
            ];

            // Filtrar apenas banners ativos e não expirados
            $activeBanners = array_filter($banners, function ($banner) {
                return $banner['is_active'] && 
                       (new \DateTime($banner['expires_at'])) > new \DateTime();
            });

            // Reordenar por order
            usort($activeBanners, function ($a, $b) {
                return $a['order'] <=> $b['order'];
            });

            return $this->sendResponse(
                ['banners' => array_values($activeBanners)],
                'Banners recuperados com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar banners', [], 500);
        }
    }

    /**
     * Get banner details by ID.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Para implementação futura com banco de dados
            // Por enquanto, buscar nos banners estáticos
            $banners = [
                1 => [
                    'id' => 1,
                    'title' => 'Eventos de Verão',
                    'image_url' => asset('storage/banners/summer.jpg'),
                    'link_type' => 'category',
                    'link_value' => '1',
                    'is_active' => true,
                    'order' => 1,
                    'expires_at' => '2024-12-31T23:59:59Z'
                ]
            ];

            if (!isset($banners[$id])) {
                return $this->sendError('Banner não encontrado', [], 404);
            }

            return $this->sendResponse(
                ['banner' => $banners[$id]],
                'Detalhes do banner recuperados com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar detalhes do banner', [], 500);
        }
    }
}