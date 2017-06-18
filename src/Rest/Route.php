<?php
/**
 * NormalRoute
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Rest;

/**
 * Class Route
 * @package PG\MSF\Route
 */
class Route extends \PG\MSF\Route\NormalRoute
{
    /**
     * @var array
     * support verb
     */
    public static $verbs = [
        'GET',      // 从服务器取出资源（一项或多项）
        'POST',     // 在服务器新建一个资源
        'PUT',      // 在服务器更新资源（客户端提供改变后的完整资源）
        'PATCH',    // 在服务器更新资源（客户端提供改变的属性）
        'DELETE',   // 从服务器删除资源
        'HEAD',     // 获取 head 元数据
        'OPTIONS',  // 获取信息，关于资源的哪些属性是客户端可以改变的
    ];
    /**
     * @var bool
     */
    public $enableCache = false;
    /**
     * @var array
     */
    public $restRules = [];
    /**
     * @var string
     * The name of the POST parameter that is used to indicate if a request is a PUT, PATCH or DELETE
     */
    public $methodParam = '_method';
    /**
     * @var string
     */
    public $verb;
    /**
     * @var array
     */
    public $patterns = [
        'PUT,PATCH {id}' => 'update', // 更新资源，如：/users/<id>
        'DELETE {id}' => 'delete', // 删除资源，如：/users/<id>
        'GET,HEAD {id}' => 'view', // 查看资源单条数据，如：/users/<id>
        'POST' => 'create', // 新建资源，如：/users
        'GET,HEAD' => 'index', // 查看资源列表数据（可分页），如：/users
        '{id}' => 'options', // 查看资源所支持的HTTP动词，如：/users/<id> | /users
        '' => 'options',
    ];

    /**
     * Route constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initRules();
    }

    /**
     * 处理http request
     * @param $request
     */
    public function handleClientRequest($request)
    {
        $this->clientData->path = rtrim($request->server['path_info'], '/');
        $this->verb = $this->getVerb($request);
        $data = $this->parseRule();
        if (!isset($data[0])) {
            throw new \Exception('');
        }
        $this->parsePath($data[0]);
        // 将 path 中含有的参数放入 get 中
        if (!empty($data[1])) {
            foreach ($data[1] as $name => $value) {
                $request->get[$name] = $value;
            }
        }
    }

    /**
     * get request verb
     * @param Object $request
     */
    public function getVerb($request)
    {
        if (isset($request->post[$this->methodParam])) {
            return strtoupper($request->post[$this->methodParam]);
        }
        if (isset($request->server['http_x_http_method_override'])) {
            return strtoupper($request->server['http_x_http_method_override']);
        }
        if (isset($request->server['request_method'])) {
            return strtoupper($request->server['request_method']);
        }

        return 'GET';
    }

    /**
     * parse Rest Rules, return path
     * @return array
     */
    public function parseRule()
    {
        if (empty($this->restRules)) {
            return [];
        }
        $pathInfo = $this->clientData->path;
        foreach ($this->restRules as $rule) {
            if (!in_array($this->verb, $rule[0])) {
                continue;
            }
            if (!preg_match($rule[1][0], $pathInfo, $matches)) {
                continue;
            }

            $patternParams = $rule[1][1];
            $pathParams = $rule[1][2];
            $placeholders = $rule[1][3];

            foreach ($placeholders as $placeholder => $name) {
                if (isset($matches[$placeholder])) {
                    $matches[$name] = $matches[$placeholder];
                    unset($matches[$placeholder]);
                }
            }

            $params = [];
            $tr = [];
            foreach ($matches as $key => $value) {
                if (isset($pathParams[$key])) {
                    $tr[$pathParams[$key]] = $value;
                    unset($params[$key]);
                } elseif (isset($patternParams[$key])) {
                    $params[$key] = $value;
                }
            }
            $rule[2] = strtr($rule[2], $tr);

            return [$rule[2], $params];
        }

        return [];
    }

    /**
     * 解析path
     *
     * @param $path
     */
    public function parsePath($path)
    {
        $route = explode('/', $path);
        $route = array_map(function ($name) {
            $name = ucfirst($name);
            return $name;
        }, $route);
        $methodName = array_pop($route);
        $this->clientData->controllerName = ltrim(implode("\\", $route), "\\")??null;
        $this->clientData->methodName = $methodName;
    }

