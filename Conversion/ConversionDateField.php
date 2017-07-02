<?php

/**
 * Converts a column to a date column
 *
 * Supports conversion of time-zone.
 *
 * @package   Conversion
 * @copyright 2006 silverorange
 */
class ConversionDateField extends ConversionField
{
	// {{{ public properties

	public $convert_null_to_now = true;
	public $src_tz_id = 'America/Halifax';
	public $dst_tz_id = 'UTC';

	// }}}

	// conversion methods
	// {{{ protected function convertData()

	public function convertData($data)
	{
		$data = parent::convertData($data);

		if ($data === null && !$this->convert_null_to_now)
			return null;

		$date = new SwatDate($data);
		$date->setTZbyID($this->src_tz_id);
		$date->convertTZbyID($this->dst_tz_id);

		$data = $date->getDate();

		return $data;
	}

	// }}}
}

?>
