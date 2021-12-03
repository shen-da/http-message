<?php

declare(strict_types=1);

namespace Loner\Http\Message\Uri;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * 资源标识
 *
 * @package Loner\Http\Message\Uri
 */
class Uri implements UriInterface
{
    /**
     * http/https 默认主机
     *  通用 URI 中，主机可以为空；但 http 和 https 必须提供主机，若未提供，采用此默认主机
     */
    public const DEFAULT_HTTP_HOST = 'localhost';

    /**
     * 默认协议端口
     *
     * @var int[]
     */
    private static array $schemeDefaultPorts = [
        'http' => 80,
        'https' => 443
    ];

    /**
     * 用户信息、路径、查询字符串和片段中使用的未保留字符
     *
     * @var string
     */
    private static string $charUnreserved = '\w-.~';

    /**
     * 用户信息、查询字符串和片段中使用的子分隔符
     *
     * @var string
     */
    private static string $charSubDelimiter = '!$&\'()*+,;=';

    /**
     * 协议
     *
     * @var string
     */
    private string $scheme = '';

    /**
     * 用户信息
     *
     * @var string
     */
    private string $userInfo = '';

    /**
     * 主机名
     *
     * @var string
     */
    private string $host = '';

    /**
     * 端口号
     *
     * @var int|null
     */
    private ?int $port = null;

    /**
     * 路径
     *
     * @var string
     */
    private string $path = '';

    /**
     * 查询字符串
     *
     * @var string
     */
    private string $query = '';

    /**
     * 片段
     *
     * @var string
     */
    private string $fragment = '';

    /**
     * 组合 URI
     *
     * @param string $scheme
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param string $fragment
     * @return string
     */
    public static function composeComponents(string $scheme, string $authority, string $path, string $query, string $fragment): string
    {
        $url = '';

        if ($scheme !== '') {
            $url .= $scheme . ':';
        }

        if ($authority !== '' || $scheme === 'file') {
            $url .= '//' . $authority;
        }

        $url .= $path;

        if ($query !== '') {
            $url .= '?' . $query;
        }

        if ($fragment !== '') {
            $url .= '#' . $fragment;
        }

        return $url;
    }

    /**
     * 初始化请求路径
     *
     * @param string $url
     * @throws InvalidArgumentException
     */
    public function __construct(string $url = '')
    {
        if ($url !== '') {
            if (false === $components = parse_url($url)) {
                throw new InvalidArgumentException(sprintf('Unable to parse URI: %s.', $url));
            }
            $this->applyComponents($components);
        }
    }

    /**
     * 检索方案组件
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * 检索授权组件
     *
     * @return string
     */
    public function getAuthority(): string
    {
        if ($this->host) {
            $authority = $this->userInfo ? $this->userInfo . '@' . $this->host : $this->host;
            return $this->port === null ? $authority : $authority . ':' . $this->port;
        }
        return '';
    }

    /**
     * 检索用户信息组件
     *
     * @return string
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * 检索主机组件
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 获取端口组件
     *
     * @return int|null
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * 检索路径组件
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 检索查询字符串
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * 检索片段组件
     *
     * @return string
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * 返回具有指定方案的实例
     *
     * @param string $scheme
     * @return \Loner\Http\Message\Uri
     * @throws InvalidArgumentException
     */
    public function withScheme($scheme): self
    {
        $scheme = $this->filterScheme($scheme);
        return $this->getScheme() === $scheme ? $this : (clone $this)->setScheme($scheme);
    }

    /**
     * 返回具有指定用户信息的实例
     *
     * @param string $user
     * @param string|null $password
     * @return static
     * @throws InvalidArgumentException
     */
    public function withUserInfo($user, $password = null): self
    {
        $userInfo = self::makeUserInfo($user, $password);
        return $this->getUserInfo() === $userInfo ? $this : (clone $this)->setUserInfo($userInfo);
    }

    /**
     * 返回具有指定主机的实例
     *
     * @param string $host
     * @return static
     * @throws InvalidArgumentException
     */
    public function withHost($host): self
    {
        $host = $this->filterHost($host);
        return $this->getHost() === $host ? $this : (clone $this)->setHost($host);
    }

    /**
     * 返回具有指定端口的实例
     *
     * @param int|null $port
     * @return static
     * @throws InvalidArgumentException
     */
    public function withPort($port): self
    {
        $port = $this->filterPort($port);
        return $this->getPort() === $port ? $this : (clone $this)->setPort($port);
    }

    /**
     * 返回具有指定路径的实例
     *
     * @param string $path
     * @return static
     * @throws InvalidArgumentException
     */
    public function withPath($path): self
    {
        $path = $this->filterPath($path);
        return $this->getPath() === $path ? $this : (clone $this)->setPath($path);
    }

    /**
     * 返回具有指定查询字符串的实例
     *
     * @param string $query
     * @return static
     * @throws InvalidArgumentException
     */
    public function withQuery($query): self
    {
        $query = $this->filterWithUrlEncode($query);
        return $this->getQuery() === $query ? $this : (clone $this)->setQuery($query);
    }

    /**
     * 返回具有指定片段的实例
     *
     * @param string $fragment
     * @return static
     */
    public function withFragment($fragment): self
    {
        $fragment = $this->filterWithUrlEncode($fragment);
        return $this->getFragment() === $fragment ? $this : (clone $this)->setFragment($fragment);
    }

    /**
     * 字符串化
     *
     * @return string
     */
    public function __toString(): string
    {
        return self::composeComponents($this->scheme, $this->getAuthority(), $this->path, $this->query, $this->fragment);
    }

    /**
     * 协议过滤
     *
     * @param mixed $scheme
     * @return string
     */
    private static function filterScheme(mixed $scheme): string
    {
        if (!is_string($scheme)) {
            throw new InvalidArgumentException('Scheme must be a string.');
        }

        return $scheme;
    }

