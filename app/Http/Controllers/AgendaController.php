<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AgendaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class AgendaController extends Controller
{
    // ── fetchAgendaEvents ─────────────────────────────────────────────────────
    #[OA\Get(
        path: '/api/creators/{profileId}/agenda',
        summary: 'Récupérer l\'agenda public d\'un créateur',
        description: 'Retourne la liste des événements programmés qui ne sont pas annulés, triés par date chronologique.',
        tags: ['Agenda'],
        parameters: [
            new OA\Parameter(
                name: 'profileId',
                in: 'path',
                description: 'ID du profil créateur (UUID)',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des événements de l\'agenda',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des événements de l\'agenda du créateur'),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Créateur non trouvé'
    )]
    public function index(string $profileId): JsonResponse
    {
        $items = AgendaItem::where('user_id', $profileId)
            ->where('is_cancelled', false)
            ->orderBy('scheduled_at')
            ->get();

        $formatted = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,

                // 🔥 mapping vers le front
                'date' => $item->scheduled_at,
                'endDate' => $item->end_date,

                'imageUrl' => $item->image_url, // pas dans ta DB
                'link' => $item->url,
            ];
        });

        return response()->json($formatted);

    }

    // ── addAgendaEvent ────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/api/creators/{profileId}/agenda',
        summary: 'Ajouter un événement à l\'agenda',
        description: 'Crée un nouvel élément (Live, Sortie, Événement) dans l\'agenda du créateur.',
        tags: ['Agenda'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'profileId',
                in: 'path',
                required: true,
                description: 'ID du profil créateur (UUID)',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'type', 'scheduled_at'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Mon Live de lancement'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 1000, nullable: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['live', 'release', 'event']),
                    new OA\Property(property: 'url', type: 'string', format: 'url', nullable: true, example: 'https://wolplay.io/live/123'),
                    new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', description: 'Date de l\'événement (doit être dans le futur)')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Événement créé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Événement créé avec succès'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Interdit - Vous n\'êtes pas le propriétaire de ce profil')]
    public function store(Request $request, string $profileId): JsonResponse
    {
        $this->authorizeOwner($profileId);

        $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'type'         => 'nullable|in:live,release,event',
            'link'          => 'nullable|url',
            'date' => 'nullable|date|after:now',
            'endDate'     => 'nullable|date|after:scheduled_at',
            'imageUrl'   => 'nullable|url',
        ]);


        $scheduledAt = Carbon::parse($request->input('date'))
            ->format('Y-m-d H:i:s');

        $endate  = $request->input('endDate') ? Carbon::parse($request->input('endDate'))->format('Y-m-d H:i:s') : null;

        $item = AgendaItem::create([
            'user_id'      => $profileId,
            'title'        => $request->input('title'),
            'description'  => $request->input('description') ?? '',
            'type'         => $request->input('type') ?? 'event',
            'url'          => $request->input('link'),
            'scheduled_at' => $scheduledAt,
            'end_date'     => $endate,
            'image_url'    => $request->input('imageUrl'),
        ]);

        return response()->json($item, 201);
    }

    // ── updateAgendaEvent ─────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/api/creators/{profileId}/agenda/{eventId}',
        summary: 'Modifier un événement de l\'agenda',
        description: 'Met à jour les informations d\'un événement existant.',
        tags: ['Agenda'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'profileId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'eventId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string', maxLength: 1000, nullable: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['live', 'release', 'event']),
                    new OA\Property(property: 'url', type: 'string', format: 'url', nullable: true),
                    new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'is_cancelled', type: 'boolean', example: false)
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Événement mis à jour',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Événement mis à jour avec succès'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Événement non trouvé')]
    public function update(Request $request, string $profileId, string $eventId): JsonResponse
    {
        $this->authorizeOwner($profileId);

        $item = AgendaItem::where('user_id', $profileId)->findOrFail($eventId);

        $request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'type'         => 'sometimes|in:live,release,event',
            'url'          => 'nullable|url',
            'scheduled_at' => 'sometimes|date|after:now',
            'is_cancelled' => 'sometimes|boolean',
        ]);

        $item->update($request->only([
            'title',
            'description',
            'type',
            'url',
            'scheduled_at',
            'is_cancelled',
        ]));

        return response()->json($item);
    }

    // ── deleteAgendaEvent ─────────────────────────────────────────────────────
    #[OA\Delete(
        path: '/api/creators/{profileId}/agenda/{eventId}',
        summary: 'Supprimer un événement',
        description: 'Supprime définitivement un événement de l\'agenda.',
        tags: ['Agenda'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'profileId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ]
    )]
    #[OA\Response(response: 204, description: 'Événement supprimé')]
    public function destroy(string $profileId, string $eventId): JsonResponse
    {
        $this->authorizeOwner($profileId);

        $item = AgendaItem::where('user_id', $profileId)->findOrFail($eventId);
        $item->delete();

        return response()->json(null, 204);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function authorizeOwner(string $profileId): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->id === $profileId || $user->isAdmin()),
            403,
            'Action non autorisée.'
        );
    }
}
