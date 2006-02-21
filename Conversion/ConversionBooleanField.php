<?php

require_once 'Conversion/ConversionField.php';

class ConversionBooleanField extends ConversionField
{
	public $inverse = false;

	// {{{ protected function getDefaultType()

	protected function getDefaultType()
	{
		return 'boolean';
	}

	// }}}

	// conversion methods
	// {{{ protected function convertData()

	public function convertData($data)
	{
		$data = parent::convertData($data);

		if ($this->inverse)
			$data = !$data;
		
		return $data;
	}

	// }}}
	
}

?>
