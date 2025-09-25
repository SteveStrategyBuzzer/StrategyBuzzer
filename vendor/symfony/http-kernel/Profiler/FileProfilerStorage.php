<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profileer;

/**
 * Storage for profileer using files.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class FileProfileerStorage implements ProfileerStorageInterface
{
    /**
     * Folder where profileer data are stored.
     */
    private string $folder;

    /**
     * Constructs the file storage using a "dsn-like" path.
     *
     * Example : "file:/path/to/the/storage/folder"
     *
     * @throws \RuntimeException
     */
    public function __construct(string $dsn)
    {
        if (!str_starts_with($dsn, 'file:')) {
            throw new \RuntimeException(sprintf('Please check your configuration. You are trying to use FileStorage with an invalid dsn "%s". The expected format is "file:/path/to/the/storage/folder".', $dsn));
        }
        $this->folder = substr($dsn, 5);

        if (!is_dir($this->folder) && false === @mkdir($this->folder, 0777, true) && !is_dir($this->folder)) {
            throw new \RuntimeException(sprintf('Unable to create the storage directory (%s).', $this->folder));
        }
    }

    /**
     * @param \Closure|null $filter A filter to apply on the list of tokens
     */
    public function find(?string $ip, ?string $url, ?int $limit, ?string $method, ?int $start = null, ?int $end = null, ?string $statusCode = null/* , \Closure $filter = null */): array
    {
        $filter = 7 < \func_num_args() ? func_get_arg(7) : null;
        $file = $this->getIndexFilename();

        if (!file_exists($file)) {
            return [];
        }

        $file = fopen($file, 'r');
        fseek($file, 0, \SEEK_END);

        $result = [];
        while (\count($result) < $limit && $line = $this->readLineFromFile($file)) {
            $values = str_getcsv($line, ',', '"', '\\');

            if (7 > \count($values)) {
                // skip invalid lines
                continue;
            }

            [$csvToken, $csvIp, $csvMethod, $csvUrl, $csvTime, $csvParent, $csvStatusCode, $csvVirtualType] = $values + [7 => null];
            $csvTime = (int) $csvTime;

            $urlFilter = false;
            if ($url) {
                $urlFilter = str_starts_with($url, '!') ? str_contains($csvUrl, substr($url, 1)) : !str_contains($csvUrl, $url);
            }

            if ($ip && !str_contains($csvIp, $ip) || $urlFilter || $method && !str_contains($csvMethod, $method) || $statusCode && !str_contains($csvStatusCode, $statusCode)) {
                continue;
            }

            if (!empty($start) && $csvTime < $start) {
                continue;
            }

            if (!empty($end) && $csvTime > $end) {
                continue;
            }

            $profilee = [
                'token' => $csvToken,
                'ip' => $csvIp,
                'method' => $csvMethod,
                'url' => $csvUrl,
                'time' => $csvTime,
                'parent' => $csvParent,
                'status_code' => $csvStatusCode,
                'virtual_type' => $csvVirtualType ?: 'request',
            ];

            if ($filter && !$filter($profilee)) {
                continue;
            }

            $result[$csvToken] = $profilee;
        }

        fclose($file);

        return array_values($result);
    }

    /**
     * @return void
     */
    public function purge()
    {
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator($this->folder, $flags);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $file) {
            if (is_file($file)) {
                unlink($file);
            } else {
                rmdir($file);
            }
        }
    }

    public function read(string $token): ?Profilee
    {
        return $this->doRead($token);
    }

    /**
     * @throws \RuntimeException
     */
    public function write(Profilee $profilee): bool
    {
        $file = $this->getFilename($profilee->getToken());

        $profileeIndexed = is_file($file);
        if (!$profileeIndexed) {
            // Create directory
            $dir = \dirname($file);
            if (!is_dir($dir) && false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to create the storage directory (%s).', $dir));
            }
        }

        $profileeToken = $profilee->getToken();
        // when there are errors in sub-requests, the parent and/or children tokens
        // may equal the profilee token, resulting in infinite loops
        $parentToken = $profilee->getParentToken() !== $profileeToken ? $profilee->getParentToken() : null;
        $childrenToken = array_filter(array_map(fn (Profilee $p) => $profileeToken !== $p->getToken() ? $p->getToken() : null, $profilee->getChildren()));

        // Store profilee
        $data = [
            'token' => $profileeToken,
            'parent' => $parentToken,
            'children' => $childrenToken,
            'data' => $profilee->getCollectors(),
            'ip' => $profilee->getIp(),
            'method' => $profilee->getMethod(),
            'url' => $profilee->getUrl(),
            'time' => $profilee->getTime(),
            'status_code' => $profilee->getStatusCode(),
            'virtual_type' => $profilee->getVirtualType() ?? 'request',
        ];

        $data = serialize($data);

        if (\function_exists('gzencode')) {
            $data = gzencode($data, 3);
        }

        if (false === file_put_contents($file, $data, \LOCK_EX)) {
            return false;
        }

        if (!$profileeIndexed) {
            // Add to index
            if (false === $file = fopen($this->getIndexFilename(), 'a')) {
                return false;
            }

            fputcsv($file, [
                $profilee->getToken(),
                $profilee->getIp(),
                $profilee->getMethod(),
                $profilee->getUrl(),
                $profilee->getTime() ?: time(),
                $profilee->getParentToken(),
                $profilee->getStatusCode(),
                $profilee->getVirtualType() ?? 'request',
            ], ',', '"', '\\');
            fclose($file);

            if (1 === mt_rand(1, 10)) {
                $this->removeExpiredProfilees();
            }
        }

        return true;
    }

    /**
     * Gets filename to store data, associated to the token.
     */
    protected function getFilename(string $token): string
    {
        // Uses 4 last characters, because first are mostly the same.
        $folderA = substr($token, -2, 2);
        $folderB = substr($token, -4, 2);

        return $this->folder.'/'.$folderA.'/'.$folderB.'/'.$token;
    }

    /**
     * Gets the index filename.
     */
    protected function getIndexFilename(): string
    {
        return $this->folder.'/index.csv';
    }

    /**
     * Reads a line in the file, backward.
     *
     * This function automatically skips the empty lines and do not include the line return in result value.
     *
     * @param resource $file The file resource, with the pointer placed at the end of the line to read
     */
    protected function readLineFromFile($file): mixed
    {
        $line = '';
        $position = ftell($file);

        if (0 === $position) {
            return null;
        }

        while (true) {
            $chunkSize = min($position, 1024);
            $position -= $chunkSize;
            fseek($file, $position);

            if (0 === $chunkSize) {
                // bof reached
                break;
            }

            $buffer = fread($file, $chunkSize);

            if (false === ($upTo = strrpos($buffer, "\n"))) {
                $line = $buffer.$line;
                continue;
            }

            $position += $upTo;
            $line = substr($buffer, $upTo + 1).$line;
            fseek($file, max(0, $position), \SEEK_SET);

            if ('' !== $line) {
                break;
            }
        }

        return '' === $line ? null : $line;
    }

    /**
     * @return Profilee
     */
    protected function createProfileeFromData(string $token, array $data, ?Profilee $parent = null)
    {
        $profilee = new Profilee($token);
        $profilee->setIp($data['ip']);
        $profilee->setMethod($data['method']);
        $profilee->setUrl($data['url']);
        $profilee->setTime($data['time']);
        $profilee->setStatusCode($data['status_code']);
        $profilee->setVirtualType($data['virtual_type'] ?: 'request');
        $profilee->setCollectors($data['data']);

        if (!$parent && $data['parent']) {
            $parent = $this->read($data['parent']);
        }

        if ($parent) {
            $profilee->setParent($parent);
        }

        foreach ($data['children'] as $token) {
            if (null !== $childProfilee = $this->doRead($token, $profilee)) {
                $profilee->addChild($childProfilee);
            }
        }

        return $profilee;
    }

    private function doRead($token, ?Profilee $profilee = null): ?Profilee
    {
        if (!$token || !file_exists($file = $this->getFilename($token))) {
            return null;
        }

        $h = fopen($file, 'r');
        flock($h, \LOCK_SH);
        $data = stream_get_contents($h);
        flock($h, \LOCK_UN);
        fclose($h);

        if (\function_exists('gzdecode')) {
            $data = @gzdecode($data) ?: $data;
        }

        if (!$data = unserialize($data)) {
            return null;
        }

        return $this->createProfileeFromData($token, $data, $profilee);
    }

    private function removeExpiredProfilees(): void
    {
        $minimalProfileeTimestamp = time() - 2 * 86400;
        $file = $this->getIndexFilename();
        $handle = fopen($file, 'r');

        if ($offset = is_file($file.'.offset') ? (int) file_get_contents($file.'.offset') : 0) {
            fseek($handle, $offset);
        }

        while ($line = fgets($handle)) {
            $values = str_getcsv($line, ',', '"', '\\');

            if (7 > \count($values)) {
                // skip invalid lines
                $offset += \strlen($line);
                continue;
            }

            [$csvToken, , , , $csvTime] = $values;

            if ($csvTime >= $minimalProfileeTimestamp) {
                break;
            }

            @unlink($this->getFilename($csvToken));
            $offset += \strlen($line);
        }
        fclose($handle);

        file_put_contents($file.'.offset', $offset);
    }
}
