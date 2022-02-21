<?php

declare(strict_types=1);

namespace Loner\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\{RequestInterface, StreamInterface, UriInterface};
use Stringable;

/**
 * http 客户端请求
 *
 * @package Loner\Http\Message
 */
class Request implements RequestInterface, Stringable
{
    use Message;

    /**
     * 可用请求方法
     *
     * @var string[]
     */
    public const ALLOW_METHODS = ['OPTIONS', 'HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * URI 对象
     *
     * @var UriInterface
     */
    private UriInterface $uri;

    /**
     * 请求方法类型
     *
     * @var string
     */
    private string $method;

    /**
     * 请求目标
     *
     * @var string|null
     */
    private ?string $target = null;

    /**
     * 初始化请求信息
     *
     * @param string $method
     * @param UriInterface $uri
     * @param array|null $headers
     * @param StreamInterface|null $body
     * @param string|null $protocolVersion
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        array $headers = null,
        StreamInterface $body = null,
        string $protocolVersion = null
    )
    {
        if ($headers !== null) {
            $this->setHeaders($headers);
        }
        if ($body !== null) {
            $this->setBody($body);
        }
        if ($protocolVersion !== null) {
            $this->setProtocolVersion($protocolVersion);
        }

        $this->setMethod($method)->setUri($uri, $this->hasHeader('Host'));
    }

    /**
     * 检索消息的请求目标
     *
     * @return string
     */
    public function getRequestTarget(): string
    {
        if ($this->target === null) {
            $this->target = $this->uri->getPath() ?: '/';

            if ('' !== $query = $this->uri->getQuery()) {
                $this->target .= '?' . $query;

                if ('' !== $fragment = $this->uri->getFragment()) {
                    $this->target .= '#' . $fragment;
                }
            }
        }

        return $this->target;
    }

    /**
     * 返回具有特定请求目标的实例
     *
     * @param mixed $requestTarget
     * @return static
     */
    public function withRequestTarget($requestTarget): self
    {
        if ($this->target === $requestTarget) {
            return $this;
        }

        $new = clone $this;
        $new->target = $requestTarget;
        return $new;
    }

    /**
     * 返回请求的 HTTP 方法
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 返回具有指定请求方法的实例
     *
     * @param string $method
     * @return static
     * @throws InvalidArgumentException
     */
    public function withMethod($method): self
    {
        if (!is_string($method) || $method === '') {
            throw  new InvalidArgumentException('Method must be a non empty string.');
        }

        return $this->method === $method ? $this : (clone $this)->setMethod($method);
    }

    /**
     * 检索 URI
     *
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * 返回具有指定 URI 的实例
     *
     * @param UriInterface $uri
     * @param bool $preserveHost
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        return $this->uri === $uri ? $this : (clone $this)->setUri($uri, $preserveHost);
    }

    /**
     * 字符串化
     *
     * @return string
     */
    public function __toString(): string
    {
        $requestLine = "{$this->getMethod()} {$this->getRequestTarget()} HTTP/{$this->getProtocolVersion()}";

        $headers = [];
        foreach ($this->getHeaders() as $name => $header) {
            $headers[] = $name . '=' . join('; ', $header);
        }

        return $requestLine . "\r\n" . join("\r\n", $headers) . "\r\n\r\n" . $this->getBody();
    }

    /**
     * 设置请求方法
     *
     * @param string $method
     * @return $this
     * @throws InvalidArgumentException
     */
    private function setMethod(string $method): self
    {
        if ($this->isMethodInvalid($method)) {
            throw new InvalidArgumentException('Invalid path provided for request.');
        }

        $this->method = $method;
        return $this;
    }

    /**
     * 设置 URI
     *
     * @param UriInterface $uri
     * @param bool $preserveHost
     * @return $this
     */
    private function setUri(UriInterface $uri, bool $preserveHost = false): self
    {
        $this->uri = $uri;
        return $preserveHost ? $this : $this->setHostFromUri();
    }

    /**
     * 通过 URI 设置主机头
     *
     * @return $this
     */
    private function setHostFromUri(): self
    {
        if ('' === $host = $this->uri->getHost()) {
            return $this;
        }

        if (null !== $port = $this->uri->getPort()) {
            $host = sprintf('%s:%d', $host, $port);
        }

        return $this->setHeader('Host', $host);
    }

    /**
     * 判断请求方法是否无效
     *
     * @param string $method
     * @return bool
     */
    private function isMethodInvalid(string $method): bool
    {
        return in_array($method, self::ALLOW_METHODS) === false;
    }
}
