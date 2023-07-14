<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseDumpCommand extends Command
{
	protected $dumpFilePath = 'dump.sql';

	protected $signature = 'db:dump';

	protected $description = 'Generate a database dump with selected tables';

	protected $whitelisted = [];

	function __construct() {
		parent::__construct();
		$this->dumpFilePath = config('dumpster.filepath' , $this->dumpFilePath);
	}

	public function handle()
	{
		$from = $this->getFromConfig();
		$this->table(
			['KEYS', 'FROM', 'TO'],
			[
				[ "HOST" , $from['host'], env('TO_DB_HOST')],
				[ "PORT" , $from['port'], env('TO_DB_PORT')],
				[ "DATABASE" , $from['database'], env('TO_DB_DATABASE')],
				[ "USERNAME" , $from['username'], env('TO_DB_USERNAME')],
			]
		);

		if (!$this->confirm('Are these details correct?')) {
			return;
		}

		$whitelistedTables = $this->getWhitelistedTables();

		$nonWhitelistedTables = $this->getNonWhitelistedTables();

		$dumpCommandWithData = $this->getBaseFrombaseCommand($from) . ' --no-data=false ' . $from['database'] . ' ' . implode(' ', $whitelistedTables);

		$dumpCommandWithoutData = 'mysqldump --host=' . config('database.connections.mysql.host') . ' --port=' . config('database.connections.mysql.port') .
			' -u ' . config('database.connections.mysql.username') . ' -p' . config('database.connections.mysql.password') .
			' --no-data=true ' . config('database.connections.mysql.database') . ' ' . implode(' ', $nonWhitelistedTables);

		$dumpFilePath = 'dump.sql';
		exec($dumpCommandWithData . ' > ' . $dumpFilePath);
		exec($dumpCommandWithoutData . ' >> ' . $dumpFilePath);

		if (file_exists($dumpFilePath)) {
			Storage::disk('local')->move($dumpFilePath, 'public/' . $dumpFilePath);
			$this->info('Database dump generated successfully.');

			exec("mysql -u " . env('DB_TEST_USERNAME') . " -p" . env('DB_TEST_PASSWORD') . " " . env('DB_TEST_DATABASE') . " < dump.sql");
		} else {
			$this->error('Failed to generate the dump file.');
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
	 * Getter funtion to get the config
	 *
	 */
	protected function getFromConfig() {

		$databaseConfig = env('DATABASE_URL') ? $this->parseDatabaseUrl(env('DATABASE_URL')) : [];

		if(count($databaseConfig) != 0) return $databaseConfig;

		return [
			'host' => config('database.connections.mysql.host'),
			'port' => config('database.connections.mysql.port') ?? 3306,
			'username' => config('database.connections.mysql.database'),
			'password' => config('database.connections.mysql.password'),
			'database' => config('database.connections.mysql.database')
		];
	}

	function dumpCommandWithData(array $from) {
		$dumpCommandWithData = $this->getBaseFrombaseCommand($from) . ' --no-data=false ' . $from['database'] . ' ' . implode(' ', $this->getWhitelistedTables());
		exec($dumpCommandWithData . ' > ' . $this->dumpFilePath);
	}
	function getWhitelistedTables() {
		return DB::table('information_schema.tables')
			->select('TABLE_NAME')
			->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
			->whereIn('TABLE_NAME', $this->whitelisted)
			->pluck('TABLE_NAME')
			->toArray();

	}
	function getNonWhitelistedTables() {
		return	$nonWhitelistedTables = DB::table('information_schema.tables')
					->select('TABLE_NAME')
					->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
					->whereNotIn('TABLE_NAME', $this->whitelisted)
					->pluck('TABLE_NAME')
					->toArray();
	}
	function getBaseFrombaseCommand(array $from) {
		return 'mysqldump --host=' . $from['host'] . ' --port=' . $from['port'] . ' -u ' . $from['username'] . ' -p' . $from['password'];
	}

}
