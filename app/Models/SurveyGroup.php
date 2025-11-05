<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'restrict_voting',
    ];

    protected $casts = [
        'restrict_voting' => 'boolean',
    ];

    /**
     * Obtener todas las encuestas de este grupo
     */
    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    /**
     * Verificar si un usuario (por fingerprint) ya votó en alguna encuesta del grupo
     */
    public function hasVotedInGroup(string $fingerprint): bool
    {
        if (!$this->restrict_voting) {
            return false;
        }

        return Vote::whereHas('question.survey', function ($query) {
                $query->where('survey_group_id', $this->id);
            })
            ->where('fingerprint', $fingerprint)
            ->where('is_valid', true)
            ->exists();
    }

    /**
     * Obtener la encuesta en la que el usuario ya votó (si existe)
     */
    public function getVotedSurvey(string $fingerprint): ?Survey
    {
        if (!$this->restrict_voting) {
            return null;
        }

        $vote = Vote::whereHas('question.survey', function ($query) {
                $query->where('survey_group_id', $this->id);
            })
            ->where('fingerprint', $fingerprint)
            ->where('is_valid', true)
            ->with('question.survey')
            ->first();

        return $vote?->question?->survey;
    }
}
