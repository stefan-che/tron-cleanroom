<?php

declare(strict_types=1);

namespace App\Services\Aml\Export;

use RuntimeException;

/**
 * JSONL writer with optional on-the-fly compression.
 *
 * A file of 20k addresses is tens of megabytes; there is no need to build it
 * in memory: both browser download and file writes are streamed, and gzip
 * is compressed incrementally via deflate_add.
 */
class AmlExportWriter
{
    /** @var resource|null */
    private $deflate = null;

    /**
     * @param callable(string $chunk): void $sink Where finished bytes are sent
     */
    public function __construct(
        private readonly mixed $sink,
        bool $gzip = false,
    ) {
        if ($gzip) {
            $context = deflate_init(ZLIB_ENCODING_GZIP);

            if ($context === false) {
                throw new RuntimeException('Failed to initialize gzip compression for the export');
            }

            $this->deflate = $context;
        }
    }

    public function write(string $line): void
    {
        if ($this->deflate === null) {
            ($this->sink)($line);
            return;
        }

        $chunk = deflate_add($this->deflate, $line, ZLIB_NO_FLUSH);

        if ($chunk !== '') {
            ($this->sink)($chunk);
        }
    }

    /**
     * Writes the gzip stream trailer. Without this call the archive is corrupt.
     */
    public function finish(): void
    {
        if ($this->deflate === null) {
            return;
        }

        ($this->sink)(deflate_add($this->deflate, '', ZLIB_FINISH));
        $this->deflate = null;
    }
}
