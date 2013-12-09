<?php

class ExistingUserException extends Exception
{
	static public $Errno = 406;
	static public $Message = "User name is already in use.";
	private $request;
	public function __construct ($request = NULL) {
		parent:: __construct(static:: $Message, static:: $Errno);
		$this->request = $request;
	}
	public function json_encode() {
		$pkg = array(
			'errno'=>$this->getCode(),
			'message'=>$this->getMessage()
		);
		if ($this->request !== NULL)
			$pkg['requested'] = $this->request;
		error_log(print_r($Errno,true));
		header("HTTP/1.0 {$this->getCode()} {$this->getMessage()}");
		return json_encode($pkg);
	}
}