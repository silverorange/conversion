<?php

/**
 * Converts a column to a boolean column
 *
 * @package   Conversion
 * @copyright 2006 silverorange
 */
class ConversionBooleanField extends ConversionField
{
	// {{{ public properties

	public $inverse = false;

	// }}}
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
