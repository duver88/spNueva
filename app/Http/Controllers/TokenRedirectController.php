<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokenRedirectController extends Controller
{
    public function redirect(Request $request, string $publicSlug)
    {
        // Buscar encuesta por public_slug (ofuscado)
        $survey = Survey::where('public_slug', $publicSlug)->with('group')->firstOrFail();

        // ========================================================================
        // DETECTAR BOTS DE REDES SOCIALES (Facebook, Twitter, etc.)
        // Estos bots pre-cargan URLs para mostrar previews, NO asignar token
        // IMPORTANTE: Esto evita que Facebook consuma tokens al crear anuncios
        // ========================================================================
        $userAgent = $request->userAgent();
        $socialBotPatterns = [
            'facebookexternalhit',      // Bot principal de Facebook para previews
            'FacebookExternalHit',      // Variante con mayúsculas
            'facebookcatalog',          // Bot de catálogo de Facebook
            'Facebot',                  // Otro bot de Facebook
            'meta-externalagent',       // Meta (Facebook) external agent
            'WhatsApp/',                // Bot de WhatsApp (incluye /)
            'Twitterbot',               // Bot de Twitter
            'LinkedInBot',              // Bot de LinkedIn
            'TelegramBot',              // Bot de Telegram
            'Slackbot',                 // Bot de Slack
            'Discordbot',               // Bot de Discord
            'Google-InspectionTool',    // Google inspection tool
            'Googlebot',                // Google crawler
            'ia_archiver',              // Internet Archive
            'Pinterest',                // Pinterest bot
            'Instagrambot',             // Instagram bot
        ];

        foreach ($socialBotPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                // Es un bot de red social haciendo preview
                // NO generar token, mostrar landing page neutra
                \Log::info('Bot detectado en /t/ - No se asignó token', [
                    'user_agent' => $userAgent,
                    'pattern' => $pattern,
                    'ip' => $request->ip()
                ]);

                // Redirigir a la encuesta SIN token
                return redirect()->route('surveys.show', [
                    'publicSlug' => $publicSlug
                ]);
            }
        }

        // Si la encuesta pertenece a un grupo, redirigir a la ruta con grupo
        if ($survey->survey_group_id && $survey->group && $survey->group->slug) {
            $queryParams = $request->query(); // Mantener todos los parámetros (token, source, etc.)
            return redirect()->route('token.redirect.group', [
                'groupSlug' => $survey->group->slug,
                'publicSlug' => $publicSlug
            ] + $queryParams);
        }

        // ========================================================================
        // VERIFICAR SI YA VIENE UN TOKEN EN LA URL
        // ========================================================================
        $tokenString = $request->query('token');

        if ($tokenString) {
            // Si viene un token en la URL, redirigir directamente a /survey/ con ese token
            // NO generar un token nuevo
            return redirect()->route('surveys.show', [
                'publicSlug' => $publicSlug,
                'token' => $tokenString
            ]);
        }

        // ========================================================================
        // DELAY CON VISTA INTERMEDIA
        // Mostrar página intermedia que cargará el token después de 5 segundos
        // Esto evita que Facebook/bots consuman tokens al hacer scraping rápido
        // ========================================================================

        // Mostrar vista intermedia con delay de 5 segundos
        // Esta vista mostrará la encuesta sin token y luego redirigirá con token
        return view('surveys.token-loading', [
            'survey' => $survey,
            'publicSlug' => $publicSlug,
            'delay' => 5000 // 5 segundos en milisegundos
        ]);
    }

    /**
     * API endpoint para asignar token (llamado por JavaScript después del delay)
     */
    public function assignToken(Request $request, string $publicSlug)
    {
        // Buscar encuesta por public_slug (ofuscado)
        $survey = Survey::where('public_slug', $publicSlug)->firstOrFail();

        // Intentar asignar un token disponible del pool (con bloqueo para evitar condiciones de carrera)
        DB::beginTransaction();

        try {
            // Buscar un token pendiente disponible (lockForUpdate para evitar asignación duplicada)
            $token = SurveyToken::where('survey_id', $survey->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            // Si NO hay tokens disponibles
            if (!$token) {
                DB::commit();
                return response()->json([
                    'success' => false,
                    'message' => 'No hay tokens disponibles'
                ], 404);
            }

            // Actualizar información del token
            $source = $request->query('source');
            $campaignId = $request->query('campaign_id');

            if ($source && $token->source === 'manual') {
                $token->source = $source;
            }
            if ($campaignId && !$token->campaign_id) {
                $token->campaign_id = $campaignId;
            }

            $token->user_agent = $request->userAgent();
            $token->save();

            DB::commit();

            // Retornar el token asignado
            return response()->json([
                'success' => true,
                'token' => $token->token,
                'redirect_url' => route('surveys.show', [
                    'publicSlug' => $publicSlug,
                    'token' => $token->token
                ])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar token'
            ], 500);
        }
    }

    /**
     * Redirigir con slug del grupo
     */
    public function redirectWithGroup(Request $request, string $groupSlug, string $publicSlug)
    {
        // Buscar el grupo por slug
        $group = \App\Models\SurveyGroup::where('slug', $groupSlug)->firstOrFail();

        // Buscar encuesta por public_slug (ofuscado) y verificar que pertenece al grupo
        $survey = Survey::where('public_slug', $publicSlug)
            ->where('survey_group_id', $group->id)
            ->firstOrFail();

        // ========================================================================
        // DETECTAR BOTS DE REDES SOCIALES (Facebook, Twitter, etc.)
        // Estos bots pre-cargan URLs para mostrar previews, NO asignar token
        // IMPORTANTE: Esto evita que Facebook consuma tokens al crear anuncios
        // ========================================================================
        $userAgent = $request->userAgent();
        $socialBotPatterns = [
            'facebookexternalhit',      // Bot principal de Facebook para previews
            'FacebookExternalHit',      // Variante con mayúsculas
            'facebookcatalog',          // Bot de catálogo de Facebook
            'Facebot',                  // Otro bot de Facebook
            'meta-externalagent',       // Meta (Facebook) external agent
            'WhatsApp/',                // Bot de WhatsApp (incluye /)
            'Twitterbot',               // Bot de Twitter
            'LinkedInBot',              // Bot de LinkedIn
            'TelegramBot',              // Bot de Telegram
            'Slackbot',                 // Bot de Slack
            'Discordbot',               // Bot de Discord
            'Google-InspectionTool',    // Google inspection tool
            'Googlebot',                // Google crawler
            'ia_archiver',              // Internet Archive
            'Pinterest',                // Pinterest bot
            'Instagrambot',             // Instagram bot
        ];

        foreach ($socialBotPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                // Es un bot de red social haciendo preview
                // NO generar token, mostrar landing page neutra
                \Log::info('Bot detectado en /t/ (grupo) - No se asignó token', [
                    'user_agent' => $userAgent,
                    'pattern' => $pattern,
                    'ip' => $request->ip()
                ]);

                // Redirigir a la encuesta SIN token
                return redirect()->route('surveys.show.group', [
                    'groupSlug' => $groupSlug,
                    'publicSlug' => $publicSlug
                ]);
            }
        }

        // ========================================================================
        // VERIFICAR SI YA VIENE UN TOKEN EN LA URL
        // ========================================================================
        $tokenString = $request->query('token');

        if ($tokenString) {
            // Si viene un token en la URL, redirigir directamente con ese token
            return redirect()->route('surveys.show.group', [
                'groupSlug' => $groupSlug,
                'publicSlug' => $publicSlug,
                'token' => $tokenString
            ]);
        }

        // ========================================================================
        // DELAY CON VISTA INTERMEDIA
        // Mostrar página intermedia que cargará el token después de 5 segundos
        // Esto evita que Facebook/bots consuman tokens al hacer scraping rápido
        // ========================================================================

        // Mostrar vista intermedia con delay de 5 segundos
        return view('surveys.token-loading', [
            'survey' => $survey,
            'groupSlug' => $groupSlug,
            'publicSlug' => $publicSlug,
            'delay' => 5000 // 5 segundos en milisegundos
        ]);
    }

    /**
     * Buscar voto previo de este dispositivo en el grupo
     */
    private function findPreviousVoteInGroup(int $groupId, Request $request, $deviceMatcher)
    {
        $userAgent = $request->userAgent();

        if (!$userAgent) {
            return null;
        }

        // Buscar votos en encuestas de este grupo con User-Agent similar
        return \App\Models\Vote::join('questions', 'votes.question_id', '=', 'questions.id')
            ->join('surveys', 'questions.survey_id', '=', 'surveys.id')
            ->where('surveys.survey_group_id', $groupId)
            ->where('votes.is_valid', true)
            ->where('votes.user_agent', 'LIKE', '%' . substr($userAgent, 0, 50) . '%')
            ->select('votes.*')
            ->first();
    }
}
