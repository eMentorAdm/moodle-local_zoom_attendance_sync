<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_zoom_attendance_sync
 * @copyright   2023 e-Mentor srl <service@e-mentor.it>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zoom_attendance_sync\webservices;

defined('MOODLE_INTERNAL') || die();

use Error;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use local_zoom_attendance_sync\event\request_success;
use local_zoom_attendance_sync\event\request_failed;
use stdClass;

global $CFG;
require_once($CFG->dirroot . '/local/zoom_attendance_sync/extlib/vendor/autoload.php');

/**
 * Class rest_client
 * @package local_zoom_attendance_sync\webservices
 */
abstract class generic_rest_client {

    /**
     *
     */
    public const METHOD_GET    = 'GET';
    /**
     *
     */
    public const METHOD_POST   = 'POST';
    /**
     *
     */
    public const METHOD_PUT    = 'PUT';
    /**
     *
     */
    public const METHOD_DELETE = 'DELETE';
    /**
     *
     */
    public const METHOD_PATCH  = 'PATCH';
    /**
     *
     */
    public const METHOD_HEAD  = 'HEAD';


    /**
     * @var Client
     */
    protected $client;
    /**
     * @var array
     */
    protected $request_options = [];
    /**
     * @var
     */
    protected $config;

    /**
     * @var array
     */
    protected $progress = [];

    /**
     * @var array|null
     */
    protected $default_options = [];

    /**
     * @var string
     */
    protected $method = 'GET';

    /**
     * @var bool
     */
    protected $async = false;

    /**
     * @var string
     */
    protected $resource = '';

    protected $templates = [];

    protected $base_uri = '';


    /**
     * rest_client constructor.
     * @param array $options For complete description see https://docs.guzzlephp.org/en/stable/request-options.html
     * @throws \dml_exception
     */
    public function __construct(){
        $this->definition();
        $this->client = new Client(['verify' => false]);
    }

    /**
     * @return Client
     */
    public function get_client ():Client {
        return $this->client;
    }

    /**
     * @return array
     */
    public function get_progress ():array {
        return $this->progress;
    }

    /**
     * @return string
     */
    public function get_base_uri ():string {
        return $this->base_uri;
    }

    /**
     * @param string $base_uri
     */
    public function set_base_uri (string $base_uri):void {
        $this->base_uri = $base_uri;
    }