    /**
     * Returns whether this is a GET request.
     * @return bool whether this is a GET request.
     */
    public function getIsGet()
    {
        return $this->verb === 'GET';
    }

    /**
     * Returns whether this is an OPTIONS request.
     * @return bool whether this is a OPTIONS request.
     */
    public function getIsOptions()
    {
        return $this->verb === 'OPTIONS';
    }

    /**
     * Returns whether this is a HEAD request.
     * @return bool whether this is a HEAD request.
     */
    public function getIsHead()
    {
        return $this->verb === 'HEAD';
    }

    /**
     * Returns whether this is a POST request.
     * @return bool whether this is a POST request.
     */
    public function getIsPost()
    {
        return $this->verb === 'POST';
    }

    /**
     * Returns whether this is a DELETE request.
     * @return bool whether this is a DELETE request.
     */
    public function getIsDelete()
    {
        return $this->verb === 'DELETE';
    }

    /**
     * Returns whether this is a PUT request.
     * @return bool whether this is a PUT request.
     */
    public function getIsPut()
    {
        return $this->verb === 'PUT';
    }

    /**
     * Returns whether this is a PATCH request.
     * @return bool whether this is a PATCH request.
     */
    public function getIsPatch()
    {
        return $this->verb === 'PATCH';
    }

    /**
     * init Rules
     * @return array
     */
    protected function initRules()
    {
        $rules = getInstance()->config->get('rest.route.rules', []);
        if (empty($rules)) {
            return;
        }

        $verbs = 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS';
        foreach ($rules as $pattern => $path) {
            // 分离 verb 和 pattern
            if (preg_match("/^((?:($verbs),)*($verbs))(?:\\s+(.*))?$/", $pattern, $matches1)) {
                $matchVerbs = explode(',', $matches1[1]);
                $pattern = isset($matches1[4]) ? $matches1[4] : '';
            } else {
                $matchVerbs = [];
            }

            $patternParams = []; // pattern 里含有<>
            $pathParams = []; // path 里含有<>
            $placeholders = [];

            // pattern 预处理
            $pattern = $this->trimSlashes($pattern);
            if ($pattern === '') {
                $pattern = '#^$#u';
                $this->restRules[] = [
                    $matchVerbs,
                    [$pattern, [], [], []],
                    $path
                ];
                continue;
            } else {
                $pattern = '/' . $pattern . '/';
            }

            // 解析如果path里面含有<>
            if (strpos($path, '<') !== false && preg_match_all('/<([\w._-]+)>/', $path, $matches)) {
                foreach ($matches[1] as $name) {
                    $pathParams[$name] = "<$name>";
                }
            }

            // 解析如果pattern里面含有<>
            $tr = [
                '.' => '\\.',
                '*' => '\\*',
                '$' => '\\$',
                '[' => '\\[',
                ']' => '\\]',
                '(' => '\\(',
                ')' => '\\)',
            ];
            $tr2 = [];
            if (preg_match_all('/<([\w._-]+):?([^>]+)?>/', $pattern, $matches2, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($matches2 as $match) {
                    $name = $match[1][0];
                    $tPattern = isset($match[2][0]) ? $match[2][0] : '[^\/]+';
                    $placeholder = 'a' . hash('crc32b', $name); // placeholder must begin with a letter
                    $placeholders[$placeholder] = $name;
                    $tr["<$name>"] = "(?P<$placeholder>$tPattern)";
                    if (isset($pathParams[$name])) {
                        $tr2["<$name>"] = "(?P<$placeholder>$tPattern)";
                    } else {
                        $patternParams[$name] = $tPattern === '[^\/]+' ? '' : "#^$tPattern$#u";
                    }
                }
            }
            $pattern = '#^' . trim(strtr($pattern, $tr), '/') . '$#u';

            // 组装数据
            $item = [
                $matchVerbs,
                [
                    $pattern,       // 0
                    $patternParams, // 1
                    $pathParams,    // 2
                    $placeholders   // 3
                ],
                $path
            ];

            $this->restRules[] = $item;
        }
    }

    /**
     * Trim slashes in passed string. If string begins with '//', two slashes are left as is
     * in the beginning of a string.
     *
     * @param string $string
     * @return string
     */
    protected function trimSlashes($string)
    {
        if (strpos($string, '//') === 0) {
            return '//' . trim($string, '/');
        }
        return trim($string, '/');
    }
}
