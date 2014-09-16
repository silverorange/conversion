<?php

require_once 'PEAR.php';
require_once 'MDB2.php';
require_once 'SwatDB/SwatDB.php';
require_once 'SwatDB/exceptions/SwatDBException.php';
require_once 'Swat/exceptions/SwatException.php';
require_once 'Conversion/ConversionTable.php';

/**
 * Runnable application that converts data from one database to another 
 *
 * The conversion process is composed of multiple {@link ConversionTable}
 * objects.
 *
 * Features supported are:
 * - change database software
 * - change table names
 * - change column names
 * - change column types
 * - change character encoding
 * - change date time-zones
 * - easily extensible for completely custom conversion behaviour
 *
 * @package   Conversion
 * @copyright 2006 silverorange
 */
class ConversionProcess
{
	// {{{ public properties

	public $src_dsn = null;
	public $dst_dsn = null;

	public $src_db = null;
	public $dst_db = null;

	// }}}
	// {{{ private properties

	private $tables = array();
	private $stack = array();
	private $queue = array();

	// }}}
	// {{{ public function addTable()

	public function addTable(ConversionTable $table)
	{
		$this->tables[] = $table;		
	}

	// }}}
	// {{{ public function run()

	public function run()
	{
		foreach ($this->tables as $table) {
			$table->process = $this;
			printf("Initializing table (%s)... ", get_class($table));
			$table->init();
			echo "Table initialization complete\n";
		}

		$this->connectSourceDB();
		$this->connectDestinationDB();

		foreach ($this->tables as $table)
			$this->queueTable($table);

		foreach (array_reverse($this->queue) as $table) {
			$table->disableTriggers();
			$table->runPass1($this);
			$table->enableTriggers();
		}

		foreach ($this->queue as $table) {
			$table->disableTriggers();
			$table->runPass2($this);
			$table->check();
			$table->enableTriggers();
		}

		foreach ($this->queue as $table) {
			$table->disableTriggers();
			$table->runPass3($this);
			$table->check();
			$table->enableTriggers();
		}
	}

	// }}}
	// {{{ public static function readline()

	public static function readline($prompt = '')
	{
		echo $prompt;
		$out = '';
		$key = '';
		$key = fgetc(STDIN);

		while ($key != "\n") {
			$out.= $key;
			$key = fread(STDIN, 1);
		}

		return $out;
	}

	// }}}
	// {{{ protected function connectSourceDB()

	protected function connectSourceDB()
	{
		printf("Connecting to source DB (%s)... ", $this->src_dsn);

		if ($this->src_dsn === null) {
			$this->src_db = null;
			echo "No source DSN\n";
			return;
		}

		$this->src_db = MDB2::connect($this->src_dsn);

		if (PEAR::isError($this->src_db))
			throw new SwatDBException($this->src_db);

		$this->src_db->options['result_buffering'] = false;

		echo "success\n";
	}

	// }}}
	// {{{ protected function connectDestinationDB()

	protected function connectDestinationDB()
	{
		printf("Connecting to destination DB (%s)... ", $this->dst_dsn);

		if ($this->dst_dsn === null) {
			$this->dst_db = null;
			echo "No destination DSN\n";
			return;
		}

		$this->dst_db = MDB2::connect($this->dst_dsn);

		$this->dst_db->options['portability'] =
			$this->dst_db->options['portability'] ^
				MDB2_PORTABILITY_EMPTY_TO_NULL;

		if (PEAR::isError($this->dst_db))
			throw new SwatDBException($this->dst_db);

		echo "success\n";
	}

	// }}}
	// {{{ private function queueTable()

	private function queueTable(ConversionTable $table)
	{
		$table_name = get_class($table);

		if (in_array($table, $this->queue))
			return;

		if (in_array($table_name, $this->stack))
			throw new SwatException("Circular dependency on table '$table_name'.");

		array_push($this->stack, $table_name);

		foreach ($table->getDeps() as $dep) {
			$dep_table = $this->lookupTableByDestinationTable($dep);

			if ($dep_table === null)
				printf("Warning: dependent table '$dep' not found, skipping\n");
			else
				$this->queueTable($dep_table);
		}

		array_pop($this->stack);

		$this->queue[] = $table;
	}

	// }}}
	// {{{ private function lookupTableByDestinationTable()

	private function lookupTableByDestinationTable($name)
	{
		foreach ($this->tables as $table)
			if ($name == $table->dst_table)
				return $table;

		return null;
	}

	// }}}
}

?>
