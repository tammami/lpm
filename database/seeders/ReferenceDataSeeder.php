<?php

namespace Database\Seeders;

use App\Models\AnswerScale;
use App\Models\ClassificationScheme;
use Illuminate\Database\Seeder;

/**
 * Konfigurasi awal: skema klasifikasi skor & preset skala jawaban.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $scheme = ClassificationScheme::query()->updateOrCreate(['name' => 'Skala 4 (Standar LPM)'], [
            'scale_min' => 0,
            'scale_max' => 4,
            'description' => 'Klasifikasi awal sesuai BRD. Dapat disesuaikan LPM.',
            'is_default' => true,
        ]);

        $scheme->classifications()->delete();
        $scheme->classifications()->createMany([
            ['min_score' => 0, 'max_score' => 1, 'label' => 'Sangat Rendah', 'color' => 'danger', 'sort_order' => 1],
            ['min_score' => 1, 'max_score' => 2, 'label' => 'Rendah', 'color' => 'warning', 'sort_order' => 2],
            ['min_score' => 2, 'max_score' => 3, 'label' => 'Baik', 'color' => 'info', 'sort_order' => 3],
            ['min_score' => 3, 'max_score' => 4, 'label' => 'Sangat Baik', 'color' => 'success', 'sort_order' => 4],
        ]);

        $five = ClassificationScheme::query()->updateOrCreate(['name' => 'Skala 5'], [
            'scale_min' => 1,
            'scale_max' => 5,
            'description' => 'Untuk instrumen dengan Likert 1–5.',
            'is_default' => false,
        ]);

        $five->classifications()->delete();
        $five->classifications()->createMany([
            ['min_score' => 1, 'max_score' => 1.8, 'label' => 'Sangat Kurang', 'color' => 'danger', 'sort_order' => 1],
            ['min_score' => 1.8, 'max_score' => 2.6, 'label' => 'Kurang', 'color' => 'warning', 'sort_order' => 2],
            ['min_score' => 2.6, 'max_score' => 3.4, 'label' => 'Cukup', 'color' => 'neutral', 'sort_order' => 3],
            ['min_score' => 3.4, 'max_score' => 4.2, 'label' => 'Baik', 'color' => 'info', 'sort_order' => 4],
            ['min_score' => 4.2, 'max_score' => 5, 'label' => 'Sangat Baik', 'color' => 'success', 'sort_order' => 5],
        ]);

        $scales = [
            [
                'name' => 'Likert 4 — Kualitas',
                'question_type' => 'likert',
                'options' => [
                    ['label' => 'Kurang', 'value' => '1', 'score' => 1],
                    ['label' => 'Cukup', 'value' => '2', 'score' => 2],
                    ['label' => 'Baik', 'value' => '3', 'score' => 3],
                    ['label' => 'Sangat Baik', 'value' => '4', 'score' => 4],
                ],
            ],
            [
                'name' => 'Likert 4 — Persetujuan',
                'question_type' => 'likert',
                'options' => [
                    ['label' => 'Tidak Setuju', 'value' => '1', 'score' => 1],
                    ['label' => 'Kurang Setuju', 'value' => '2', 'score' => 2],
                    ['label' => 'Setuju', 'value' => '3', 'score' => 3],
                    ['label' => 'Sangat Setuju', 'value' => '4', 'score' => 4],
                ],
            ],
            [
                'name' => 'Likert 5 — Kepuasan',
                'question_type' => 'likert',
                'options' => [
                    ['label' => 'Sangat Tidak Puas', 'value' => '1', 'score' => 1],
                    ['label' => 'Tidak Puas', 'value' => '2', 'score' => 2],
                    ['label' => 'Cukup Puas', 'value' => '3', 'score' => 3],
                    ['label' => 'Puas', 'value' => '4', 'score' => 4],
                    ['label' => 'Sangat Puas', 'value' => '5', 'score' => 5],
                ],
            ],
            [
                'name' => 'Kepatuhan AMI',
                'question_type' => 'single_choice',
                'options' => [
                    ['label' => 'Sesuai (Compliant)', 'value' => 'compliant', 'score' => 4],
                    ['label' => 'Sesuai Sebagian (Partially Compliant)', 'value' => 'partial', 'score' => 2],
                    ['label' => 'Tidak Sesuai (Non-Compliant)', 'value' => 'non_compliant', 'score' => 0],
                    ['label' => 'Tidak Berlaku (N/A)', 'value' => 'na', 'score' => null],
                ],
            ],
            [
                'name' => 'Ya / Tidak',
                'question_type' => 'yes_no',
                'options' => [
                    ['label' => 'Ya', 'value' => 'yes', 'score' => 4],
                    ['label' => 'Tidak', 'value' => 'no', 'score' => 0],
                ],
            ],
        ];

        foreach ($scales as $scale) {
            AnswerScale::query()->updateOrCreate(['name' => $scale['name']], $scale);
        }
    }
}