    /**
     * 生成用户信息
     *
     * @param string $user
     * @param string|null $pass
     * @return string
     */
    private static function makeUserInfo(string $user, ?string $pass): string
    {
        return $user
            ? $pass
                ? self::filterUserInfo($user) . ':' . self::filterUserInfo($pass)
                : self::filterUserInfo($user)
            : '';
    }

    /**
     * 用户/密码过滤（编码）
     *
     * @param mixed $part
     * @return string
     * @throws InvalidArgumentException
     */
    private static function filterUserInfo(mixed $part): string
    {
        if (!is_string($part)) {
            throw new InvalidArgumentException('User and password must be a string.');
        }

        return preg_replace_callback(
            '#(?:[^%' . self::$charUnreserved . self::$charSubDelimiter . ']+|%(?![A-Fa-f0-9]{2}))#',
            fn($match) => rawurlencode($match[0]),
            $part
        );
    }

    /**
     * 主机名过滤
     *
     * @param mixed $host
     * @return string
     * @throws InvalidArgumentException
     */
    private static function filterHost(mixed $host): string
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException('Host must be a string.');
        }

        return strtolower($host);
    }

    /**
     * 端口号过滤
     *
     * @param mixed $port
     * @return null|int
     * @throws InvalidArgumentException
     */
    private static function filterPort(mixed $port): ?int
    {
        if ($port === null) {
            return null;
        }

        $port = (int)$port;
        if (1 > $port || 0xffff < $port) {
            throw new InvalidArgumentException(sprintf('Invalid port: %d. Must be between 1 and 65535.', $port));
        }

        return $port;
    }

    /**
     * 路径过滤（编码）
     *
     * @param mixed $path
     * @return string
     * @throws InvalidArgumentException
     */
    private static function filterPath(mixed $path): string
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Path must be a string.');
        }

        return preg_replace_callback(
            '#(?:[^%' . self::$charUnreserved . self::$charSubDelimiter . ':@/]++|%(?![A-Fa-f0-9]{2}))#',
            fn($match) => rawurlencode($match[0]),
            $path
        );
    }

    /**
     * 应用组件
     *
     * @param array $components
     */
    private function applyComponents(array $components): void
    {
        if (isset($components['scheme'])) {
            $this->scheme = self::filterScheme($components['scheme']);
        }
        if (!empty($components['host'])) {
            $this->host = self::filterHost($components['host']);
        } else {
            $this->supplyDefaultHost();
        }
        if (isset($components['port'])) {
            $this->port = self::filterPort($components['port']);
            $this->clearDefaultPort();
        }
        if (isset($components['path'])) {
            $this->path = self::filterPath($components['path']);
        }
        if (isset($components['query'])) {
            $this->query = self::filterWithUrlEncode($components['query']);
        }
        if (isset($components['fragment'])) {
            $this->fragment = self::filterWithUrlEncode($components['fragment']);
        }
        if (isset($components['user'])) {
            $this->userInfo = self::makeUserInfo($components['user'], $components['pass'] ?? null);
        }
    }

    /**
     * 查询字符串/片段过滤（编码）
     *
     * @param mixed $urlPart
     * @return string
     * @throws InvalidArgumentException
     */
    private function filterWithUrlEncode(mixed $urlPart): string
    {
        if (!is_string($urlPart)) {
            throw new InvalidArgumentException('Query, and fragment must be a string.');
        }

        return preg_replace_callback(
            '#(?:[^%' . self::$charUnreserved . self::$charSubDelimiter . ':@/?]++|%(?![A-Fa-f0-9]{2}))#',
            fn($match) => rawurlencode($match[0]),
            $urlPart
        );
    }

    /**
     * http/https 补充默认主机名
     */
    private function supplyDefaultHost(): void
    {
        if ($this->host === '' && ($this->scheme === 'http' || $this->scheme === 'https')) {
            $this->host = self::DEFAULT_HTTP_HOST;
        }
    }

    /**
     * 默认端口时，清除记录
     */
    private function clearDefaultPort(): void
    {
        if ($this->port !== null && $this->isDefaultPort()) {
            $this->port = null;
        }
    }

    /**
     * 判断当前是否默认端口号
     *
     * @return bool
     */
    private function isDefaultPort(): bool
    {
        return $this->port === null ||
            isset(self::$schemeDefaultPorts[$this->scheme]) && $this->port === self::$schemeDefaultPorts[$this->scheme];
    }

    /**
     * 设置方案组件
     *
     * @param string $scheme
     * @return $this
     */
    private function setScheme(string $scheme): self
    {
        $this->scheme = $scheme;
        $this->clearDefaultPort();
        return $this;
    }

    /**
     * 设置用户信息组件
     *
     * @param string $userInfo
     * @return $this
     */
    private function setUserInfo(string $userInfo): self
    {
        $this->userInfo = $userInfo;
        return $this;
    }

    /**
     * 设置主机组件
     *
     * @param string $host
     * @return $this
     */
    private function setHost(string $host): self
    {
        $this->host = $host;
        $this->supplyDefaultHost();
        return $this;
    }

    /**
     * 设置端口组件
     *
     * @param string|null $port
     * @return $this
     */
    private function setPort(?string $port): self
    {
        $this->port = $port;
        $this->clearDefaultPort();
        return $this;
    }

    /**
     * 设置路径组件
     *
     * @param string $path
     * @return $this
     */
    private function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 设置查询字符串
     *
     * @param string $query
     * @return $this
     */
    private function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * 设置片段组件
     *
     * @param string $fragment
     * @return $this
     */
    private function setFragment(string $fragment): self
    {
        $this->fragment = $fragment;
        return $this;
    }
}
