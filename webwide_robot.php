<?php

class WWRobot {

	private $user;
	private $pass;
	private $response;
	private $error;
	private $testmode;
	private $url;

	public function __construct($params)
	{
		$this->testmode = true;
		$this->url = $params['ApiUrl'];
		$this->user = $params['ApiUser'];
		$this->pass = $params['ApiPass'];
		if(substr($this->url, -1, 1) != '/') $this->url .= '/';
	}

	public function call($method, $function, $params=array())
	{
		$curl = curl_init();
		$url = $this->url.$function;

		$params['testmode'] = $this->testmode;

		if($method == 'POST' || $method == 'PUT' || $method == 'DELETE') curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
		if($method == 'GET') $url = sprintf("%s?%s", $url, http_build_query($params));

		if(@$params['testmode'])
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		}

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERPWD, $this->user.':'.$this->pass);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$this->response = json_decode(curl_exec($curl));

		curl_close($curl);
	}

	public function get($var, $default='')
	{
		if(!$this->success() || !$this->response->result) return $default;
		return isset($this->response->result->$var) ? $this->response->result->$var : $default;
	}

	public function success()
	{
		return (!$this->response || @$this->response->returncode != 200) ? false : true;
	}

	public function getErrorMessage()
	{
		$message = is_array($this->response->returnmessage) ? implode(" | ", $this->response->returnmessage) : $this->response->returnmessage;
		return $this->success() ? '' : $message;
	}

	public function getErrorCode()
	{
		if(!$this->response || !$this->response->returncode) return 500;
		return $this->response->returncode;
	}

}