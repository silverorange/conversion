<?php

require_once 'Conversion/ConversionField.php';

/**
 * Converts a column to a text column
 *
 * Supports conversion of character encoding.
 *
 * @package   Conversion
 * @copyright 2006 silverorange
 */
class ConversionTextField extends ConversionField
{
	// {{{ public properties

	public $src_charset = 'ISO-8859-1';
	public $dst_charset = 'UTF-8';
	public $trim = false;
	public $empty_to_null = false;

	// }}}

	// conversion methods
	// {{{ protected function convertData()

	public function convertData($data)
	{
		$data = parent::convertData($data);
		$data = iconv($this->src_charset, $this->dst_charset, $data);

		if ($this->trim)
			$data = trim($data);

		if ($this->empty_to_null)
			$data = (strlen($data) > 0) ? $data : null;

		return $data;
	}

	// }}}
}

?>
