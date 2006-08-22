<?php

require_once 'Conversion/ConversionField.php';

class ConversionTable
{
	// {{{ public properties

	// ref to parent ConversionProcess object
	public $process;

	public $src_table = null;
	public $dst_table = null;

	public $clear_data = false;
	public $set_sequence = true;
	public $custom_where_clause = null;

	// }}}
	// {{{ protected properties

	protected $fields = array();
	protected $id_field = null;

	// }}}
	// {{{ private properties

	private $deps = array();
	private $current_row;

	// }}}

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
	// {{{ public function getCurrentRow()

	public function &getCurrentRow()
	{
		return $this->current_row;
	}

	// }}}
	// {{{ public function init()

	public function init()
	{
	}

	// }}}
	// {{{ public function run_pass1()

	public function run_pass1()
	{
		$table_name = get_class($this);
		printf("Pass 1: Converting table (%s)... ", $table_name);
		$count = 0;

		if ($this->clear_data || $this->id_field === null)
			$count = $this->clearDestinationTable();

		echo "$count rows deleted\n";
	}

	// }}}
	// {{{ public function run_pass2()

	public function run_pass2()
	{
		$table_name = get_class($this);
		$msg = sprintf("Pass 2: Converting table (%s)... ", $table_name);
		echo $msg;

		if ($this->clear_data || $this->id_field === null)
			$max_id = null;
		else
			$max_id = $this->getDestinationMaxId();

		$rs = $this->getSourceRecordset($max_id);

		$count = 0;		
		$row = $this->getSourceRow($rs);

		while ($row !== null) {
			if ($count % 100 == 0)
				echo "\r", $msg, "$count rows inserted";

			$this->current_row = &$row;
			$count++;
			$this->convertRow($row);
			$this->insertDestinationRow($row);
			$row = $this->getSourceRow($rs);
		}

		if ($this->set_sequence &&
			$this->id_field !== null &&
			$this->id_field->dst_field->type === 'integer')
				$this->setDestinationSequence();

		$this->finalize();

		echo "\r", $msg, "$count rows inserted\n";
		return;
	}

	// }}}
	// {{{ public function getFieldIndexByDestinationName()

	public function getFieldIndexByDestinationName($name)
	{
		foreach ($this->fields as $index => $field)
			if ($name == $field->dst_field->name)
				return $index;

		throw new SwatException("Conversion field with destination field name '$name' not found.");
	}

	// }}}
	// {{{ public function check()

	public function check()
	{
		if ($this->src_table === null || $this->dst_table === null)
			return;

		$src_count = $this->getSourceRecordCount();
		$dst_count = $this->getDestinationRecordCount();
		if ($dst_count != $src_count)
			printf("Warning: source table (%s) has %s rows and destination table (%s) has %s rows.\n",
				$this->src_table, $src_count, $this->dst_table, $dst_count);
	}

	// }}}
	// {{{ public function disableTriggers()

	public function disableTriggers()
	{
		$sql = sprintf('alter table %s disable trigger all', $this->dst_table);

		SwatDB::query($this->process->dst_db, $sql);
	}

	// }}}
	// {{{ public function enableTriggers()

	public function enableTriggers()
	{
		$sql = sprintf('alter table %s enable trigger all', $this->dst_table);

		SwatDB::query($this->process->dst_db, $sql);
	}

	// }}}

	// source methods
	// {{{ protected function getSourceRow()

	protected function getSourceRow($rs)
	{
		return $rs->fetchRow();
	}

	// }}}
	// {{{ protected function getSourceRecordset()

	protected function getSourceRecordset($start_above = null)
	{
		$sql = $this->getSourceSQL();

		if ($this->custom_where_clause !== null)
			$sql.= ' and '.$this->custom_where_clause;

		if ($this->id_field !== null) {
			if ($start_above === null)
				$sql.= sprintf(' order by %s',
				$this->id_field->src_field->name);
			else
				$sql.= sprintf(' and %s > %s order by %s',
					$this->id_field->src_field->name,
					$this->process->src_db->quote($start_above, $this->id_field->src_field->type),
					$this->id_field->src_field->name);
		}

		$rs = SwatDB::query($this->process->src_db, $sql, null);
		return $rs;
	}

	// }}}
	// {{{ protected function getSourceSQL()

	protected function getSourceSQL()
	{
		$select_list = array();

		foreach ($this->fields as $field) {
			if ($field->src_field === null)
				$select_list[] = 'null';
			else
				$select_list[] = $field->src_field->name;
		}

		$sql = sprintf('select %s from %s where 1=1',
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

		if ($this->id_field->src_field->type !== 'integer')
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

	protected function convertRow(&$row)
	{
		$i = 0;

		foreach ($this->fields as $field) {
			$row[$i] = $field->convertData($row[$i]);
			$i++;
		}
	}

	// }}}
	// {{{ protected function finalize()

	protected function finalize()
	{
		foreach ($this->fields as $field)
			$field->finalize();
	}

	// }}}
	
	// destination methods
	// {{{ protected function insertDestinationRow()

	protected function insertDestinationRow($row)
	{
		$sql = $this->getDestinationSQL();
		$i = 0;

		foreach ($this->fields as $field) {
			$row[$i] = $this->process->dst_db->quote($row[$i], $field->dst_field->type);
			$i++;
		}

		$sql = vsprintf($sql, $row);
		SwatDB::exec($this->process->dst_db, $sql);
	}

	// }}}
	// {{{ protected function getDestinationSQL()

	protected function getDestinationSQL()
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

		if ($this->id_field->dst_field->type !== 'integer')
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

		return SwatDB::exec($this->process->dst_db, $sql);
	}

	// }}}
	// {{{ protected function setDestinationSequence()

	protected function setDestinationSequence()
	{
		if ($this->id_field === null)
			throw new SwatException('No ID field specified.');

		$sql = sprintf('select setval(\'%1$s_%2$s_seq\', max(%2$s), true) from %1$s',
			$this->dst_table,
			$this->id_field->dst_field->name);

		SwatDB::exec($this->process->dst_db, $sql);
	}

	// }}}
}

?>
