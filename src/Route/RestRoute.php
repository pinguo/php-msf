<?php
/**
 * Restful Api路由
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Route;

/**
 * Class RestRoute
 * @package PG\MSF\Route
 */
class RestRoute extends NormalRoute
{
    /**
     * @var array 支持HTTP动作
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
     * @var bool 路径cache
     */
    public $enableCache = false;

    /**
     * @var array 路径规则
     */
    public $restRules = [];

    /**
     * @var string HTTP请求方法
     */
    public $verb;

    /**
     * @var array
     */
    //public $patterns = [
    //    'PUT,PATCH {id}' => 'update', // 更新资源，如：/users/<id>
    //    'DELETE {id}' => 'delete', // 删除资源，如：/users/<id>
    //    'GET,HEAD {id}' => 'view', // 查看资源单条数据，如：/users/<id>
    //    'POST' => 'create', // 新建资源，如：/users
    //    'GET,HEAD' => 'index', // 查看资源列表数据（可分页），如：/users
    //    '{id}' => 'options', // 查看资源所支持的HTTP动词，如：/users/<id> | /users
    //    '' => 'options',
    //];

    /**
     * Route constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initRules();
    }

    /**
     * HTTP请求解析
     *
     * @param \swoole_http_request $request 请求对象
     */
    public function handleHttpRequest($request)
    {
        $this->parseRequestBase($request);

        $data = $this->parseRule();
        $path = $data[0] ?? $this->routeParams->path;
        if ($path) {
            $this->parsePath($path);
        }
        if (!empty($data[1])) {
            foreach ($data[1] as $name => $value) {
                $request->get[$name] = $value;
            }
        }
    }

    /**
     * 解析路由规则
     *
     * @return array
     */
    public function parseRule()
    {
        if (empty($this->restRules)) {
            return [];
        }
        $pathInfo = $this->trimSlashes($this->routeParams->path);
        foreach ($this->restRules as $rule) {
            if (!in_array($this->routeParams->verb, $rule[0])) {
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
                    //unset($params[$key]);
                    $params[$key] = $value;
                } elseif (isset($patternParams[$key])) {
                    $params[$key] = $value;
                }
            }
            $rule[2] = '/' . strtr($rule[2], $tr);

            return [$rule[2], $params];
        }

        return [];
    }

    /**
     * 初始化Rules
     *
     * @return array|mixed
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
            if (strpos($path, '<') !== false && preg_match_all('/<([\w._-]+)>/', $path, $matches2)) {
                foreach ($matches2[1] as $name) {
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
            if (preg_match_all('/<([\w._-]+):?([^>]+)?>/', $pattern, $matches3, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($matches3 as $match) {
                    $name = $match[1][0];
                    $tmpPattern = isset($match[2][0]) ? $match[2][0] : '[^\/]+';
                    $placeholder = 'a' . hash('crc32b', $name);
                    $placeholders[$placeholder] = $name;
                    $tr["<$name>"] = "(?P<$placeholder>$tmpPattern)";
                    if (isset($pathParams[$name])) {
                        $tr2["<$name>"] = "(?P<$placeholder>$tmpPattern)";
                    } else {
                        $patternParams[$name] = $tmpPattern === '[^\/]+' ? '' : "#^$tmpPattern$#u";
                    }
                }
            }
            $pattern = preg_replace('/<([\w._-]+):?([^>]+)?>/', '<$1>', $pattern);
            $pattern = '#^' . trim(strtr($pattern, $tr), '/') . '$#u';

            // 组装数据
            $this->restRules[] = [
                $matchVerbs,
                [
                    $pattern,       // 0
                    $patternParams, // 1
                    $pathParams,    // 2
                    $placeholders   // 3
                ],
                $path
            ];
        }
    }

    /**
     * 去掉下划线
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
