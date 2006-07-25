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

		$this->src_db->options['result_buffering'] = false;

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
			echo "Table initialization complete\n";
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

		$table->run_pass1($this);

		array_push($this->stack, $table_name);

		foreach ($table->getDeps() as $dep) {
			$dep_table = $this->lookupTableByDestinationTable($dep);

			if ($dep_table === null)
				printf("Warning: dependent table '$dep' not found, skipping\n");
			else
				$this->convertTable($dep_table);
		}

		array_pop($this->stack);

		$table->run_pass2($this);
		$table->check();

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
}

?>
