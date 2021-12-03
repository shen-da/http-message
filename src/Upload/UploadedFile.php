<?php

declare(strict_types=1);

namespace Loner\Http\Message\Upload;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * 规范上载文件
 *
 * @package Loner\Http\Message\Upload
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * 错误标识库
     *
     * @var bool[]
     */
    private static array $errorHash = [
        UPLOAD_ERR_OK => true,
        UPLOAD_ERR_INI_SIZE => true,
        UPLOAD_ERR_FORM_SIZE => true,
        UPLOAD_ERR_PARTIAL => true,
        UPLOAD_ERR_NO_FILE => true,
        UPLOAD_ERR_NO_TMP_DIR => true,
        UPLOAD_ERR_CANT_WRITE => true,
        UPLOAD_ERR_EXTENSION => true
    ];

    /**
     * 数据流
     *
     * @var StreamInterface|null
     */
    private ?StreamInterface $stream;

    /**
     * 文件字节数
     *
     * @var int|null
     */
    private ?int $size;

    /**
     * 错误标识
     *
     * @var int
     */
    private int $error;

    /**
     * 文件是否已移动
     *
     * @var bool
     */
    private bool $moved = false;

    /**
     * 客户端发出文件名
     *
     * @var string|null
     */
    private ?string $clientFilename;

    /**
     * 客户端发送的媒体类型
     *
     * @var string|null
     */
    private ?string $clientMediaType;

    /**
     * 初始化上载文件信息
     *
     * @param StreamInterface $stream
     * @param int|null $size
     * @param int $error
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
    public function __construct(StreamInterface $stream, int $size = null, int $error = UPLOAD_ERR_OK, string $clientFilename = null, string $clientMediaType = null)
    {
        $this->setError($error);
        $this->setClientFilename($clientFilename);
        $this->setClientMediaType($clientMediaType);
        if ($this->isOk()) {
            $this->setStream($stream);
        }
        $this->setSize($size);
    }

    /**
     * 检索上载文件流
     *
     * @return StreamInterface|null
     * @throws RuntimeException
     */
    public function getStream(): ?StreamInterface
    {
        $this->validateActive();

        return $this->stream;
    }

    /**
     * 将上载文件移动到新路径
     *
     * @param string $targetPath
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function moveTo($targetPath): void
    {
        $this->validateActive();

        if (!is_string($targetPath) || $targetPath === '') {
            throw new InvalidArgumentException('Invalid path provided for move operation.');
        }

        $uri = $this->stream->getMetadata('uri');

        $moved = PHP_SAPI === 'cli'
            ? is_file($uri)
                ? @rename($uri, $targetPath)
                : file_put_contents($targetPath, (string)$this->stream)
            : @move_uploaded_file($uri, $targetPath);

        if (!$moved) {
            throw new RuntimeException('UploadedFile move failed.');
        }

        $this->moved = true;
        $this->stream->close();
    }

    /**
     * 检索文件大小
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * 检索与上载文件关联的错误
     *
     * @return int
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * 检索客户端发送的文件名
     *
     * @return string|null
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * 检索客户端发送的媒体类型
     *
     * @return string|null
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * 设置临时文件名
     *
     * @param StreamInterface $stream
     */
    private function setStream(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * 设置文件字节数
     *
     * @param int|null $size
     */
    private function setSize(?int $size)
    {
        $this->size = $size ?? $this->stream->getSize();
    }

    /**
     * 设置与上载文件关联的错误
     *
     * @param int $error
     */
    private function setError(int $error)
    {
        if (!isset(self::$errorHash[$error])) {
            throw new InvalidArgumentException('Invalid error status for UploadedFile.');
        }

        $this->error = $error;
    }

    /**
     * 保存客户端发出的文件名
     *
     * @param string|null $clientFilename
     */
    private function setClientFilename(?string $clientFilename)
    {
        if (!is_string($clientFilename) && !is_null($clientFilename)) {
            throw new InvalidArgumentException('Upload file client filename must bu a string or null.');
        }

        $this->clientFilename = $clientFilename;
    }

    /**
     * 保存客户端发出的媒体类型
     *
     * @param string|null $clientMediaType
     */
    private function setClientMediaType(?string $clientMediaType)
    {
        if (!is_string($clientMediaType) && !is_null($clientMediaType)) {
            throw new InvalidArgumentException('Upload file client media type must bu a string or null.');
        }

        $this->clientMediaType = $clientMediaType;
    }

    /**
     * 上传是否无误
     *
     * @return bool
     */
    private function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /**
     * 文件是否已移动
     *
     * @return bool
     */
    private function isMoved(): bool
    {
        return $this->moved;
    }

    /**
     * 流可用性验证
     *
     * @throws RuntimeException
     */
    private function validateActive(): void
    {
        if (!$this->isOk()) {
            throw new RuntimeException('Cannot retrieve stream due to upload error.');
        }

        if ($this->isMoved()) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved.');
        }
    }
}
