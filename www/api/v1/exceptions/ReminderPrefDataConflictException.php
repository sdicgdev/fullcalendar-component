<?php

class ReminderPrefDataConflictException extends Exception
{
	static public $Errno = 409;
	static public $Message =
		'The request could not be completed due to a conflict with the current state of the ReminderPref resource.';

	private $resource;
	private $conflicts;
	public function __construct (ReminderPrefs $resource, array $conflicts) {
		parent:: __construct(static:: $Message, static:: $Errno);
		$this->resource = $resource;
		$this->conflicts = $conflicts;
	}
	public function json_encode() {
		return json_encode(array(
			'errno'=>$this->getCode(),
			'message'=>$this->getMessage(),
			'resource'=>$this->resource,
			'conflicts'=>$this->conflicts
		));
	}
}