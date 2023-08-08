<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseDumpCommand extends Command
{
	protected $dumpFilePath = 'dump.sql';

	protected $signature = 'db:dump {transfer=false}';

	protected $description = 'Generate a database dump with selected tables';

	protected $dumpOnly = true;

	protected $whitelisted = [];

	public function __construct()
	{
		parent::__construct();
		$this->dumpFilePath = config('dumpster.file_name', $this->dumpFilePath);
		$this->whitelisted = config('dumpster.whitelisted_tables', []);
	}

	public function handle()
	{
		$this->dumpOnly = $this->argument('transfer') !== 'false';

		$from = $this->getFromConfig();
		$this->printConfigTable($from);

		!$this->dumpOnly
			&& $this->confirm('would you like to transfer dump onto another source?')
			&& $this->printConfigTable($from);


		if (!$this->confirm('Are these details correct?')) {
			return;
		}

		$whitelistedTables = $this->getWhitelistedTables();
		$nonWhitelistedTables = $this->getNonWhitelistedTables();

		$dumpCommandWithData = $this->getBaseFromCommand($from) . ' --no-data=false ' . $from['database'] . ' ' . implode(' ', $whitelistedTables);
		$dumpCommandWithoutData = $this->getBaseFromCommand($from) . ' --no-data=true ' . $from['database'] . ' ' . implode(' ', $nonWhitelistedTables);

		count($whitelistedTables) > 0 && exec($dumpCommandWithData . ' > ' . $this->dumpFilePath);
		count($nonWhitelistedTables) > 0 && exec($dumpCommandWithoutData . (count($whitelistedTables) > 0 ? ' >> ' : ' > ') . $this->dumpFilePath);

		if (file_exists($this->dumpFilePath)) {
			Storage::disk('local')->move($this->dumpFilePath, 'public/' . $this->dumpFilePath);
			$this->info('Database dump generated successfully.');

			// $this->dumpOnly && exec("mysql -u " . env('DB_TEST_USERNAME') . " -p" . env('DB_TEST_PASSWORD') . " " . env('DB_TEST_DATABASE') . " < dump.sql");
		} else {
			$this->error('Failed to generate the dump file. ' . $this->dumpFilePath);
		}
	}

	protected function parseDatabaseUrl($databaseUrl)
	{
		$parsedUrl = parse_url($databaseUrl);

		$databaseConfig = [
			'host' => $parsedUrl['host'],
			'port' => $parsedUrl['port'] ?? 3306,
			'username' => $parsedUrl['user'],
			'password' => $parsedUrl['pass'],
			'database' => ltrim($parsedUrl['path'], '/')
		];

		return $databaseConfig;
	}

	/**
	 * Getter funtion to get the config *
	 */
	protected function getFromConfig()
	{

		$databaseConfig = env('DATABASE_URL') ? $this->parseDatabaseUrl(env('DATABASE_URL')) : [];

		if (count($databaseConfig) != 0) {
			return $databaseConfig;
		}

		return [
			'host' => config('database.connections.mysql.host'),
			'port' => config('database.connections.mysql.port') ?? 3306,
			'username' => config('database.connections.mysql.database'),
			'password' => config('database.connections.mysql.password'),
			'database' => config('database.connections.mysql.database')
		];
	}

	public function getWhitelistedTables()
	{
		return DB::table('information_schema.tables')
			->select('TABLE_NAME')
			->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
			->whereIn('TABLE_NAME', $this->whitelisted)
			->pluck('TABLE_NAME')
			->toArray();
	}

	public function getNonWhitelistedTables()
	{
		return DB::table('information_schema.tables')
			->select('TABLE_NAME')
			->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
			->whereNotIn('TABLE_NAME', $this->whitelisted)
			->pluck('TABLE_NAME')
			->toArray();
	}

	public function getBaseFromCommand(array $from)
	{
		return 'mysqldump --host=' . $from['host'] . ' --port=' . $from['port'] . ' -u ' . $from['username'] . ' -p' . $from['password'];
	}

	public function printConfigTable($from)
	{
		$this->table(
			['', 'KEYS', 'FROM', $this->dumpOnly ? 'TO' : ''],
			[
				['> ', "HOST", $from['host'], $this->dumpOnly ? env('TO_DB_HOST') : ''],
				['> ', "PORT", $from['port'], $this->dumpOnly ? env('TO_DB_PORT') : ''],
				['> ', "DATABASE", $from['database'], $this->dumpOnly ? env('TO_DB_DATABASE') : ''],
				['> ', "USERNAME", $from['username'], $this->dumpOnly ? env('TO_DB_USERNAME') : ''],
			]
		);
	}


}