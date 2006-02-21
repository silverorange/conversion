<?php

require_once 'Conversion/ConversionField.php';

class ConversionTable
{
	// ref to parent ConversionProcess object
	public $process;

	public $src_table = null;
	public $dst_table = null;

	private $deps = array();
	private $fields = array();
	private $id_field = null;

	// {{{ public function addField()

	public function addField($field)
	{
		$field->table = $this;
		$field->init();
		$this->fields[] = $field;
	}

	// }}}
	// {{{ public function setIDField()

	public function setIDField($field)
	{
		$this->id_field = $field;
	}

	// }}}
	// {{{ public function addDep()

	public function addDep($dst_table_name)
	{
		$this->deps[] = $dst_table_name;
	}

	// }}}
	// {{{ public function getDeps()

	public function getDeps()
	{
		return $this->deps;
	}

	// }}}
	// {{{ public function init()

	public function init()
	{
	}

	// }}}
	// {{{ public function run()

	public function run()
	{
		$rs = $this->getSourceRecordset();
		$this->clearDestinationTable();
		$row = $this->getSourceRow($rs);

		while ($row !== null) {
			$row = $this->convertRow($row);
			$this->insertDestinationRow($row);
			$row = $this->getSourceRow($rs);
		}
	}

	// }}}
	// {{{ public function getFieldByDestinationName()

	public function getFieldIndexByDestinationName($name)
	{
		foreach ($this->fields as $index => $field)
			if ($name == $field->dst_field->name)
				return $index;

		throw new SwatException("Conversion field with destination field name '$name' not found.");
	}

	// }}}

	// source methods
	// {{{ protected function getSourceRow($rs)

	protected function getSourceRow($rs)
	{
		return $rs->fetchRow();
	}

	// }}}
	// {{{ protected function getSourceRecordset()

	protected function getSourceRecordset()
	{
		$sql = $this->getSourceQuery();
		$rs = SwatDB::query($this->process->src_db, $sql, null);
		return $rs;
	}

	// }}}
	// {{{ protected function getSourceQuery()

	protected function getSourceQuery()
	{
		$select_list = array();

		foreach ($this->fields as $field) {
			if ($field->src_field === null)
				$select_list[] = 'null';
			else
				$select_list[] = $field->src_field->name;
		}

		$sql = sprintf('select %s from %s',
			implode(', ', $select_list),
			$this->src_table);

		return $sql;
	}

	// }}}
	// {{{ protected function getSourceMaxId()

	protected function getSourceMaxId()
	{
		if ($this->id_field === null)
			throw new SwatException('No ID field specified.');

		if ($this->id_field->src_field->type === 'integer')
			throw new SwatException('Unable to query max since source ID field is non-integer.');

		$sql = sprintf('select max(%s) from %s',
			$this->id_field->src_field->name,
			$this->src_table);

		return SwatDB::queryOne($this->process->src_db, $sql);
	}

	// }}}
	// {{{ protected function getSourceRecordCount()

	protected function getSourceRecordCount()
	{
		$sql = sprintf('select count(*) from %s',
			$this->src_table);

		return SwatDB::queryOne($this->process->src_db, $sql);
	}

	// }}}

	// conversion methods
	// {{{ protected function convertRow()

	protected function convertRow($row)
	{
		$i = 0;

		foreach ($this->fields as $field) {
			$row[$i] = $field->convertData($row[$i]);
			$i++;
		}

		return $row;
	}

	// }}}
	
	// destination methods
	// {{{ protected function insertDestinationRow()

	protected function insertDestinationRow($row)
	{
		$sql = $this->getDestinationInsert();
		$i = 0;

		foreach ($this->fields as $field) {
			$row[$i] = $this->process->dst_db->quote($row[$i], $field->dst_field->type);
			$i++;
		}

		$sql = vsprintf($sql, $row);
		SwatDB::exec($this->process->dst_db, $sql);
	}

	// }}}
	// {{{ protected function getDesinationInsert()

	protected function getDestinationInsert()
	{
		$insert_list = array();
		$value_list = array();

		foreach ($this->fields as $field) {
			$insert_list[] = $field->dst_field->name;
			$value_list[] = '%s';
		}

		$sql = sprintf('insert into %s (%s) values (%s)',
			$this->dst_table,
			implode(', ', $insert_list),
			implode(', ', $value_list));

		return $sql;
	}

	// }}}
	// {{{ protected function getDestinationMaxId()

	protected function getDestinationMaxId()
	{
		if ($this->id_field === null)
			throw new SwatException('No ID field specified.');

		if ($this->id_field->dst_field->type === 'integer')
			throw new SwatException('Unable to query max since destination ID field is non-integer.');

		$sql = sprintf('select max(%s) from %s',
			$this->id_field->dst_field->name,
			$this->dst_table);

		return SwatDB::queryOne($this->process->dst_db, $sql);
	}

	// }}}
	// {{{ protected function getDestinationRecordCount()

	protected function getDestinationRecordCount()
	{
		$sql = sprintf('select count(*) from %s',
			$this->dst_table);

		return SwatDB::queryOne($this->process->dst_db, $sql);
	}

	// }}}
	// {{{ protected function clearDestinationTable()

	protected function clearDestinationTable()
	{
		$sql = sprintf('delete from %s',
			$this->dst_table);

		SwatDB::exec($this->process->dst_db, $sql);
	}

	// }}}
}

?>
