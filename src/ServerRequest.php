<?php

declare(strict_types=1);

namespace Loner\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\{ServerRequestInterface, StreamInterface, UploadedFileInterface, UriInterface};

/**
 * http 服务端接受请求
 *
 * @package Loner\Http\Message
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * 服务器参数
     *
     * @var array
     */
    private array $serverParams;

    /**
     * cookie 参数
     *
     * @var array
     */
    private array $cookieParams = [];

    /**
     * 查询字符串参数
     *
     * @var array
     */
    private array $queryParams = [];

    /**
     * 规范化文件上载数据
     *
     * @var UploadedFileInterface[]
     */
    private array $uploadedFiles = [];

    /**
     * 正文解析参数
     *
     * @var object|array|null
     */
    protected $parseBody;

    /**
     * 请求派生属性
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * 初始化请求信息
     *
     * @param string $method
     * @param UriInterface $uri
     * @param array $serverParams
     * @param array|null $headers
     * @param StreamInterface|null $body
     * @param string|null $protocolVersion
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        array $serverParams = [],
        array $headers = null,
        StreamInterface $body = null,
        string $protocolVersion = null
    )
    {
        $this->serverParams = $serverParams;

        parent::__construct($method, $uri, $headers, $body, $protocolVersion);
    }

    /**
     * 检索服务器参数
     *
     * @return array
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * 检索 cookie
     *
     * @return array
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * 返回具有指定 Cookie 的实例。
     *
     * 数据不需要来自 $_COOKIE 超全局，但必须与 $_COOKIE 的结构兼容。通常，这些数据将在实例化时注入。
     *
     * 此方法不能更新请求实例的相关 Cookie 头，也不能更新服务器参数中的相关值。
     *
     * 此方法的实现方式必须确保消息的不变性，并且必须返回具有更新的 cookie 值的实例。
     *
     * @param array $cookies 表示 cookies 的键/值对数组。
     * @return static
     */
    public function withCookieParams(array $cookies)
    {
        if ($this->cookieParams === $cookies) {
            return $this;
        }

        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /**
     * 检索查询字符串参数
     *
     * @return array
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * 返回具有指定查询字符串参数的实例。
     *
     * 这些值在传入请求的过程中应该保持不变。
     * 它们可以在实例化期间被注入，例如从 PHP 的 $_GET，或者可以从诸如 URI 之类的其他值派生。
     * 在从 URI 解析参数的情况下，数据必须与 PHP 的 parse_str() 返回的内容兼容，以了解如何处理重复的查询参数以及如何处理嵌套集。
     *
     * 设置查询字符串参数不得更改请求存储的URI，也不能更改服务器参数中的值。
     *
     * 必须以保持消息的不可变性的方式实现此方法，并且必须返回具有更新的查询字符串参数的实例。
     *
     * @param array $query 查询字符串参数数组，通常来自 $_GET
     * @return static
     */
    public function withQueryParams(array $query): self
    {
        if ($this->queryParams === $query) {
            return $this;
        }

        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /**
     * 检索规范化文件上载数据
     *
     * @return UploadedFileInterface[]
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * 使用指定上载文件创建新实例
     *
     * @param UploadedFileInterface[] $uploadedFiles
     * @return static
     */
    public function withUploadedFiles(array $uploadedFiles): self
    {
        foreach ($uploadedFiles as $file) {
            if (!$file instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('UploadedFiles must be an array of UploadedFileInterface instances.');
            }
        }

        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /**
     * 检索请求主体中提供的任何参数。
     *
     * 如果请求内容类型为 application/x-www-form-urlencoded 或 multipart/form-data，并且请求方法为 POST，则该方法必须返回 $_POST 的内容。
     *
     * 否则，该方法可以返回反序列化请求主体内容的任何结果；当解析返回结构化内容时，潜在类型必须是数组或对象。空值表示缺少正文内容。
     *
     * @return null|array|object 反序列化的主体参数（如果有）。这些通常是数组或对象。
     */
    public function getParsedBody()
    {
        return $this->parseBody;
    }

    /**
     * 返回具有指定主体参数的实例。
     *
     * 这些可以在实例化期间注入。
     *
     * 如果请求内容类型为 application/x-www-form-urlencoded 或 multipart/form-data，并且请求方法为 POST，则仅使用此方法注入 $_POST 的内容。
     *
     * 数据不必来自 $_POST，但必须是反序列化请求正文内容的结果。反序列化/解析返回结构化数据，因此，此方法只接受数组或对象，如果没有可解析的内容，则只接受空值。
     *
     * 例如，如果内容协商确定请求数据是 JSON 负载，则可以使用此方法创建具有反序列化参数的请求实例。
     *
     * 此方法的实现方式必须保持消息的不变性，并且必须返回具有更新的主体参数的实例。
     *
     * @param null|array|object $data 反序列化的正文数据。这通常在数组或对象中。
     * @return static
     * @throws InvalidArgumentException 如果提供了不支持的参数类型。
     */
    public function withParsedBody($data): self
    {
        if (!is_object($data) && !is_array($data) && !is_null($data)) {
            throw new InvalidArgumentException('Parsed body must be an object, array, or null.');
        }

        if ($this->parseBody === $data) {
            return $this;
        }

        $new = clone $this;
        $new->parseBody = $data;
        return $new;
    }

    /**
     * 检索从请求派生的属性。
     *
     * 请求“属性”可用于允许注入从请求导出的任何参数：例如，路径匹配操作的结果；解密 cookies 的结果；反序列化非表单编码消息体的结果；属性将是特定于应用程序和请求的，并且可以是可变的。
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * 检索单个派生请求属性。
     *
     * 检索单个派生请求属性，如 getAttributes() 中所述。如果以前未设置该属性，则返回所提供的默认值。
     *
     * 此方法不需要 hasAttribute() 方法，因为它允许指定未找到属性时返回的默认值。
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     * @see getAttributes()
     */
    public function getAttribute($name, $default = null)
    {
        return key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * 返回具有指定派生请求属性的实例。
     *
     * 此方法允许设置单个派生请求属性，如 getAttributes() 中所述。
     *
     * 此方法的实现方式必须保持消息的不变性，并且必须返回具有 updated 属性的实例。
     *
     * @param string $name
     * @param mixed $value
     * @return static
     * @see getAttributes()
     */
    public function withAttribute($name, $value): self
    {
        if (key_exists($name, $this->attributes) && $this->attributes[$name] === $value) {
            return $this;
        }

        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    /**
     * 返回删除指定派生请求属性的实例
     *
     * 返回一个实例，该实例删除指定的派生请求属性。
     *
     * 此方法允许删除 getAttributes() 中描述的单个派生请求属性。
     *
     * 必须以保持消息的不可变性的方式实现此方法，并且必须返回一个删除属性的实例。
     *
     * @param string $name
     * @return static
     * @see getAttributes()
     */
    public function withoutAttribute($name): self
    {
        if (!key_exists($name, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
