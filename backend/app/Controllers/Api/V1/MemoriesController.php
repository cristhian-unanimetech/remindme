<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\SpotifyMetadataService;
use App\Models\MemoryImageModel;
use App\Models\MemoryModel;
use App\Models\MemoryTagModel;
use App\Models\TagModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class MemoriesController extends ApiController
{
    private MemoryModel $memories;
    private MemoryImageModel $memoryImages;
    private TagModel $tags;
    private MemoryTagModel $memoryTags;
    private SpotifyMetadataService $spotifyMetadata;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->memories        = model(MemoryModel::class);
        $this->memoryImages    = model(MemoryImageModel::class);
        $this->tags            = model(TagModel::class);
        $this->memoryTags      = model(MemoryTagModel::class);
        $this->spotifyMetadata = service('spotifyMetadataService');
    }

    public function index(): ResponseInterface
    {
        $userId = $this->authenticatedUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $search = trim((string) $this->request->getGet('search'));
        $tag    = trim((string) $this->request->getGet('tag'));
        $from   = trim((string) $this->request->getGet('from'));
        $to     = trim((string) $this->request->getGet('to'));

        if (($from !== '' && ! $this->isDate($from)) || ($to !== '' && ! $this->isDate($to))) {
            return $this->validationErrorResponse([
                'date' => 'Los filtros de fecha deben usar el formato YYYY-MM-DD.',
            ]);
        }

        $builder = $this->memories->builder();
        $builder->select('memories.*');
        $builder->where('memories.user_id', $userId);

        if ($search !== '') {
            $builder->groupStart()
                ->like('memories.title', $search)
                ->orLike('memories.content', $search)
                ->groupEnd();
        }

        if ($from !== '') {
            $builder->where('memories.memory_date >=', $from);
        }

        if ($to !== '') {
            $builder->where('memories.memory_date <=', $to);
        }

        if ($tag !== '') {
            $builder->join('memory_tags', 'memory_tags.memory_id = memories.id');
            $builder->join('tags', 'tags.id = memory_tags.tag_id');
            $builder->where('tags.name', $tag);
            $builder->groupBy('memories.id');
        }

        $rows = $builder
            ->orderBy('memories.created_at', 'DESC')
            ->orderBy('memories.id', 'DESC')
            ->get()
            ->getResultArray();

        $data = array_map(fn (array $memory): array => $this->mapMemorySummary($memory), $rows);

        return $this->response->setJSON([
            'data' => $data,
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $userId = $this->authenticatedUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $memory = $this->findUserMemory($id, $userId);
        if ($memory === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => 'Recuerdo no encontrado.',
            ]);
        }

        return $this->response->setJSON([
            'data' => $this->mapMemoryDetail($memory),
        ]);
    }

    public function create(): ResponseInterface
    {
        $userId = $this->authenticatedUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $data = $this->requestData();

        $errors = $this->validateMemoryPayload($data, true);
        $files  = $this->uploadedImages();
        $errors = array_merge($errors, $this->validateUploadedImages($files));

        if ($errors !== []) {
            return $this->validationErrorResponse($errors);
        }

        $spotify = $this->spotifyMetadata->resolve((string) ($data['spotify_url'] ?? ''));

        $memoryId = $this->memories->insert([
            'user_id'     => $userId,
            'title'       => trim((string) $data['title']),
            'content'     => trim((string) $data['content']),
            'memory_date' => (string) $data['memory_date'],
            'spotify_url' => $spotify['spotify_url'] ?? null,
            'song_title'  => $spotify['song_title'] ?? null,
            'artist_name' => $spotify['artist_name'] ?? null,
            'album_name'  => $spotify['album_name'] ?? null,
            'cover_url'   => $spotify['cover_url'] ?? null,
            'embed_url'   => $spotify['embed_url'] ?? null,
            'embed_html'  => $spotify['embed_html'] ?? null,
            'mood_color'  => $this->nullableString($data['mood_color'] ?? null),
        ], true);

        if (! is_int($memoryId)) {
            return $this->response->setStatusCode(500)->setJSON([
                'message' => 'No se ha podido crear el recuerdo.',
            ]);
        }

        $this->syncTags($memoryId, $this->parseTags($data['tags'] ?? null));
        $this->saveUploadedImages($memoryId, $files);

        $memory = $this->findUserMemory($memoryId, $userId);
        if ($memory === null) {
            return $this->response->setStatusCode(500)->setJSON([
                'message' => 'Recuerdo no encontrado tras la creación.',
            ]);
        }

        return $this->response->setStatusCode(201)->setJSON([
            'data' => $this->mapMemoryDetail($memory),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $userId = $this->authenticatedUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $memory = $this->findUserMemory($id, $userId);
        if ($memory === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => 'Recuerdo no encontrado.',
            ]);
        }

        $data = $this->requestData();

        $errors = $this->validateMemoryPayload($data, false);
        $files  = $this->uploadedImages();
        $errors = array_merge($errors, $this->validateUploadedImages($files));

        if ($errors !== []) {
            return $this->validationErrorResponse($errors);
        }

        $spotify = $this->spotifyMetadata->resolve((string) ($data['spotify_url'] ?? ''));

        $this->memories->update($id, [
            'title'       => trim((string) $data['title']),
            'content'     => trim((string) $data['content']),
            'memory_date' => (string) $data['memory_date'],
            'spotify_url' => $spotify['spotify_url'] ?? null,
            'song_title'  => $spotify['song_title'] ?? null,
            'artist_name' => $spotify['artist_name'] ?? null,
            'album_name'  => $spotify['album_name'] ?? null,
            'cover_url'   => $spotify['cover_url'] ?? null,
            'embed_url'   => $spotify['embed_url'] ?? null,
            'embed_html'  => $spotify['embed_html'] ?? null,
            'mood_color'  => $this->nullableString($data['mood_color'] ?? null),
        ]);

        if (array_key_exists('tags', $data)) {
            $this->syncTags($id, $this->parseTags($data['tags']));
        }

        if (array_key_exists('remove_image_ids', $data)) {
            $this->removeImages($id, $this->parseIntegerList($data['remove_image_ids']));
        }

        $this->saveUploadedImages($id, $files);

        $updated = $this->findUserMemory($id, $userId);
        if ($updated === null) {
            return $this->response->setStatusCode(500)->setJSON([
                'message' => 'No se ha podido actualizar el recuerdo.',
            ]);
        }

        return $this->response->setJSON([
            'data' => $this->mapMemoryDetail($updated),
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $userId = $this->authenticatedUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $memory = $this->findUserMemory($id, $userId);
        if ($memory === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => 'Recuerdo no encontrado.',
            ]);
        }

        $images = $this->memoryImages->where('memory_id', $id)->findAll();
        foreach ($images as $image) {
            $this->deleteImageFile((string) ($image['image_path'] ?? ''));
        }

        $this->memories->delete($id);

        return $this->response->setJSON([
            'message' => 'Recuerdo eliminado.',
        ]);
    }

    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(): array
    {
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));

        if (str_contains($contentType, 'application/json')) {
            try {
                $json = $this->request->getJSON(true);
                if (is_array($json) && $json !== []) {
                    return $json;
                }
            } catch (\Throwable) {
            }
        }

        $post = $this->request->getPost();

        return is_array($post) ? $post : [];
    }

    /**
     * @return array<string, string>
     */
    private function validateMemoryPayload(array $data, bool $isCreate): array
    {
        $rules = [
            'title'       => 'required|max_length[150]',
            'content'     => 'required',
            'memory_date' => 'required|valid_date[Y-m-d]',
            'spotify_url' => 'permit_empty|max_length[255]',
            'mood_color'  => 'permit_empty|max_length[30]',
        ];

        if (! $this->validateData($data, $rules)) {
            $errors = $this->validator->getErrors();
        } else {
            $errors = [];
        }

        $spotifyUrl = trim((string) ($data['spotify_url'] ?? ''));
        if ($spotifyUrl !== '' && ! $this->spotifyMetadata->isValidSpotifyUrl($spotifyUrl)) {
            $errors['spotify_url'] = 'Spotify URL inválida. Usa https://open.spotify.com/...';
        }

        if (! $isCreate && $data === []) {
            $errors['payload'] = 'No se ha proporcionado ningún payload.';
        }

        return $errors;
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedImages(): array
    {
        $files = $this->request->getFileMultiple('images');
        if (! is_array($files) || $files === []) {
            $files = $this->request->getFileMultiple('images[]');
        }

        $result = [];
        if (is_array($files)) {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                if (! $file->isValid() || $file->hasMoved()) {
                    continue;
                }

                $result[] = $file;
            }
        }

        if ($result === []) {
            $single = $this->request->getFile('images');
            if (! $single instanceof UploadedFile) {
                $single = $this->request->getFile('images[]');
            }

            if ($single instanceof UploadedFile && $single->isValid() && ! $single->hasMoved()) {
                $result[] = $single;
            }
        }

        return $result;
    }

    /**
     * @param list<UploadedFile> $files
     *
     * @return array<string, string>
     */
    private function validateUploadedImages(array $files): array
    {
        $errors = [];
        if (count($files) > 8) {
            $errors['images'] = 'Puedes subir 8 imágenes por recuerdo.';

            return $errors;
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        foreach ($files as $index => $file) {
            $extension = strtolower((string) $file->getExtension());
            if (! in_array($extension, $allowedExtensions, true)) {
                $errors['images_' . $index] = 'Extensión de imagen no permitida.';
                continue;
            }

            if ($file->getSize() > 8 * 1024 * 1024) {
                $errors['images_' . $index] = 'Cada imágen debe pesar menos de 8mb.';
            }
        }

        return $errors;
    }

    /**
     * @param list<UploadedFile> $files
     */
    private function saveUploadedImages(int $memoryId, array $files): void
    {
        if ($files === []) {
            return;
        }

        $uploadDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'memories';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $hasPrimary = $this->memoryImages
            ->where('memory_id', $memoryId)
            ->where('is_primary', 1)
            ->first() !== null;

        foreach ($files as $file) {
            $newName = $file->getRandomName();
            $file->move($uploadDir, $newName);

            $this->memoryImages->insert([
                'memory_id'  => $memoryId,
                'image_path' => '/uploads/memories/' . $newName,
                'is_primary' => $hasPrimary ? 0 : 1,
            ]);

            $hasPrimary = true;
        }
    }

    /**
     * @return list<string>
     */
    private function parseTags(mixed $input): array
    {
        $values = [];

        if (is_array($input)) {
            $values = $input;
        } elseif (is_string($input)) {
            $raw = trim($input);
            if ($raw === '') {
                return [];
            }

            if (str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $values = $decoded;
                }
            }

            if ($values === []) {
                $values = explode(',', $raw);
            }
        }

        $normalized = [];
        foreach ($values as $tag) {
            $clean = trim((string) $tag);
            if ($clean === '') {
                continue;
            }

            $key = mb_strtolower($clean);
            if (! isset($normalized[$key])) {
                $normalized[$key] = $clean;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param list<string> $tags
     */
    private function syncTags(int $memoryId, array $tags): void
    {
        $tagIds = [];
        foreach ($tags as $tagName) {
            $existing = $this->tags->where('name', $tagName)->first();
            if (is_array($existing)) {
                $tagIds[] = (int) $existing['id'];
                continue;
            }

            $newTagId = $this->tags->insert(['name' => $tagName], true);
            if (is_int($newTagId)) {
                $tagIds[] = $newTagId;
            }
        }

        $tagIds = array_values(array_unique($tagIds));
        $existingRows = $this->memoryTags->where('memory_id', $memoryId)->findAll();
        $existingIds = array_map(static fn (array $row): int => (int) $row['tag_id'], $existingRows);

        foreach ($existingRows as $row) {
            if (! in_array((int) $row['tag_id'], $tagIds, true)) {
                $this->memoryTags->delete((int) $row['id']);
            }
        }

        foreach ($tagIds as $tagId) {
            if (! in_array($tagId, $existingIds, true)) {
                $this->memoryTags->insert([
                    'memory_id' => $memoryId,
                    'tag_id'    => $tagId,
                ]);
            }
        }
    }

    /**
     * @return list<int>
     */
    private function parseIntegerList(mixed $input): array
    {
        $values = [];

        if (is_array($input)) {
            $values = $input;
        } elseif (is_string($input)) {
            $raw = trim($input);
            if ($raw === '') {
                return [];
            }

            if (str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $values = $decoded;
                }
            }

            if ($values === []) {
                $values = explode(',', $raw);
            }
        }

        $numbers = [];
        foreach ($values as $value) {
            $number = (int) $value;
            if ($number > 0) {
                $numbers[] = $number;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @param list<int> $imageIds
     */
    private function removeImages(int $memoryId, array $imageIds): void
    {
        if ($imageIds === []) {
            return;
        }

        $rows = $this->memoryImages
            ->where('memory_id', $memoryId)
            ->whereIn('id', $imageIds)
            ->findAll();

        foreach ($rows as $row) {
            $this->deleteImageFile((string) ($row['image_path'] ?? ''));
            $this->memoryImages->delete((int) $row['id']);
        }

        $primary = $this->memoryImages
            ->where('memory_id', $memoryId)
            ->where('is_primary', 1)
            ->first();

        if ($primary === null) {
            $first = $this->memoryImages
                ->where('memory_id', $memoryId)
                ->orderBy('id', 'ASC')
                ->first();

            if (is_array($first)) {
                $this->memoryImages->update((int) $first['id'], ['is_primary' => 1]);
            }
        }
    }

    private function deleteImageFile(string $imagePath): void
    {
        $imagePath = trim($imagePath);
        if ($imagePath === '') {
            return;
        }

        $fullPath = FCPATH . ltrim($imagePath, '/\\');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserMemory(int $memoryId, int $userId): ?array
    {
        $memory = $this->memories
            ->where('id', $memoryId)
            ->where('user_id', $userId)
            ->first();

        return is_array($memory) ? $memory : null;
    }

    /**
     * @return list<string>
     */
    private function tagsForMemory(int $memoryId): array
    {
        $rows = $this->tags
            ->select('tags.name')
            ->join('memory_tags', 'memory_tags.tag_id = tags.id')
            ->where('memory_tags.memory_id', $memoryId)
            ->orderBy('tags.name', 'ASC')
            ->findAll();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * @return list<array{id: int, url: string, isPrimary: bool}>
     */
    private function imagesForMemory(int $memoryId): array
    {
        $rows = $this->memoryImages
            ->where('memory_id', $memoryId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return array_map(
            static fn (array $row): array => [
                'id'        => (int) $row['id'],
                'url'       => (string) $row['image_path'],
                'isPrimary' => (bool) $row['is_primary'],
            ],
            $rows
        );
    }

    private function primaryImageUrl(int $memoryId): ?string
    {
        $row = $this->memoryImages
            ->where('memory_id', $memoryId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('id', 'ASC')
            ->first();

        if (! is_array($row)) {
            return null;
        }

        return (string) $row['image_path'];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMemorySummary(array $memory): array
    {
        $content = trim((string) $memory['content']);

        return [
            'id'              => (int) $memory['id'],
            'title'           => (string) $memory['title'],
            'contentPreview'  => mb_substr($content, 0, 180),
            'memoryDate'      => (string) $memory['memory_date'],
            'moodColor'       => $this->nullableString($memory['mood_color'] ?? null),
            'tags'            => $this->tagsForMemory((int) $memory['id']),
            'primaryImageUrl' => $this->primaryImageUrl((int) $memory['id']),
            'spotify'         => [
                'spotifyUrl' => $this->nullableString($memory['spotify_url'] ?? null),
                'songTitle'  => $this->nullableString($memory['song_title'] ?? null),
                'artistName' => $this->nullableString($memory['artist_name'] ?? null),
                'coverUrl'   => $this->nullableString($memory['cover_url'] ?? null),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMemoryDetail(array $memory): array
    {
        return [
            'id'         => (int) $memory['id'],
            'title'      => (string) $memory['title'],
            'content'    => (string) $memory['content'],
            'memoryDate' => (string) $memory['memory_date'],
            'moodColor'  => $this->nullableString($memory['mood_color'] ?? null),
            'tags'       => $this->tagsForMemory((int) $memory['id']),
            'images'     => $this->imagesForMemory((int) $memory['id']),
            'spotify'    => [
                'spotifyUrl' => $this->nullableString($memory['spotify_url'] ?? null),
                'songTitle'  => $this->nullableString($memory['song_title'] ?? null),
                'artistName' => $this->nullableString($memory['artist_name'] ?? null),
                'albumName'  => $this->nullableString($memory['album_name'] ?? null),
                'coverUrl'   => $this->nullableString($memory['cover_url'] ?? null),
                'embedUrl'   => $this->nullableString($memory['embed_url'] ?? null),
                'embedHtml'  => $this->nullableString($memory['embed_html'] ?? null),
            ],
            'createdAt'  => $this->nullableString($memory['created_at'] ?? null),
            'updatedAt'  => $this->nullableString($memory['updated_at'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}


