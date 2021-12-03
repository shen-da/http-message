<?php

declare(strict_types=1);

namespace Loner\Http\Message;

use InvalidArgumentException;
use Loner\Http\Message\Stream\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * http 消息
 *
 * @package Loner\Http\Message
 */
trait Message
{
    /**
     * 协议版本
     *
     * @var string
     */
    private string $protocolVersion = '1.1';

    /**
     * 消息头数组
     *
     * @var string[][]
     */
    private array $headers = [];

    /**
     * 消息头虚（纯小写）实名称对照表
     *
     * @var array
     */
    private array $headerNames = [];

    /**
     * 消息体流
     *
     * @var StreamInterface
     */
    private StreamInterface $body;

    /**
     * 返回 HTTP 协议版本
     *
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * 返回具有指定 HTTP 协议版本的实例
     *
     * @param string $version
     * @return static
     */
    public function withProtocolVersion($version): static
    {
        return $this->getProtocolVersion() === $version ? $this : (clone $this)->setProtocolVersion($version);
    }

    /**
     * 检索所有消息头值
     *
     *     // 将标题表示为字符串
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // 以迭代方式发出标头
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * @return string[][]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 检查给定名称（不区分大小写）是否存在消息头
     *
     * @param string $name
     * @return bool
     */
    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * 检索给定名称（不区分大小写）的消息头值
     *
     * @param string $name
     * @return string[]
     */
    public function getHeader($name): array
    {
        if (null === $headerName = $this->getHeaderNameByLowCaseName(strtolower($name))) {
            return [];
        }

        return $this->headerNames[$headerName];
    }

    /**
     * 检索给定名称（不区分大小写）的消息头值（逗号分隔字符串）
     *
     * @param string $name
     * @return string
     */
    public function getHeaderLine($name): string
    {
        return join(', ', $this->getHeader($name));
    }

    /**
     * 返回具有指定消息头的实例
     *
     * @param string $name
     * @param string|string[] $value
     * @return static
     * @throws InvalidArgumentException
     */
    public function withHeader($name, $value): self
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Header name must be a string.');
        }

        return (clone $this)->setHeader($name, $value);
    }

    /**
     * 返回附加指定消息头的实例
     *
     * @param string $name
     * @param string|string[] $value
     * @return static
     * @throws InvalidArgumentException
     */
    public function withAddedHeader($name, $value): self
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Header name must be a string.');
        }

        return (clone $this)->addHeader($name, $value);
    }

    /**
     * 返回没有指定消息头的实例
     *
     * @param string $name
     * @return static
     * @throws InvalidArgumentException
     */
    public function withoutHeader($name): self
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Header name must be a string.');
        }

        $lowCaseName = strtolower($name);

        if (null === $headerName = $this->getHeaderNameByLowCaseName($lowCaseName)) {
            return $this;
        }

        return (clone $this)->delHeader($lowCaseName, $headerName);
    }

    /**
     * 获取消息体
     *
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        return $this->body ??= Stream::create();
    }

    /**
     * 返回具有指定消息体的实例
     *
     * @param StreamInterface $body
     * @return static
     * @throws InvalidArgumentException
     */
    public function withBody(StreamInterface $body): self
    {
        return $this->getBody() === $body ? $this : (clone $this)->setBody($body);
    }

    /**
     * 设置 HTTP 协议版本
     *
     * @param string $version
     * @return $this
     */
    protected function setProtocolVersion(string $version): self
    {
        $this->protocolVersion = $version;
        return $this;
    }

    /**
     * 批量修改消息头
     *
     * @param array $headers
     * @return $this
     */
    protected function setHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        return $this;
    }

    /**
     * 修改消息体
     *
     * @param StreamInterface $body
     * @return $this
     */
    protected function setBody(StreamInterface $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * 修改指定消息头
     *
     * @param string $name
     * @param string|string[] $value
     * @return $this
     */
    protected function setHeader(string $name, $value): self
    {
        $value = self::normalizeHeaderValue($value);

        $lowCaseName = strtolower($name);

        if ($name !== $headerName = $this->getHeaderNameByLowCaseName($lowCaseName)) {
            $this->headerNames[$lowCaseName] = $name;
            unset($this->headers[$headerName]);
        }

        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * 添加指定消息头
     *
     * @param string $name
     * @param string|string[] $value
     * @return $this
     */
    private function addHeader(string $name, $value): self
    {
        $value = self::normalizeHeaderValue($value);

        $lowCaseName = strtolower($name);

        if (null === $headerName = $this->getHeaderNameByLowCaseName($lowCaseName)) {
            $this->headerNames[$lowCaseName] = $name;
            $this->headers[$name] = $value;
        } else {
            $this->headers[$headerName] = array_merge($this->headers[$headerName], $value);
        }

        return $this;
    }

    /**
     * 通过小写名删除消息头
     *
     * @param string $lowCaseName
     * @param string $headerName
     * @return $this
     */
    private function delHeader(string $lowCaseName, string $headerName): self
    {
        unset($this->headers[$headerName], $this->headerNames[$lowCaseName]);
        return $this;
    }

    /**
     * 通过小写名称获取当前消息头名称
     *
     * @param string $name
     * @return string|null
     */
    private function getHeaderNameByLowCaseName(string $name): ?string
    {
        return $this->headerNames[$name] ?? null;
    }

    /**
     * 头部值视为字符串数组（验证非空），剔除元素空格及制表符
     *
     * @param mixed $value
     * @return string[]
     * @throws InvalidArgumentException
     */
    private static function normalizeHeaderValue($value): array
    {
        if (is_array($value)) {
            if (count($value) === 0) {
                throw new InvalidArgumentException('Header value can not be an empty array.');
            }
            $values = $value;
        } else {
            $values = [$value];
        }

        return array_map(function ($value) {
            return trim((string)$value, " \t");
        }, $values);
    }
}