    /**
     * @param string $key
     * @param $value
     * @return generic_rest_client
     */
    public function set_client_option(string $key, $value):generic_rest_client {
        $this->default_options[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param string|array $value
     * @return generic_rest_client
     */
    public function set_client_header(string $key, $value):generic_rest_client {
        $this->default_options['header'][$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param string|array $value
     * @return generic_rest_client
     */
    public function set_request_option (string $key, $value):generic_rest_client{
        $this->request_options[$key] = $value;
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function set_request_header($key, $value){
        $this->request_options['header'][$key] = $value;
        return $this;
    }

    /**
     * @param $body
     * @return $this
     */
    public function set_request_body ($body) {
        $this->request_options['body'] = $body;
        return $this;
    }

    /**
     * @param $object
     * @return $this
     */
    public function set_request_json ($object) {
        $this->request_options['json'] = $object;
        return $this;
    }

    /**
     * @param array $form
     * @param false $multipart
     * @return $this
     */
    public function set_request_form ($form=[], $multipart=false) {
        $option = 'form_params';
        if ($multipart) {
            $option = 'multipart';
            array_map(
                function ($key, $value) {
                    return [
                        'name' =>  $key,
                        'contents' => $value
                    ];
                },
                array_keys($form), $form);
        }

        $this->request_options[$option] = (isset($this->request_options[$option]) && $this->request_options[$option]) ?
            array_replace_recursive($this->request_options[$option], $form) : $form;
        return $this;
    }

    /**
     * @param array $query
     * @return $this
     */
    public function set_request_query ($query=[]) {
        $this->request_options['query'] = (isset($this->request_options['query']) && $this->request_options['query']) ?
            array_replace_recursive($this->request_options['query'], $query) : $query;
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return generic_rest_client
     */
    public function set_request_template(string $name, ?string $value='', ?string $default_value=''): generic_rest_client
    {

        if (empty($value)) {
            if (empty($default_value)) {
                return $this;
            }
            $this->templates[$name] = $default_value;
            return $this;
        }
        $this->templates[$name] = $value;
        return $this;
    }

    /**
     * @param $resource
     * @param false $async
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface
     */
    public function get ($resource, $async=false) {
        return $this->execute($resource, 'GET', $async);
    }

    /**
     * @param $resource
     * @param false $async
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface
     */
    public function post ($resource, $async=false) {
        return $this->execute($resource, 'POST', $async);
    }

    /**
     * @param $resource
     * @param false $async
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface
     */
    public function put ($resource, $async=false) {
        return $this->execute($resource, 'PUT', $async);
    }

    /**
     * @param $resource
     * @param false $async
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface
     */
    public function patch ($resource, $async=false) {
        return $this->execute($resource, 'PATCH', $async);
    }

    /**
     * @param $resource
     * @param false $async
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface
     */
    public function delete ($resource, $async=false) {
        return $this->execute($resource, 'DELETE', $async);
    }

    /**
     * @param $resource
     * @param false $async
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface
     */
    public function head ($resource, $async=false) {
        return $this->execute($resource, 'HEAD', $async);
    }


    public function send(array $params=[]) {
        if (empty($this->resource)) {
            throw new Error(new \lang_string('error:resourceundefined', 'local_zoom_attendance_sync'));
        }
        if (empty($this->method)) {
            throw new Error(new \lang_string('error:methodundefined', 'local_zoom_attendance_sync'));
        }
        switch ($this->method) {
            case self::METHOD_GET:
                $this->set_request_query($params);
                break;

            CASE self::METHOD_POST:
            CASE self::METHOD_PUT:
                $this->set_request_form($params);
                break;
        }
        return $this->execute($this->build_resourceuri(), $this->method, $this->async);
    }


    /**
     * @param $resource
     * @param string $method
     * @param false $assync
     * @return stdClass
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function execute($resource, $method='GET', $async=false): stdClass
    {

        $this->resource = $resource;

        $this->set_default_queryparams();

        $request = new Request($method, $this->build_resourceuri() . $this->build_querystring(), $this->default_options['header']);

        try {
            $result = ($async) ?
                $this->client->requestAsync($method, '', $this->request_options) :
                $this->client->send($request, $this->request_options);
            $reason = $result->getReasonPhrase();
        } catch (RequestException|\Exception $e) {
            return $this->empty_response($e->getCode(), $e->getMessage());
        }

        $contents = $result->getBody()->getContents();

        return (object) [
            'http_code' => $result->getStatusCode(),
            'http_reason' => $reason,
            'content' => $contents
        ];
    }



    /**
     *
     */
    protected function flush_request_options () {
        $this->request_options = [];
    }

    /**
     * @return void
     */
    protected function definition():void  {
        //To override from child classes
    }

    /**
     * @param string|null $resource
     * @return string
     * @throws \dml_exception
     */
    public function build_resourceuri(string $resource=null):string {
        $uriparam = [];
        $uriparam[] = $this->base_uri;
        $uriparam[] = (!empty($resource)) ? $resource : $this->resource;
        $uri = implode('/', $uriparam);
        $uriparam[] = implode('', array_values($this->templates));
        do{
            $uri = substr($uri, -1) === '/' ? substr($uri, 0, -1) : $uri;
        } while (substr($uri, -1) === '/');

        return $uri;
    }

    public function build_querystring():string {
        $query = [];

        if(empty($this->request_options['query'])){
            return '';
        }

        $params = $this->request_options['query'];

        foreach ($params as $key => $value) {
            $query[] = $key . '=' . $value;
        }
        if (count($query) <= 0) {
            return '';
        }
        return '?' . implode('&', $query);
    }

    protected function set_default_queryparams(){
        /*
        
        */
    }


    /**
     * @param string $contents
     * @param string $code
     * @param string $reason
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function  dispatch_success_event(string $contents, string $code, string $reason):void {
       
            $event = request_success::create(
                array(
                    'context' => \context_system::instance(),
                    'other' => array(
                        'uri' => $this->build_resourceuri() . $this->build_querystring(),
                        'request_options' => json_decode(json_encode($this->request_options), true),
                        'resource' => $this->resource,
                        'status_code' => $code,
                        'reason' => $reason,
                        'response' => $contents,
                    )
                )
            );
            $event->trigger();
        
    }

    /**
     * @param string $message
     * @param int $code
     * @param \Exception $e
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function dispatch_failed_event(string $message, int $code) {
        
            $event = request_failed::create(
                array(
                    'context' => \context_system::instance(),
                    'other' => array(
                        'uri' => $this->build_resourceuri() . $this->build_querystring(),
                        'request_options' => $this->request_options,
                        'resource' => $this->resource,
                        'error_msg' => $message,
                        'error_code' => $code
                    )
                )
            );
            $event->trigger();
       

    }

    /**
     * @param string $code
     * @param string $reason
     * @return stdClass
     */
    protected function empty_response (string $code, string $reason): stdClass {
        return (object) [
            'http_code' => $code,
            'http_reason' => $reason,
            'content' => ''
        ];
    }
}