<?php

declare(strict_types=1);

namespace Loner\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\{ResponseInterface, StreamInterface};

/**
 * http 服务端响应
 *
 * @package Loner\Http\Message
 */
class Response implements ResponseInterface
{
    use Message;

    /**
     * 状态码说明对照表
     *
     * @var string[]
     */
    public const PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];

    /**
     * 状态码
     *
     * @var int
     */
    private int $statusCode;

    /**
     * 与状态码关联的原因短语
     *
     * @var string
     */
    private string $reasonPhrase;

    /**
     * cookie 列表
     *
     * @var string[]
     */
    private array $cookies = [];

    /**
     * 初始化响应信息
     *
     * @param int $statusCode
     * @param string $reasonPhrase
     * @param array|null $headers
     * @param StreamInterface|null $body
     * @param string|null $protocolVersion
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $statusCode = 200,
        string $reasonPhrase = '',
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
        $this->setStatus($statusCode, $reasonPhrase);
    }

    /**
     * 获取响应状态码
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 返回具有指定状态码和原因短语的实例
     *
     * @param int $code
     * @param string $reasonPhrase
     * @return static
     * @throws InvalidArgumentException
     */
    public function withStatus($code, $reasonPhrase = ''): self
    {
        if (!is_int($code)) {
            throw new InvalidArgumentException('Status code must bu a integer.');
        }

        $reasonPhrase = (string)$reasonPhrase;

        if ($this->statusCode === $code) {
            if ($this->reasonPhrase === $reasonPhrase) {
                return $this;
            }

            if ($reasonPhrase === '' && isset(self::PHRASES[$code])) {
                $reasonPhrase = self::PHRASES[$code];
                if ($reasonPhrase === $this->reasonPhrase) {
                    return $this;
                }
            }
        }

        return (clone $this)->setStatus($code, $reasonPhrase);

    }

    /**
     * 获取与状态码关联的原因短语
     *
     * @return string
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase ?: self::PHRASES[$this->statusCode] ?? '';
    }

    /**
     * 返回具有指定 cookie 的实例
     *
     * @param string $name
     * @param string $value
     * @param int $maxAge
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @param bool $raw
     * @param string|null $sameSite
     * @return static
     */
    public function withCookie(
        string $name,
        string $value = '',
        int $maxAge = 0,
        string $path = '',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = null
    ): self
    {
        if (preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            throw new InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $name));
        }

        if (empty($name)) {
            throw new InvalidArgumentException('The cookie name cannot be empty.');
        }

        if (!in_array($sameSite, ['lax', 'strict', null], true)) {
            throw new InvalidArgumentException('The "sameSite" parameter value is not valid.');
        }

        if (!$raw) {
            $name = urlencode($name);
            $value = rawurlencode($value);
        }

        $time = time();
        $maxAge = max(0, $maxAge);

        $cookie = '';

        if ($value === '') {
            $cookie .= sprintf('%s=%s', $name, 'deleted');
            $cookie .= sprintf('; Expires=%s', gmdate('D, d-M-Y H:i:s T', $time - 31536001));
            $cookie .= '; Max-Age=-31536001';
        } else {
            $cookie .= sprintf('%s=%s', $name, $value);
            if ($maxAge > 0) {
                $cookie .= sprintf('; Expires=%s', gmdate('D, d-M-Y H:i:s T', $time + $maxAge));
                $cookie .= sprintf('; Max-Age=%d', $maxAge);
            }
        }

        $cookie .= sprintf('; Path=%s', $path ?: '/');

        if ($domain !== '') {
            $cookie .= sprintf('; Domain=%s', $domain);
        }

        if ($secure) {
            $cookie .= '; Secure';
        }

        if ($httpOnly) {
            $cookie .= '; Httponly';
        }

        if ($sameSite !== null) {
            $cookie .= sprintf('; SameSite=%s', ucfirst($sameSite));
        }

        if (isset($this->cookies[$name]) && $this->cookies[$name] === $cookie) {
            return $this;
        }

        return (clone $this)->setCookie($name, $cookie);
    }

    /**
     * 字符串化
     *
     * @return string
     */
    public function __toString(): string
    {
        $body = $this->getBody();

        $new = $this
            ->withHeader('Date', gmdate('D, d-M-Y H:i:s T'))
            ->withHeader('Content-Length', (string)$body->getSize() ?: '0');

        $headers = $new->getHeaderLines();
        array_unshift($headers, $new->getStatusLine(), ...$new->getCookieLines());

        return sprintf("%s\r\n\r\n%s", join("\r\n", $headers), (string)$body);
    }

    /**
     * 设置响应状态
     *
     * @param int $code
     * @param string $reasonPhrase
     * @return $this
     */
    private function setStatus(int $code, string $reasonPhrase = ''): self
    {
        if ($code < 100 || $code >= 600) {
            throw new InvalidArgumentException('Invalid status code provided for response.');
        }

        $this->statusCode = $code;
        $this->reasonPhrase = $reasonPhrase;

        return $this;
    }

    /**
     * 设置 cookie
     *
     * @param string $name
     * @param string $cookie
     * @return $this
     */
    private function setCookie(string $name, string $cookie): self
    {
        $this->cookies[$name] = $cookie;
        return $this;
    }

    /**
     * 获取状态行
     *
     * @return string
     */
    private function getStatusLine(): string
    {
        return sprintf('HTTP/%s %d %s', $this->getProtocolVersion(), $this->getStatusCode(), $this->getReasonPhrase());
    }

    /**
     * 返回消息头串列表
     *
     * @return string[]
     */
    private function getHeaderLines(): array
    {
        $headers = [];
        foreach ($this->getHeaders() as $name => $values) {
            $headers[] = sprintf('%s: %s', $name, join(', ', $values));
        }
        return $headers;
    }

    /**
     * 返回 cookie 列表
     *
     * @return string[]
     */
    private function getCookieLines(): array
    {
        $cookies = [];
        foreach ($this->cookies as $cookie) {
            $cookies[] = sprintf('Set-Cookie: %s', $cookie);
        }
        return $cookies;
    }
}