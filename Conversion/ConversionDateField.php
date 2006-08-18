<?php

require_once 'Conversion/ConversionField.php';
require_once 'Swat/SwatDate.php';

class ConversionDateField extends ConversionField
{
	public $src_timezone = 'America/Halifax';
	public $dst_timezone = 'UTC';

	// conversion methods
	// {{{ protected function convertData()

	public function convertData($data)
	{
		$data = parent::convertData($data);

		$date = new SwatDate($data);
		$date->setTZbyID($this->src_timezone);
		$date->convertTZbyID($this->dst_timezone);

		$data = $date->getDate();

		return $data;
	}

	// }}}
	
}

?>
