<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Statamic\Facades\Stache;

/** PostgreSQL is authoritative; native CMS/filesystem APIs use a local mirror. */
class HostedFiles
{
    public function locked(callable $callback): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Durable files require PostgreSQL.');
        }

        return DB::transaction(function () use ($callback) {
            // A slow or stalled instance must not hold every request indefinitely.
            DB::statement("SET LOCAL lock_timeout = '5s'");
            // Transaction-level locks also work with transaction-pooled connections.
            DB::select('select pg_advisory_xact_lock(?)', [1279438171]);

            return $callback();
        });
    }

    public function initialize(): bool
    {
        return $this->locked(function () {
            if (DB::table('hosted_file_states')->where('id', 1)->exists()) {
                $this->restore(true);

                return false;
            }
            DB::table('hosted_file_states')->insert(['id' => 1, 'epoch' => (string) Str::uuid(), 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $this->capture();

            return true;
        });
    }

    public function invalidate(): void
    {
        $marker = config('hosted.marker');
        if (is_file($marker)) {
            unlink($marker);
        }
    }

    private function roots(): array
    {
        $roots = config('hosted.roots');
        foreach ($roots as $root) {
            if (is_link($root)) {
                throw new RuntimeException('Managed file roots cannot be symbolic links.');
            }
            if (! is_dir($root) && ! mkdir($root, 0750, true) && ! is_dir($root)) {
                throw new RuntimeException('Managed file roots must be writable.');
            }
        }

        return $roots;
    }

    private function safePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, ':') || str_contains($path, "\0") || strlen($path) > 1024) {
            throw new RuntimeException('Invalid managed file path.');
        }
        foreach (explode('/', $path) as $part) {
            if (in_array($part, ['', '.', '..'], true)) {
                throw new RuntimeException('Invalid managed file path.');
            }
        }
    }

    private function scan(array $roots, bool $hash): array
    {
        $entries = [];
        $total = 0;
        foreach ($roots as $kind => $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                if ($file->isLink() || (! $file->isDir() && ! $file->isFile())) {
                    throw new RuntimeException('Unsupported managed file type.');
                }
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($root, '/\\')) + 1));
                $this->safePath($path);
                $directory = $file->isDir();
                $size = $directory ? 0 : $file->getSize();
                $total += $size;
                if ($hash && ($size > config('hosted.max_file_bytes') || $total > config('hosted.quota_bytes'))) {
                    throw new RuntimeException('Durable file size or storage quota exceeded.');
                }
                $entries[$kind.'/'.$path] = ['root' => $kind, 'path' => $path, 'is_directory' => $directory, 'size' => $size, 'checksum' => $hash && ! $directory ? hash_file('sha256', $file->getPathname()) : null];
                if ($hash && count($entries) > config('hosted.max_files')) {
                    throw new RuntimeException('Durable file count limit exceeded.');
                }
            }
        }

        return $entries;
    }

    /** Call while locked, after successful native CMS or upload mutations. */
    public function capture(): void
    {
        $roots = $this->roots();
        $current = $this->scan($roots, true);
        $stored = DB::table('hosted_files')->select('id', 'root', 'path', 'is_directory', 'size', 'checksum')->get()->keyBy(fn ($row) => $row->root.'/'.$row->path);
        $changed = false;
        foreach ($stored as $key => $row) {
            if (! isset($roots[$row->root])) {
                throw new RuntimeException('An existing durable root is not configured.');
            }
            if (! isset($current[$key])) {
                DB::table('hosted_files')->where('id', $row->id)->delete();
                $changed = true;
            }
        }
        foreach ($current as $key => $entry) {
            $old = $stored->get($key);
            if ($old && (bool) $old->is_directory === $entry['is_directory'] && $old->checksum === $entry['checksum'] && (int) $old->size === $entry['size']) {
                continue;
            }
            $payload = null;
            if (! $entry['is_directory']) {
                $bytes = file_get_contents($roots[$entry['root']].'/'.$entry['path']);
                if ($bytes === false || hash('sha256', $bytes) !== $entry['checksum']) {
                    throw new RuntimeException('Managed files changed during persistence.');
                }
                $payload = base64_encode($bytes);
            }
            DB::table('hosted_files')->upsert([array_merge($entry, ['path_hash' => hash('sha256', $entry['path']), 'payload' => $payload, 'created_at' => now(), 'updated_at' => now()])], ['root', 'path_hash'], ['path', 'is_directory', 'size', 'checksum', 'payload', 'updated_at']);
            $changed = true;
        }
        if ($changed) {
            DB::table('hosted_file_states')->where('id', 1)->increment('revision', 1, ['updated_at' => now()]);
        }
        $this->writeMarker();
    }

    private function state(): object
    {
        return DB::table('hosted_file_states')->where('id', 1)->first() ?? throw new RuntimeException('Initialize durable files before serving requests.');
    }

    private function writeMarker(): void
    {
        $state = $this->state();
        File::ensureDirectoryExists(dirname(config('hosted.marker')));
        File::replace(config('hosted.marker'), $state->epoch.':'.$state->revision);
    }

    /** Restore a new ephemeral instance without replacing durable content by seeds. */
    public function restore(bool $force = false): void
    {
        $state = $this->state();
        foreach (config('hosted.roots') as $root) {
            if (! is_dir($root)) {
                $force = true;
            }
        }
        $roots = $this->roots();
        $marker = config('hosted.marker');
        if (! $force && is_file($marker) && file_get_contents($marker) === $state->epoch.':'.$state->revision) {
            return;
        }
        $metadata = [];
        $total = 0;
        // Validate every row before changing any local file. Keep payload memory bounded.
        foreach (DB::table('hosted_files')->orderBy('id')->cursor() as $row) {
            $this->safePath($row->path);
            if (! isset($roots[$row->root]) || $row->path_hash !== hash('sha256', $row->path)) {
                throw new RuntimeException('Stored files failed path validation.');
            }
            if ($row->is_directory && ((int) $row->size !== 0 || $row->checksum !== null || $row->payload !== null)) {
                throw new RuntimeException('Stored directory metadata is invalid.');
            }
            if (! $row->is_directory) {
                $bytes = base64_decode($row->payload ?? '', true);
                $total += (int) $row->size;
                if ($bytes === false || strlen($bytes) !== (int) $row->size || hash('sha256', $bytes) !== $row->checksum || strlen($bytes) > config('hosted.max_file_bytes') || $total > config('hosted.quota_bytes')) {
                    throw new RuntimeException('Stored files failed integrity or quota validation.');
                }
            }
            $metadata[$row->root.'/'.$row->path] = ['id' => $row->id, 'root' => $row->root, 'path' => $row->path, 'is_directory' => (bool) $row->is_directory];
            if (count($metadata) > config('hosted.max_files')) {
                throw new RuntimeException('Stored file count limit exceeded.');
            }
        }
        foreach ($metadata as $entry) {
            $parent = dirname($entry['path']);
            while ($parent !== '.') {
                if (isset($metadata[$entry['root'].'/'.$parent]) && ! $metadata[$entry['root'].'/'.$parent]['is_directory']) {
                    throw new RuntimeException('Stored files have conflicting paths.');
                }
                $parent = dirname($parent);
            }
        }
        $existing = $this->scan($roots, false);
        uksort($existing, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($existing as $key => $entry) {
            if (isset($metadata[$key]) && $metadata[$key]['is_directory'] === $entry['is_directory']) {
                continue;
            }
            $full = $roots[$entry['root']].'/'.$entry['path'];
            if ($entry['is_directory']) {
                if (! rmdir($full)) {
                    throw new RuntimeException('Could not reconcile a managed directory.');
                }
            } elseif (! unlink($full)) {
                throw new RuntimeException('Could not reconcile a managed file.');
            }
        }
        foreach ($metadata as $entry) {
            $full = $roots[$entry['root']].'/'.$entry['path'];
            if ($entry['is_directory']) {
                File::ensureDirectoryExists($full, 0750);

                continue;
            }
            $row = DB::table('hosted_files')->where('id', $entry['id'])->first(['checksum', 'payload']);
            if (is_file($full) && hash_file('sha256', $full) === $row->checksum) {
                continue;
            }
            File::ensureDirectoryExists(dirname($full), 0750);
            File::replace($full, base64_decode($row->payload, true), $entry['root'] === 'private' ? 0600 : 0644);
        }
        if (class_exists(Stache::class)) {
            Stache::clear();
        }
        $this->writeMarker();
    }
}
