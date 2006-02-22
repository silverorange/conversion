<?php

require_once 'PEAR.php';
require_once 'MDB2.php';
require_once 'SwatDB/SwatDB.php';
require_once 'SwatDB/exceptions/SwatDBException.php';
require_once 'Swat/exceptions/SwatException.php';
require_once 'Conversion/ConversionTable.php';

class ConversionProcess
{
	public $src_dsn = null;
	public $dst_dsn = null;

	public $src_db = null;
	public $dst_db = null;

	private $tables = array();
	private $processed_table_names = array();
	private $stack = array();

	// {{{ public function addTable()

	public function addTable(ConversionTable $table)
	{
		$this->tables[] = $table;		
	}

	// }}}
	// {{{ protected function connectSourceDB()

	protected function connectSourceDB()
	{
		printf("Connecting to source DB (%s)... ", $this->src_dsn);

		if ($this->src_dsn === null)
			throw new SwatException('No source DSN specified.');

		$this->src_db = MDB2::connect($this->src_dsn);

		if (PEAR::isError($this->src_db))
			throw new SwatDBException($this->src_db);

		echo "success\n";
	}

	// }}}
	// {{{ protected function connectDestinationDB()

	protected function connectDestinationDB()
	{
		printf("Connecting to destination DB (%s)... ", $this->dst_dsn);

		if ($this->dst_dsn === null)
			throw new SwatException('No destination DSN specified.');

		$this->dst_db = MDB2::connect($this->dst_dsn);

		if (PEAR::isError($this->dst_db))
			throw new SwatDBException($this->dst_db);

		echo "success\n";
	}

	// }}}
	// {{{ public function run()

	public function run()
	{
		foreach ($this->tables as $table) {
			$table->process = $this;
			printf("Initializing table (%s)... ", get_class($table));
			$table->init();
			echo "success\n";
		}
		
		$this->connectSourceDB();
		$this->connectDestinationDB();

		foreach ($this->tables as $table)
			$this->convertTable($table);
	}

	// }}}
	// {{{ private function convertTable()

	private function convertTable(ConversionTable $table)
	{
		$table_name = get_class($table);

		if (in_array($table_name, $this->processed_table_names))
			return;

		if (in_array($table_name, $this->stack))
			throw new SwatException("Circular dependency on table '$table_name'.");

		array_push($this->stack, $table_name);

		foreach ($table->getDeps() as $dep) {
			$dep_table = $this->lookupTableByDestinationTable($dep);

			if ($dep_table === null)
				printf("Warning: dependent table '$dep' not found, skipping\n");
			else
				$this->convertTable($dep_table);
		}

		array_pop($this->stack);

		printf("Converting table (%s)... ", $table_name);
		$row_count = $table->run($this);
		echo "$row_count rows inserted\n";

		$this->processed_table_names[] = $table_name;
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
