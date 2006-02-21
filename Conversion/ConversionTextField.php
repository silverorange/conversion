<?php

require_once 'Conversion/ConversionField.php';

class ConversionTextField extends ConversionField
{
	public $src_charset = 'ISO-8859-1';
	public $dst_charset = 'UTF-8';

	// conversion methods
	// {{{ protected function convertData()

	public function convertData($data)
	{
		$data = parent::convertData($data);
		$data = iconv($this->src_charset, $this->dst_charset, $data);
		return $data;
	}

	// }}}
	
}

?>
