<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends BaseController
{
    /**
     * Get all active categories.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $categories = Category::with('events')->get();

            $formattedCategories = $categories->map(function ($category) {
                return $this->formatCategory($category);
            });

            return $this->sendResponse(
                ['categories' => $formattedCategories],
                'Categorias recuperadas com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar categorias', [], 500);
        }
    }

    /**
     * Get category details by ID.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $category = Category::with('events')->find($id);

            if (!$category) {
                return $this->sendError('Categoria não encontrada', [], 404);
            }

            $formattedCategory = $this->formatCategory($category);

            return $this->sendResponse(
                ['category' => $formattedCategory],
                'Detalhes da categoria recuperados com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar detalhes da categoria', [], 500);
        }
    }

    /**
     * Get events by category.
     */
    public function events(Request $request, $id): JsonResponse
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return $this->sendError('Categoria não encontrada', [], 404);
            }

            $perPage = min($request->get('per_page', 20), 100);
            
            $events = $category->events()
                              ->with(['city', 'province', 'tickets', 'sells', 'review', 'like'])
                              ->whereIn('status_id', [1,2,3])
                              ->where('start_date', '>=', now()->format('Y-m-d'))
                              ->orderBy('start_date', 'asc')
                              ->paginate($perPage);

            $formattedEvents = $events->getCollection()->map(function ($event) {
                return $this->formatEvent($event, \Illuminate\Support\Facades\Auth::id());
            });

            $events->setCollection($formattedEvents);

            return $this->sendResponse(
                [
                    'category' => $this->formatCategory($category),
                    'events' => $events->items()
                ],
                'Eventos da categoria recuperados com sucesso',
                $this->formatPagination($events)
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar eventos da categoria', [], 500);
        }
    }
}