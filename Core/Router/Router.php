<?php
namespace Core\Router;

/**
 * Router.php
 *
 * @author Michael "Tekkla" Zorn <tekkla@tekkla.de>
 * @copyright 2016
 * @license MIT
 */
class Router extends \AltoRouter implements \ArrayAccess
{

    /**
     * Status flag ajax
     *
     * @var bool
     */
    private $ajax = false;

    /**
     * Default return format
     *
     * @var string
     */
    private $format = 'html';

    /**
     *
     * @var array
     */
    private $match = [];

    /**
     *
     * @var string
     */
    private $request_url = '';

    /**
     * List of names to move to target when occur as parameter
     *
     * @var array
     */
    private $parameter_to_target = [];

    /**
     *
     * @var Router $instance
     */
    private static $instance;

    /**
     * Singleton instance factory
     *
     * @return Router
     */
    public function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Sets a list of names which should be moved to target when they occur as parameter
     *
     * @param array $parameter_names
     *            List of parameternames to move to target
     */
    public function setParametersToTarget(array $parameter_names)
    {
        $this->parameter_to_target = $parameter_names;
    }

    /**
     * Sets a parameter
     *
     * @param string $param
     *            The parameters name
     * @param mixed $value
     *            The value to set as parameter
     */
    public function setParam($param, $value)
    {
        $this->match['params'][$param] = $value;
    }

    /**
     * Returns a parameter value
     *
     * @param string $param
     *            Name of param to return value of
     *
     * @return mixed
     */
    public function getParam($param)
    {
        if (!empty($this->match['params'][$param])) {
            return $this->match['params'][$param];
        }
    }

    /**
     * Returns all params
     *
     * @return array
     */
    public function getParams()
    {
        return $this->match['params'] ?? [];
    }

    /**
     * Reversed routing for generating the URL for a named route
     *
     * @param string $route_name
     *            The name of the route.
     * @param array $params
     *            Associative array of parameters to replace placeholders with.
     *
     * @return string The URL of the route with named parameters in place.
     */
    public function url($route_name, $params = [])
    {
        return $this->generate($route_name, $params);
    }

    /**
     * Nearly the same as map() method of AltoRouter
     *
     * The only difference is in how app and generic routes are handled.
     * App routes will be put in front of generic routes to prevent that similiar but from app to handle routes are
     * treated as generic routes.
     *
     * {@inheritDoc}
     *
     * @see AltoRouter::map()
     */
    public function map($method, $route, $target, $name = null)
    {
        $map = [
            $method,
            $route,
            $target,
            $name
        ];

        if (!empty($name) && strpos($name, 'generic.') === false) {
            array_unshift($this->routes, $map);
        }
        else {
            $this->routes[] = $map;
        }

        if (!empty($name)) {

            if (isset($this->namedRoutes[$name])) {
                Throw new RouterException(sprintf('Can not redeclare route "%s"', $name));
            }

            $this->namedRoutes[$name] = $route;
        }
    }

    /**
     * Match a given request url against stored routes
     *
     * @param string $request_url
     * @param string $request_method
     *
     * @return array boolean with route information on success, false on failure (no match).
     */
    public function match($request_url = null, $request_method = null)
    {

        // Set Request Url if it isn't passed as parameter
        if ($request_url === null) {
            $request_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        }

        $this->request_url = $request_url;

        // Set Request Method if it isn't passed as a parameter
        if ($request_method === null) {
            $request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }

        // Framework.js adds automatically an /ajax flag @ the end of the requested URI.
        // Here we check for this flag, remembers if it's present and then remove the flag
        // so the following URI matching process runs without flaw.
        if (substr($request_url, -5) == '/ajax') {
            $this->ajax = true;
            $request_url = str_replace('/ajax', '', $request_url);
        }

        $this->match = parent::match($request_url, $request_method);

        if (!empty($this->match)) {

            // Some parameters only have control or workflow character and are no parameters for public use.
            // Those will be removed from the parameters array after using them to set corresponding values and/or flags
            // in router.
            $controls = [
                'ajax',
                'format'
            ];

            foreach ($controls as $key) {

                if (isset($this->match['params'][$key])) {

                    switch ($key) {
                        case 'ajax':
                            $this->ajax = true;
                            break;
                        case 'format':
                            $this->format = $this->match['params'][$key];
                            break;
                        default:
                            $this->{$key} = $this->match['params'][$key];
                    }
                    break;
                }

                unset($this->match['params'][$key]);
            }
        }

        foreach ($this->match['params'] as $key => $val) {
            if (in_array($key, $this->parameter_to_target)) {
                $this->match['target'][$key] = $val;
                unset($this->match['params'][$key]);
            }
        }
    }

    /**
     * Returns the name of the current active route.
     *
     * @return string
     */
    public function getCurrentRoute()
    {
        return $this->match['name'];
    }

    /**
     * Checks for an ajax request and returns boolean true or false
     *
     * @return boolean
     */
    public function isAjax()
    {
        return $this->ajax;
    }

    /**
     * Sets requested output format
     *
     * @param string $format
     *            Output format: xml, json or html
     *
     * @throws RouterException
     */
    public function setFormat($format)
    {
        $allowed = [
            'html',
            'xml',
            'json',
            'file'
        ];

        if (!in_array(strtolower($format), $allowed)) {
            Throw new RouterException(sprintf('Your format "%s" is not an allowed format. Use one of these formats %s', $format, implode(', ', $allowed)));
        }

        $this->format = $format;
    }

    /**
     * Returns the requested outputformat.
     *
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Returns all request related data in one array
     *
     * @return array
     */
    public function getStatus()
    {
        return [
            'url' => $this->request_url,
            'route' => $this->getCurrentRoute(),
            'ajax' => $this->ajax,
            'method' => $_SERVER['REQUEST_METHOD'],
            'format' => $this->format,
            'match' => $this->match
        ];
    }

    /**
     * Returns mapped routes stack
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     *
     * {@inheritDoc}
     *
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        return isset($this->match[$offset]);
    }

    /**
     *
     * {@inheritDoc}
     *
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($offset)
    {
        if (isset($this->match[$offset])) {
            return $this->match[$offset];
        }
    }

    /**
     *
     * {@inheritDoc}
     *
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value)
    {
        $this->match[$offset] = $value;
    }

    /**
     *
     * {@inheritDoc}
     *
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        if (isset($this->match[$offset])) {
            unset($this->match[$offset]);
        }
    }
}
