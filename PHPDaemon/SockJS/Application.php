<?php
namespace PHPDaemon\SockJS;
use PHPDaemon\HTTPRequest\Generic;
use PHPDaemon\Core\Daemon;
use PHPDaemon\Core\Debug;
/**
 * @package    Libraries
 * @subpackage SockJS
 *
 * @author     Zorin Vasily <maintainer@daemon.io>
 */
class Application extends \PHPDaemon\Core\AppInstance {
	protected $redis;
	public $wss;

	protected $sessions;
	/**
	 * Setting default config options
	 * @return array|bool
	 */
	protected function getConfigDefaults() {
		return [
			'redis-name' => '',
			'redis-prefix' => 'sockjs:',
			'wss-name' => '',
		];
	}


	public function subscribe($chan, $cb) {
		D($this->config->redisprefix->value . $chan);
		$this->redis->subscribe($this->config->redisprefix->value . $chan, $cb);
	}

	public function unsubscribe($chan, $cb, $opcb = null) {
		$this->redis->unsubscribe($this->config->redisprefix->value . $chan, $cb, $opcb);
	}

	public function publish($chan, $cb, $opcb = null) {
		$this->redis->publish($this->config->redisprefix->value . $chan, $cb, $opcb);
	}

	/**
	 * Called when the worker is ready to go.
	 * @return void
	 */
	public function onReady() {
		$this->redis = \PHPDaemon\Clients\Redis\Pool::getInstance($this->config->redisname->value);
		$this->wss = \PHPDaemon\Servers\WebSocket\Pool::getInstance($this->config->wssname->value);
		$this->sessions = new SessionsStorage;
	}

	public function beginSession($path, $server) {
		if (!$route = $this->wss->getRoute($path)) {
			return false;
		}
		$this->sessions->attach($sess = new Session($route, $appInstance, $sessId, $server));
		return $sess;
	}

	/**
	 * Creates Request.
	 * @param object Request.
	 * @param object Upstream application instance.
	 * @return object Request.
	 */
	public function beginRequest($req, $upstream) {
		$e = explode('/', $req->attrs->server['DOCUMENT_URI']);
		$method = array_pop($e);
		$serverId = null;
		$sessId = null;
		if ($method === 'websocket') {
			if ((sizeof($e) > 3) && isset($e[sizeof($e) - 2]) && ctype_digit($e[sizeof($e) - 2])) {
				$sessId = array_pop($e);
				$serverId = array_pop($e);
			}
		}
		elseif ($method === 'info') {

		} elseif (in_array($method, ['xhr', 'xhr_send'])) {
			D($e);
			$sessId = array_pop($e);
			$serverId = array_pop($e);
		}
		$path = implode('/', $e);
		$class = __NAMESPACE__ . '\\' .strtr(ucfirst($method), ['_' => '']);
		$req = new $class($this, $upstream, $req);
		$req->setSessId($sessId);
		$req->setServerId($serverId);
		$req->setPath($path);
		return $req;
	}
}