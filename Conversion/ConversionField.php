<?php

require_once 'SwatDB/SwatDBField.php';

class ConversionField
{
	// ref to parent ConversionTable object
	public $table;

	public $src_field = null;
	public $dst_field = null;
	public $value = null;

	// {{{ public function __construct()

	public function __construct($field = null) {
		$this->src_field = $field;
	}

	// }}}
	// {{{ public function init()

	public function init()
	{
		if ($this->dst_field === null)
			$this->dst_field = $this->src_field;

		$default_type = $this->getDefaultType();

		if ($this->src_field !== null)
			$this->src_field = new SwatDBField($this->src_field, $default_type);

		if ($this->dst_field !== null)
			$this->dst_field = new SwatDBField($this->dst_field, $default_type);
	}

	// }}}
	// {{{ protected function getDefaultType()

	protected function getDefaultType()
	{
		return 'text';
	}

	// }}}

	// conversion methods
	// {{{ protected function convertData()

	public function convertData($data)
	{
		if ($this->src_field === null)
			return $this->value;

		return $data;
	}

	// }}}
	
}

require_once 'Conversion/ConversionTextField.php';
require_once 'Conversion/ConversionBooleanField.php';

?>
