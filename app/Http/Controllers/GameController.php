<?php

namespace App\Http\Controllers;

use App\Enums\CountdownModes;
use App\Enums\GameDifficulties;
use App\Enums\GameModes;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GameController extends Controller
{
    /**
     * Muestra la vista del juego modo contrarreloj
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function gameView(Request $request)
    {
        // Validar que los campos requeridos estén presentes
        if (! $request->mode || ! $request->difficulty || ($request->mode === GameModes::CONTRARELOJ->value() && ! $request->countdown_time)) {
            return redirect()->route('menu.view')->with('error', 'Error: Datos de la run incompletos');
        }

        // Crear la run actual
        $run = $this->createCurrentRun($request->mode, $request->difficulty, $request->countdown_time);

        // Si el modo es contrarreloj, obtener los segundos del enum
        if ($request->mode === GameModes::CONTRARELOJ->value() && $request->countdown_time) {
            $countdownSeconds = CountdownModes::from($request->countdown_time)->seconds();
        }

        // Crear las letras para la matrícula
        $chars = $this->createChars($request->difficulty);

        // Devolver la vista con los datos necesarios
        return view('main.game', [
            'char1' => $chars[0],
            'char2' => $chars[1],
            'char3' => $chars[2],
            'run' => $run,
            'mode' => $request->mode,
            'difficulty' => $request->difficulty,
            'countdown_time' => $countdownSeconds ?? null,
        ]);
    }

    /**
     * Devuelve la vista de modos de cuenta atrás para AJAX
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function countdownModesView()
    {
        return view('components.countdown-modes');
    }

    /**
     * Devuelve la vista de dificultades para AJAX
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function difficultyModesView()
    {
        return view('components.difficulty-modes');
    }

    /**
     * Crea la run actual para el usuario
     *
     * @param  string  $mode  GameModes
     * @param  string  $difficulty  GameDifficulties
     * @param  string  $countdown_time  CountdownModes|null
     * @return Run
     */
    private function createCurrentRun($mode, $difficulty, $countdown_time = null)
    {
        try {
            // Crear la run
            $run = Run::create([
                'user_id' => auth()->id(),
                'score' => 0,
                'mode' => $mode,
                'difficulty' => $difficulty,
                'countdown_time' => $countdown_time ?? null,
                'words_tried' => [],
                'words_error' => [],
                'words_correct' => [],
            ]);

            // Devolver la run
            Log::info('Run creada correctamente');

            return $run;
        } catch (\Exception $e) {
            // Manejo del error
            Log::error('Error creando la run actual: '.$e->getMessage());
            throw new \Exception('Error creando la run actual: '.$e->getMessage());
        }
    }

    /**
     * Obtiene la run actual para el usuario
     *
     * @return Run
     */
    private function getCurrentRun($run_id)
    {
        try {
            // Obtener la run actual
            $run = Run::find($run_id);

            // Si la run no existe, se lanza un error
            if (! $run) {
                Log::error('Run no encontrada');
                throw new \Exception('Run no encontrada');
            }

            // Devolver la run
            Log::info('Run obtenida correctamente');

            return $run;
        } catch (\Exception $e) {
            // Manejo del error
            Log::error('Error obteniendo la run actual: '.$e->getMessage());
            throw new \Exception('Error obteniendo la run actual: '.$e->getMessage());
        }
    }

    /**
     * Crea las letras para la matrícula
     *
     * @param  string  $difficulty  GameDifficulties
     * @return array
     */
    private function createChars($difficulty)
    {
        if ($difficulty == GameDifficulties::FACIL->value()) {
            // Se excluyen las letras Q, W, Y, K, Ñ, Z, X, V
            $chars = ['B', 'C', 'D', 'F', 'G', 'H', 'J', 'L', 'M', 'N', 'P', 'R', 'S', 'T'];
            $char1 = $chars[array_rand($chars)];
            $char2 = $chars[array_rand($chars)];
            $char3 = $chars[array_rand($chars)];
        } else {
            $chars = ['B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', 'Z'];
            $char1 = $chars[array_rand($chars)];
            $char2 = $chars[array_rand($chars)];
            $char3 = $chars[array_rand($chars)];
        }

        // Devolver las letras en mayúsculas
        return [strtoupper($char1), strtoupper($char2), strtoupper($char3)];
    }

    /**
     * Comprueba la palabra y actualiza la puntuación
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkWord(Request $request)
    {
        // Obtener la run actual
        $run = $this->getCurrentRun($request->run_id);

        // Validar la palabra
        $rules = [
            'word' => 'required|string|min:3|max:255',
        ];

        $messages = [
            'word.required' => 'La palabra es requerida',
            'word.string' => 'La palabra debe ser una cadena de texto',
            'word.min' => 'La palabra debe tener al menos 3 caracteres',
            'word.max' => 'La palabra no debe exceder los 255 caracteres',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'chars' => [strtoupper($request->char1), strtoupper($request->char2), strtoupper($request->char3)],
                'score' => $run->score,
            ]);
        }

        // Convertir la palabra a mayúsculas y obtener las letras de la matrícula
        $word = strtoupper($request->word);
        $chars = [strtoupper($request->char1), strtoupper($request->char2), strtoupper($request->char3)];

        // Si la dificultad es difícil, verificar que las letras aparecen en el mismo orden secuencial
        if ($run->difficulty === GameDifficulties::DIFICIL->value()) {
            $plateIndex = 0;
            $lettersInOrder = false;

            for ($i = 0; $i < strlen($word); $i++) {
                if ($plateIndex < count($chars) && $word[$i] === $chars[$plateIndex]) {
                    $plateIndex++;
                    if ($plateIndex === count($chars)) {
                        $lettersInOrder = true;
                        break;
                    }
                }
            }

            if (! $lettersInOrder) {
                // Añadir la palabra incorrecta a la run y restar puntos
                $run->addErrorWord($word);
                $run->removePoints(5);

                // Si la palabra no contiene todas las letras de la matrícula, se devuelve un mensaje de error
                return response()->json([
                    'success' => false,
                    'message' => '¡Fallo! En modo difícil, las letras deben aparecer en el mismo orden. Has perdido 5 puntos.',
                    'chars' => $chars,
                    'score' => $run->score,
                ]);
            }
        }

        // Verificar que la palabra contiene todas las letras de la matrícula
        $requiredCounts = array_count_values($chars);
        $wordCounts = count_chars($word, 1);

        foreach ($requiredCounts as $char => $count) {
            $ascii = ord($char);
            $inWord = isset($wordCounts[$ascii]) ? $wordCounts[$ascii] : 0;
            if ($inWord < $count) {
                $run->addErrorWord($word);
                $run->removePoints(5);

                // Si la palabra no contiene todas las letras de la matrícula, se devuelve un mensaje de error
                return response()->json([
                    'success' => false,
                    'message' => '¡Fallo! La palabra no contiene todas las letras de la matrícula. Has perdido 5 puntos.',
                    'chars' => $chars,
                    'score' => $run->score,
                ]);
            }
        }

        try {
            // Verificar si la palabra existe en el diccionario de la API de la RAE
            $response = Http::withOptions([
                'verify' => false, // Desactivar la verificación del certificado SSL
            ])->get("https://rae-api.com/api/words/{$word}");

            // Obtener los datos de la respuesta
            $data = $response->json();

            // Si la palabra existe en el diccionario de la API de la RAE
            if ($data['ok'] === true) {
                // Añadir la palabra correcta a la run y sumar puntos
                $run->addCorrectWord($word);
                $run->addPoints(10);

                // Crear nuevas letras para la matrícula
                $newChars = $this->createChars($run->difficulty);

                // Devolver la respuesta con las nuevas letras y la puntuación actualizada
                return response()->json([
                    'success' => true,
                    'message' => '¡Palabra válida! Has ganado 10 puntos.',
                    'chars' => $newChars,
                    'score' => $run->score,
                ]);
            } else {
                // Añadir la palabra incorrecta a la run y restar puntos
                $run->addErrorWord($word);
                $run->removePoints(5);

                // Devolver la respuesta con las letras actuales y la puntuación actualizada
                return response()->json([
                    'success' => false,
                    'message' => '¡Fallo! La palabra no existe en el diccionario de la RAE. Has perdido 5 puntos.',
                    'chars' => $chars,
                    'score' => $run->score,
                ]);
            }
        } catch (\Exception $e) {
            // Si hay un error al verificar la palabra, se devuelve un mensaje de error
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar la palabra. Por favor, inténtalo de nuevo.',
                'chars' => $chars,
                'score' => $run->score,
            ]);
        }
    }

    /**
     * Cambia la matrícula
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePlate(Request $request)
    {
        // Obtener la run actual
        $run = $this->getCurrentRun($request->run_id);

        // Obtener las letras actuales de la matrícula
        $chars = [strtoupper($request->char1), strtoupper($request->char2), strtoupper($request->char3)];

        // Añadir las letras a la run y restar puntos
        $run->addCharsSkipped($chars);
        $run->removePoints(5);

        $newChars = $this->createChars($run->difficulty);

        return response()->json([
            'success' => false,
            'message' => '¡Imposible! La matrícula ha cambiado. Has perdido 5 puntos.',
            'chars' => $newChars,
            'score' => $run->score,
        ]);
    }

    /**
     * Reinicia la run
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetRun(Request $request)
    {
        // Obtener la run actual
        $run = $this->getCurrentRun($request->run_id);

        // Si la run está vacía, se elimina de forma permanente
        if (empty($run->words_correct) && empty($run->words_error)) {
            $run->forceDelete();
        } else {
            $run->delete();
        }

        // Crear una nueva run
        $run = $this->createCurrentRun($request->mode, $request->difficulty, $request->countdown_time);

        // Devolver la respuesta con el mensaje de éxito
        return response()->json([
            'success' => true,
            'message' => 'Run reiniciada correctamente',
        ]);
    }
}
