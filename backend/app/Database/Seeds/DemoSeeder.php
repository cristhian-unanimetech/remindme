<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $existing = $this->db->table('users')->where('email', 'demo@remindme.com')->get()->getRowArray();
        if (is_array($existing)) {
            echo "La cuenta demo ya existe. Ejecuta el seeder solo una vez.\n";
            return;
        }

        $this->db->table('users')->insert([
            'name'       => 'Demo',
            'email'      => 'demo@remindme.com',
            'password'   => password_hash('Demo1234!', PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();

        $uploadsDir = FCPATH . 'uploads/memories/';
        if (! is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $images = $this->copyAssetImages($uploadsDir);

        $spotify = [
            'song_title'  => "Helena's Theme",
            'artist_name' => 'John Williams',
            'album_name'  => 'Indiana Jones and the Dial of Destiny (Original Motion Picture Soundtrack)',
            'cover_url'   => 'https://image-cdn-fa.spotifycdn.com/image/ab67616d00001e02b5a4c6c343c566f5e3f97905',
            'spotify_url' => 'https://open.spotify.com/track/2yRw4iGvp34m5lB9kwG9Te',
            'embed_url'   => 'https://open.spotify.com/embed/track/2yRw4iGvp34m5lB9kwG9Te',
            'embed_html'  => '<iframe style="border-radius: 12px" width="100%" height="152" title="Spotify Embed: Helena\'s Theme" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy" src="https://open.spotify.com/embed/track/2yRw4iGvp34m5lB9kwG9Te?utm_source=oembed"></iframe>',
        ];

        $noSpotify = [
            'song_title'  => null,
            'artist_name' => null,
            'album_name'  => null,
            'cover_url'   => null,
            'spotify_url' => null,
            'embed_url'   => null,
            'embed_html'  => null,
        ];

        $memories = [
            [
                'title'       => 'El Monasterio de Petra',
                'content'     => "Caminamos más de dos horas por el desfiladero antes de verlo aparecer. Y cuando lo hizo, tallado directamente en la roca rosada, no hubo palabras.\n\nEs de esas cosas que fotografías pero sabes que ninguna foto va a contar lo que se siente al estar ahí de verdad.",
                'memory_date' => '2024-03-22',
                'mood_color'  => '#d97706',
                'tags'        => ['viaje', 'jordania', 'petra', 'historia'],
                'image'       => $images[0] ?? null,
                'spotify'     => $spotify,
            ],
            [
                'title'       => 'Frente al Taj Mahal',
                'content'     => "Lo había visto mil veces en fotos y aun así me dejó sin respiración. El mármol blanco cambia de color con la luz y a primera hora de la mañana tiene algo casi irreal.\n\nMe quedé sentado frente a él mucho más tiempo del que tenía previsto. Algunas cosas merecen eso.",
                'memory_date' => '2024-01-10',
                'mood_color'  => '#60a5fa',
                'tags'        => ['viaje', 'india', 'taj-mahal', 'maravilla'],
                'image'       => $images[1] ?? null,
                'spotify'     => $spotify,
            ],
            [
                'title'       => 'Una tarde en el Coliseo',
                'content'     => "Roma tiene algo que te aplasta con su historia en cada esquina, pero el Coliseo es distinto. Te para en seco.\n\nPensar que dos mil años atrás ese mismo espacio estaba lleno de gente es una sensación que no termina de encajar en la cabeza.",
                'memory_date' => '2023-10-05',
                'mood_color'  => '#b45309',
                'tags'        => ['viaje', 'roma', 'italia', 'historia', 'coliseo'],
                'image'       => $images[2] ?? null,
                'spotify'     => $spotify,
            ],
            [
                'title'       => 'Noche de lluvia y reflexión',
                'content'     => "A veces la lluvia hace que todo encaje. Miraba las gotas resbalar por el cristal y pensaba en cuánto habían cambiado las cosas en el último año.\n\nNo todo fue fácil, pero aquí estoy. Y eso ya dice mucho.",
                'memory_date' => '2024-10-22',
                'mood_color'  => '#6366f1',
                'tags'        => ['lluvia', 'reflexion', 'calma'],
                'image'       => null,
                'spotify'     => $noSpotify,
            ],
            [
                'title'       => 'Machu Picchu entre las nubes',
                'content'     => "Subimos en tren desde Aguas Calientes con niebla baja y no sabíamos si íbamos a ver algo. Luego se abrió el cielo y apareció la ciudadela entera.\n\nLas ruinas incas rodeadas de montañas verdes y nubes moviéndose despacio. Un sitio que parece inventado y existe de verdad.",
                'memory_date' => '2024-05-18',
                'mood_color'  => '#16a34a',
                'tags'        => ['viaje', 'peru', 'machu-picchu', 'inca', 'aventura'],
                'image'       => $images[3] ?? null,
                'spotify'     => $spotify,
            ],
            [
                'title'       => 'Primera semana en la universidad',
                'content'     => "Todo era nuevo: el campus, los compañeros, los profesores, incluso el camino al aula. Me perdí dos veces el primer día.\n\nPero a final de semana ya tenía grupo de estudio y había encontrado la cafetería buena. Las cosas siempre encajan.",
                'memory_date' => '2023-09-11',
                'mood_color'  => '#ff5d73',
                'tags'        => ['universidad', 'estudios', 'nueva etapa'],
                'image'       => null,
                'spotify'     => $noSpotify,
            ],
            [
                'title'       => 'Escapada a la montaña',
                'content'     => "Salimos a las cinco de la mañana para ver el amanecer desde la cima. Hacía frío, mucho frío, pero cuando la luz naranja empezó a cubrir el valle entendí por qué valía la pena.\n\nLuego desayunamos bocadillos fríos sentados en piedras. Lo mejor del año sin duda.",
                'memory_date' => '2024-08-20',
                'mood_color'  => '#84cc16',
                'tags'        => ['naturaleza', 'aventura', 'montana', 'amigos'],
                'image'       => $images[4] ?? null,
                'spotify'     => $noSpotify,
            ],
            [
                'title'       => 'Nochevieja en familia',
                'content'     => "Las doce uvas de siempre, las bromas de siempre, los mismos abrazos de siempre. Y aun así, cada año es distinto porque nosotros somos distintos.\n\nBrindamos por lo que fue y por lo que viene. Ojalá siempre haya una mesa así.",
                'memory_date' => '2023-12-31',
                'mood_color'  => '#8b5cf6',
                'tags'        => ['familia', 'año nuevo', 'celebracion', 'tradicion'],
                'image'       => null,
                'spotify'     => $noSpotify,
            ],
        ];

        foreach ($memories as $data) {
            $this->db->table('memories')->insert([
                'user_id'     => $userId,
                'title'       => $data['title'],
                'content'     => $data['content'],
                'memory_date' => $data['memory_date'],
                'mood_color'  => $data['mood_color'],
                'spotify_url' => $data['spotify']['spotify_url'],
                'song_title'  => $data['spotify']['song_title'],
                'artist_name' => $data['spotify']['artist_name'],
                'album_name'  => $data['spotify']['album_name'],
                'cover_url'   => $data['spotify']['cover_url'],
                'embed_url'   => $data['spotify']['embed_url'],
                'embed_html'  => $data['spotify']['embed_html'],
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

            $memoryId = (int) $this->db->insertID();

            foreach ($data['tags'] as $tagName) {
                $tag = $this->db->table('tags')->where('name', $tagName)->get()->getRowArray();

                if (! is_array($tag)) {
                    $this->db->table('tags')->insert([
                        'name'       => $tagName,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $tagId = (int) $this->db->insertID();
                } else {
                    $tagId = (int) $tag['id'];
                }

                $this->db->table('memory_tags')->insert([
                    'memory_id' => $memoryId,
                    'tag_id'    => $tagId,
                ]);
            }

            if ($data['image'] !== null) {
                $this->db->table('memory_images')->insert([
                    'memory_id'  => $memoryId,
                    'image_path' => $data['image'],
                    'is_primary' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        echo "Seeder completado.\n";
        echo "Usuario: demo@remindme.com\n";
        echo "Contraseña: Demo1234!\n";
    }

    private function copyAssetImages(string $uploadsDir): array
    {
        $assetsDir = __DIR__ . '/assets/';
        $assets    = ['sample1.jpg', 'sample2.jpg', 'sample3.jpg', 'sample4.jpg'];
        $paths     = [];

        foreach ($assets as $asset) {
            $src = $assetsDir . $asset;
            if (! file_exists($src)) {
                continue;
            }

            $ext      = pathinfo($asset, PATHINFO_EXTENSION);
            $filename = 'demo_' . uniqid() . '.' . $ext;
            $dest     = $uploadsDir . $filename;

            copy($src, $dest);

            $paths[] = '/uploads/memories/' . $filename;
        }

        return $paths;
    }
}
