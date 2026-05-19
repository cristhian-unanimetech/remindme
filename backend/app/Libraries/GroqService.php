<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

class GroqService
{
    private const BASE_URL = 'https://api.groq.com/openai/v1/chat/completions';

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) env('groq.apiKey', '');
        $this->model  = (string) env('groq.model', 'llama-3.1-8b-instant');
    }

    public function improveText(string $text): string
    {
        $prompt = implode("\n\n", [
            'Eres un asistente que mejora la redacción de textos personales tipo diario.',
            'Mejora el siguiente texto manteniendo el tono personal y los detalles originales.',
            'Devuelve unicamente el texto mejorado, sin explicaciones ni comentarios adicionales.',
            $text,
        ]);

        return $this->generate($prompt);
    }

    public function improveTitle(string $title, string $content = ''): string
    {
        $context = $content !== '' ? "\n\nContenido de la entrada:\n" . $content : '';
        $prompt  = implode("\n\n", [
            'Eres un asistente que crea titulos evocadores para entradas de diario personal.',
            'Crea un titulo corto y evocador (maximo 8 palabras) para esta entrada.',
            'Devuelve unicamente el titulo, sin comillas, sin puntuacion final y sin explicaciones.',
            'Titulo actual: ' . $title . $context,
        ]);

        return $this->generate($prompt);
    }

    public function suggestTags(string $title, string $content): string
    {
        $prompt = implode("\n\n", [
            'Eres un asistente que sugiere etiquetas para entradas de diario personal.',
            'Sugiere entre 3 y 5 etiquetas relevantes y cortas en español.',
            'Devuelve unicamente las etiquetas separadas por comas, en minusculas, sin puntuacion adicional.',
            'Titulo: ' . $title,
            'Contenido: ' . $content,
        ]);

        return $this->generate($prompt);
    }

    private function generate(string $prompt): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Groq API key no configurada.');
        }

        $body = json_encode([
            'model'       => $this->model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
            'max_tokens'  => 512,
        ]);

        $client   = \Config\Services::curlrequest();
        $response = $client->post(self::BASE_URL, [
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'body'        => $body,
            'timeout'     => 20,
            'verify'      => false,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $data       = json_decode($response->getBody(), true);

        if ($statusCode !== 200) {
            $apiMessage = $data['error']['message'] ?? ('HTTP ' . $statusCode);
            throw new RuntimeException('Groq: ' . $apiMessage);
        }

        $text = $data['choices'][0]['message']['content'] ?? '';

        if ((string) $text === '') {
            throw new RuntimeException('Groq no devolvio ninguna sugerencia.');
        }

        return trim((string) $text);
    }
}
