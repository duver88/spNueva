<?php

namespace App\Services;

use App\Models\Survey;
use Illuminate\Support\Facades\DB;

class SurveyReportGenerator
{
    /**
     * Generar reporte completo para una encuesta individual
     */
    public function generate(Survey $survey): array
    {
        return [
            'basic_stats' => $this->generateBasicStats($survey),
            'question_stats' => $this->generateQuestionStats($survey),
            'fraud_stats' => $this->generateFraudStats($survey),
            'token_stats' => $this->generateTokenStats($survey),
            'conversion_metrics' => $this->generateConversionMetrics($survey),
        ];
    }

    /**
     * Estadísticas básicas de la encuesta
     */
    public function generateBasicStats(Survey $survey): array
    {
        // Votos totales enviados (con token válido o manuales)
        $totalVotes = $survey->votes()->valid()->count();

        // Votos contados (válidos Y aprobados)
        $validVotes = $survey->votes()->countable()->count();

        // Votos pendientes de revisión
        $pendingReview = $survey->votes()->pendingReview()->count();

        // Votos rechazados
        $rejectedVotes = $survey->votes()->rejected()->count();

        // Votos no contados (sin token válido)
        $notCountedVotes = $survey->votes()->where('is_valid', false)->count();

        // Votos duplicados/fraudulentos (rechazados + pendientes)
        $duplicateVotes = $rejectedVotes + $pendingReview;

        // Vistas totales
        $totalViews = $survey->views_count ?? 0;

        // Votantes únicos (por fingerprint)
        $uniqueVoters = $survey->votes()->countable()
            ->distinct('fingerprint')
            ->count('fingerprint');

        return [
            'total_views' => $totalViews,
            'total_votes_submitted' => $totalVotes,
            'valid_votes' => $validVotes,
            'pending_review' => $pendingReview,
            'rejected_votes' => $rejectedVotes,
            'not_counted_votes' => $notCountedVotes,
            'duplicate_or_fraudulent' => $duplicateVotes,
            'unique_voters' => $uniqueVoters,
        ];
    }

    /**
     * Estadísticas por pregunta y respuesta con porcentajes
     */
    public function generateQuestionStats(Survey $survey): array
    {
        $questions = $survey->questions()
            ->with(['options'])
            ->orderBy('order')
            ->get();

        $stats = [];

        foreach ($questions as $question) {
            // Total de votos contables para esta pregunta
            $totalVotesForQuestion = $survey->votes()
                ->countable()
                ->where('question_id', $question->id)
                ->count();

            $optionStats = [];

            foreach ($question->options as $option) {
                // Votos contables para esta opción
                $votesForOption = $survey->votes()
                    ->countable()
                    ->where('question_id', $question->id)
                    ->where('question_option_id', $option->id)
                    ->count();

                // Calcular porcentaje
                $percentage = $totalVotesForQuestion > 0
                    ? round(($votesForOption / $totalVotesForQuestion) * 100, 2)
                    : 0;

                $optionStats[] = [
                    'option_id' => $option->id,
                    'option_text' => $option->option_text,
                    'votes' => $votesForOption,
                    'percentage' => $percentage,
                ];
            }

            $stats[] = [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'total_votes' => $totalVotesForQuestion,
                'options' => $optionStats,
            ];
        }

        return $stats;
    }

    /**
     * Estadísticas de fraude
     */
    public function generateFraudStats(Survey $survey): array
    {
        $votes = $survey->votes()->valid()->get();

        if ($votes->isEmpty()) {
            return [
                'average_fraud_score' => 0,
                'high_risk_count' => 0,
                'high_risk_percentage' => 0,
                'fraud_reasons_distribution' => [],
            ];
        }

        // Score promedio
        $avgFraudScore = round($votes->avg('fraud_score'), 2);

        // Votos de alto riesgo (score >= 60)
        $highRiskCount = $votes->where('fraud_score', '>=', 60)->count();
        $highRiskPercentage = round(($highRiskCount / $votes->count()) * 100, 2);

        // Distribución de razones de fraude
        $fraudReasonsDistribution = [];
        foreach ($votes as $vote) {
            if ($vote->fraud_reasons) {
                $reasons = is_string($vote->fraud_reasons)
                    ? json_decode($vote->fraud_reasons, true)
                    : $vote->fraud_reasons;

                if (is_array($reasons)) {
                    foreach ($reasons as $reason) {
                        if (!isset($fraudReasonsDistribution[$reason])) {
                            $fraudReasonsDistribution[$reason] = 0;
                        }
                        $fraudReasonsDistribution[$reason]++;
                    }
                }
            }
        }

        return [
            'average_fraud_score' => $avgFraudScore,
            'high_risk_count' => $highRiskCount,
            'high_risk_percentage' => $highRiskPercentage,
            'fraud_reasons_distribution' => $fraudReasonsDistribution,
        ];
    }

    /**
     * Estadísticas de tokens
     */
    public function generateTokenStats(Survey $survey): array
    {
        $tokens = $survey->tokens;

        return [
            'total_tokens' => $tokens->count(),
            'pending_tokens' => $tokens->where('status', 'pending')->count(),
            'reserved_tokens' => $tokens->where('status', 'reserved')->count(),
            'used_tokens' => $tokens->where('status', 'used')->count(),
            'expired_tokens' => $tokens->where('status', 'expired')->count(),
            'usage_rate' => $tokens->count() > 0
                ? round(($tokens->where('status', 'used')->count() / $tokens->count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Métricas de conversión
     */
    public function generateConversionMetrics(Survey $survey): array
    {
        $views = $survey->views_count ?? 0;
        $totalVotes = $survey->votes()->valid()->count();
        $approvedVotes = $survey->votes()->countable()->count();

        // Tasa de conversión: vistas -> votos
        $viewToVoteRate = $views > 0
            ? round(($totalVotes / $views) * 100, 2)
            : 0;

        // Tasa de aprobación: votos -> aprobados
        $voteApprovalRate = $totalVotes > 0
            ? round(($approvedVotes / $totalVotes) * 100, 2)
            : 0;

        // Tasa de conversión completa: vistas -> aprobados
        $completeConversionRate = $views > 0
            ? round(($approvedVotes / $views) * 100, 2)
            : 0;

        return [
            'view_to_vote_rate' => $viewToVoteRate,
            'vote_approval_rate' => $voteApprovalRate,
            'complete_conversion_rate' => $completeConversionRate,
        ];
    }
}
