<?php

declare(strict_types=1);

namespace Loner\Http\Message\Stream;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 数据流
 *
 * @package Loner\Http\Message\Stream
 */
class Stream implements StreamInterface
{
    /**
     * 可读模式正则
     */
    public const PREG_READABLE = '/r|[waxc]b?\+/';

    /**
     * 可写模式正则
     */
    public const PREG_WRITABLE = '/rb?\+|[waxc]/';

    /**
     * 数据流
     *
     * @var resource|null
     */
    private $stream;

    /**
     * 数据长度
     *
     * @var int|null
     */
    private ?int $size;

    /**
     * 流元数据数组
     *
     * @var array|null
     */
    private ?array $meta;

    /**
     * 关联 URI 或文件名
     *
     * @var string|null
     */
    private ?string $uri;

    /**
     * 是否可查找
     *
     * @var bool
     */
    private bool $seekable;

    /**
     * 是否可读
     *
     * @var bool
     */
    private bool $readable;

    /**
     * 是否可写
     *
     * @var bool
     */
    private bool $writable;

    /**
     * 从字符串创建新流
     *
     * @param string $content
     * @return static
     */
    public static function create(string $content = ''): self
    {
        $resource = fopen('php://temp', 'rw+');
        fwrite($resource, $content);
        return new self($resource);
    }

    /**
     * 从现有文件创建流
     *
     * @param string $filename
     * @param string $mode
     * @return self
     */
    public static function createFormFile(string $filename, string $mode = 'r'): self
    {
        if ($mode === '' || !str_contains('rwaxc', $mode[0])) {
            throw new InvalidArgumentException(sprintf('The mode %s is invalid.', $mode));
        }

        if ($filename === '') {
            throw new RuntimeException('Filename cannot be empty.');
        }

        if (false === $resource = @fopen($filename, $mode)) {
            throw new RuntimeException(sprintf('The file "%s" cannot be opened.', $filename));
        }

        return new self($resource);
    }

    /**
     * 初始化数据流信息
     *
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource.');
        }

        $this->stream = $stream;

        $this->uri = $this->getMetadata('uri');
    }

    /**
     * 读取（从头至尾）全部数据
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
        } catch (RuntimeException) {
            return '';
        }
    }

    /**
     * 关闭流和底层资源
     *
     * @return void
     */
    public function close(): void
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * 分离底层资源，之后流不可用
     *
     * @return resource|null
     */
    public function detach()
    {
        if (isset($this->stream)) {
            $this->meta = null;
            $this->uri = null;
            $this->size = null;
            $this->seekable = false;
            $this->readable = false;
            $this->writable = false;

            $resource = $this->stream;
            unset($this->stream);

            return $resource;
        }

        return null;
    }

    /**
     * 获取流的大小
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        if (!isset($this->size) && isset($this->stream)) {
            if ($this->uri) {
                clearstatcache(true, $this->uri);
            }
            $this->size = fstat($this->stream)['size'] ?? null;
        }
        return $this->size;
    }

    /**
     * 返回指针位置
     *
     * @return int
     * @throws RuntimeException
     */
    public function tell(): int
    {
        if (false === $tell = ftell($this->stream)) {
            throw new RuntimeException('Unable to determine stream position.');
        }
        return $tell;
    }

    /**
     * 判断流指针是否到末尾
     *
     * @return bool
     */
    public function eof(): bool
    {
        return !isset($this->stream) || !is_resource($this->stream) || feof($this->stream);
    }

    /**
     * 返回流是否可查找
     *
     * @return bool
     */
    public function isSeekable(): bool
    {
        return $this->seekable ??= $this->getMetadata('seekable') && fseek($this->stream, 0, SEEK_CUR) === 0;
    }

    /**
     * 移动指针
     *
     * @param int $offset
     * @param int $whence
     * @throws RuntimeException
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException(sprintf('Unable to seek to stream position "%d" with whence "%d".', $offset, $whence));
        }
    }

    /**
     * 指针指向流的开头
     *
     * @throws RuntimeException
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * 返回流是否可写
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->writable ??= preg_match(self::PREG_WRITABLE, $this->getMetadata('mode')) > 0;
    }

    /**
     * 将数据写入流
     *
     * @param string $string
     * @return int
     * @throws RuntimeException
     */
    public function write($string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Cannot write to a non-writable stream.');
        }

        $this->size = null;

        if (false === $write = fwrite($this->stream, $string)) {
            throw new RuntimeException('Unable to write to stream.');
        }

        return $write;
    }

    /**
     * 返回流是否可读
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->readable ??= preg_match(self::PREG_READABLE, $this->getMetadata('mode')) > 0;
    }

    /**
     * 从流中读取数据
     *
     * @param int $length
     * @return string
     * @throws RuntimeException
     */
    public function read($length): string
    {
        if ($this->isReadable()) {
            return fread($this->stream, $length);
        }
        throw new RuntimeException('Cannot read to a unreadable stream.');
    }

    /**
     * 读取剩余内容
     *
     * @return string
     * @throws RuntimeException
     */
    public function getContents(): string
    {
        if (isset($this->stream)) {
            $content = stream_get_contents($this->stream);
            if ($content !== false) {
                return $content;
            }
        }
        throw new RuntimeException('Unable to read stream contents.');
    }

    /**
     * 获取全部或指定元素据
     *
     * @param string|null $key
     * @return mixed
     */
    public function getMetadata(string $key = null): mixed
    {
        if (isset($this->stream)) {
            if (!isset($this->meta)) {
                $this->meta = stream_get_meta_data($this->stream);
            }
            return $key === null ? $this->meta : $this->meta[$key] ?? null;
        }
        return $key === null ? [] : null;
    }

    /**
     * 析构函数：关闭资源
     */
    public function __destruct()
    {
        $this->close();
    }
}
