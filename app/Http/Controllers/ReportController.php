<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyGroup;
use App\Services\SurveyReportGenerator;
use App\Services\GroupReportGenerator;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    protected $surveyReportGenerator;
    protected $groupReportGenerator;

    public function __construct(
        SurveyReportGenerator $surveyReportGenerator,
        GroupReportGenerator $groupReportGenerator
    ) {
        $this->surveyReportGenerator = $surveyReportGenerator;
        $this->groupReportGenerator = $groupReportGenerator;
    }

    /**
     * Mostrar reporte de una encuesta individual
     */
    public function showSurveyReport(Survey $survey)
    {
        // Generar reporte completo
        $report = $this->surveyReportGenerator->generate($survey);

        return view('admin.reports.survey', [
            'survey' => $survey,
            'report' => $report,
        ]);
    }

    /**
     * Exportar reporte de encuesta a PDF
     */
    public function exportSurveyReport(Survey $survey)
    {
        // Generar reporte completo
        $report = $this->surveyReportGenerator->generate($survey);

        $pdf = Pdf::loadView('admin.reports.survey-pdf', [
            'survey' => $survey,
            'report' => $report,
        ]);

        return $pdf->download('reporte-' . $survey->public_slug . '-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Exportar reporte de encuesta a Excel/CSV
     */
    public function exportSurveyReportCsv(Survey $survey)
    {
        $report = $this->surveyReportGenerator->generate($survey);

        $filename = 'reporte-' . $survey->public_slug . '-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($survey, $report) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, ['REPORTE DE ENCUESTA: ' . $survey->title]);
            fputcsv($file, ['Fecha generación: ' . now()->format('Y-m-d H:i:s')]);
            fputcsv($file, []);

            // Estadísticas básicas
            fputcsv($file, ['ESTADÍSTICAS GENERALES']);
            fputcsv($file, ['Métrica', 'Valor']);
            fputcsv($file, ['Vistas totales', $report['basic_stats']['total_views']]);
            fputcsv($file, ['Votos enviados', $report['basic_stats']['total_votes_submitted']]);
            fputcsv($file, ['Votos válidos', $report['basic_stats']['valid_votes']]);
            fputcsv($file, ['Votos pendientes revisión', $report['basic_stats']['pending_review']]);
            fputcsv($file, ['Votos rechazados', $report['basic_stats']['rejected_votes']]);
            fputcsv($file, ['Votos no contados', $report['basic_stats']['not_counted_votes']]);
            fputcsv($file, ['Votos duplicados/fraudulentos', $report['basic_stats']['duplicate_or_fraudulent']]);
            fputcsv($file, ['Votantes únicos', $report['basic_stats']['unique_voters']]);
            fputcsv($file, []);

            // Conversión
            fputcsv($file, ['MÉTRICAS DE CONVERSIÓN']);
            fputcsv($file, ['Métrica', 'Porcentaje']);
            fputcsv($file, ['Vistas → Votos', $report['conversion_metrics']['view_to_vote_rate'] . '%']);
            fputcsv($file, ['Votos → Aprobados', $report['conversion_metrics']['vote_approval_rate'] . '%']);
            fputcsv($file, ['Conversión completa', $report['conversion_metrics']['complete_conversion_rate'] . '%']);
            fputcsv($file, []);

            // Estadísticas por pregunta
            fputcsv($file, ['ESTADÍSTICAS POR PREGUNTA']);
            foreach ($report['question_stats'] as $questionStat) {
                fputcsv($file, []);
                fputcsv($file, ['Pregunta: ' . $questionStat['question_text']]);
                fputcsv($file, ['Total votos: ' . $questionStat['total_votes']]);
                fputcsv($file, ['Opción', 'Votos', 'Porcentaje']);

                foreach ($questionStat['options'] as $option) {
                    fputcsv($file, [
                        $option['option_text'],
                        $option['votes'],
                        $option['percentage'] . '%'
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Mostrar reporte de un grupo de encuestas
     */
    public function showGroupReport(SurveyGroup $group)
    {
        // Generar reporte completo del grupo
        $report = $this->groupReportGenerator->generate($group);

        return view('admin.reports.group', [
            'group' => $group,
            'report' => $report,
        ]);
    }

    /**
     * Exportar reporte de grupo a PDF
     */
    public function exportGroupReport(SurveyGroup $group)
    {
        // Generar reporte completo del grupo
        $report = $this->groupReportGenerator->generate($group);

        $pdf = Pdf::loadView('admin.reports.group-pdf', [
            'group' => $group,
            'report' => $report,
        ]);

        return $pdf->download('reporte-grupo-' . $group->slug . '-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Exportar reporte de grupo a Excel/CSV
     */
    public function exportGroupReportCsv(SurveyGroup $group)
    {
        $report = $this->groupReportGenerator->generate($group);

        $filename = 'reporte-grupo-' . $group->slug . '-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($group, $report) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, ['REPORTE DE GRUPO: ' . $group->name]);
            fputcsv($file, ['Fecha generación: ' . now()->format('Y-m-d H:i:s')]);
            fputcsv($file, []);

            // Estadísticas básicas
            fputcsv($file, ['ESTADÍSTICAS GENERALES DEL GRUPO']);
            fputcsv($file, ['Métrica', 'Valor']);
            fputcsv($file, ['Total de encuestas', $report['basic_stats']['total_surveys']]);
            fputcsv($file, ['Vistas totales', $report['basic_stats']['total_views']]);
            fputcsv($file, ['Votos enviados', $report['basic_stats']['total_votes_submitted']]);
            fputcsv($file, ['Votos válidos', $report['basic_stats']['valid_votes']]);
            fputcsv($file, ['Votos pendientes revisión', $report['basic_stats']['pending_review']]);
            fputcsv($file, ['Votos rechazados', $report['basic_stats']['rejected_votes']]);
            fputcsv($file, ['Votos no contados', $report['basic_stats']['not_counted_votes']]);
            fputcsv($file, ['Votos duplicados/fraudulentos', $report['basic_stats']['duplicate_or_fraudulent']]);
            fputcsv($file, ['Votantes únicos', $report['basic_stats']['unique_voters']]);
            fputcsv($file, []);

            // Conversión
            fputcsv($file, ['MÉTRICAS DE CONVERSIÓN']);
            fputcsv($file, ['Métrica', 'Porcentaje']);
            fputcsv($file, ['Vistas → Votos', $report['conversion_metrics']['view_to_vote_rate'] . '%']);
            fputcsv($file, ['Votos → Aprobados', $report['conversion_metrics']['vote_approval_rate'] . '%']);
            fputcsv($file, ['Conversión completa', $report['conversion_metrics']['complete_conversion_rate'] . '%']);
            fputcsv($file, []);

            // Estadísticas por encuesta
            fputcsv($file, ['ESTADÍSTICAS POR ENCUESTA']);
            fputcsv($file, ['Título', 'Vistas', 'Votos Válidos', 'Tasa de Conversión']);
            foreach ($report['per_survey_stats'] as $surveyStat) {
                fputcsv($file, [
                    $surveyStat['survey_title'],
                    $surveyStat['views'],
                    $surveyStat['valid_votes'],
                    $surveyStat['conversion_rate'] . '%'
                ]);
            }
            fputcsv($file, []);

            // Estadísticas por pregunta (agregadas)
            fputcsv($file, ['ESTADÍSTICAS AGREGADAS POR PREGUNTA']);
            foreach ($report['question_stats'] as $questionStat) {
                fputcsv($file, []);
                fputcsv($file, ['Pregunta: ' . $questionStat['question_text']]);
                fputcsv($file, ['Total votos: ' . $questionStat['total_votes']]);
                fputcsv($file, ['Opción', 'Votos', 'Porcentaje']);

                foreach ($questionStat['options'] as $option) {
                    fputcsv($file, [
                        $option['option_text'],
                        $option['votes'],
                        $option['percentage'] . '%'
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
