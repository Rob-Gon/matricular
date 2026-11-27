<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Achievement;

class AchievementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'Pasito a pasito...',
                'description' => 'Completa tu primer juego',
            ],
            [
                'name' => 'El menos picado',
                'description' => 'Juega 5 juegos consecutivos',
            ],
            [
                'name' => 'Deberías tocar pasto',
                'description' => 'Juega 25 juegos en un día',
            ],
            [
                'name' => 'Matriculando fuerte',
                'description' => 'Consigue 500 puntos en un solo juego',
            ],
            [
                'name' => 'Matricúlame esta',
                'description' => 'Consigue 250 puntos en un solo juego (Modo Difícil)',
            ],
            [
                'name' => 'Bachillerato de letras',
                'description' => 'Acierta 5 sustantivos seguidos',
            ],
            [
                'name' => 'Bachillerato de artes',
                'description' => 'Acierta 5 adjetivos seguidos',
            ],
            [
                'name' => 'Bachillerato de letras',
                'description' => 'Acierta 5 verbos seguidos',
            ],
            [
                'name' => '!Palíndromo!',
                'description' => 'Acierta una palabra que sea un palíndromo',
            ],
            [
                'name' => '!Calambur!',
                'description' => 'Acierta una palabra que sea un calambur',
            ],
            [
                'name' => '!Palíndromo!',
                'description' => 'Acierta una palabra que sea un palíndromo',
            ],
            [
                'name' => 'Platino Compulsivo',
                'description' => 'Consigue todos los logros',
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::firstOrCreate(['name' => $achievement['name']],$achievemen);
        }
    }
}
