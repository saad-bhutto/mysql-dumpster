<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateDatabaseDumpCommand extends Command
{
	protected $signature = 'db:dump';

	protected $description = 'Generate a database dump with selected tables';

	protected $whitelisted = [];

	public function handle()
	{

		$this->table(
			['KEYS', 'FROM', 'TO'],
			[
				[ "HOST" , env('DB_HOST') ,  env('TEST_DB_HOST')],
				[ "PORT" , env('DB_PORT') ,  env('TEST_DB_PORT')],
				[ "DATABASE" , env('DB_DATABASE') ,  env('TEST_DB_DATABASE')],
				[ "USERNAME" , env('DB_USERNAME') ,  env('TEST_DB_USERNAME')],
			]
		);

		$this->alert(' ================== Confirm  ================== ');

		if (!$this->confirm('Are these details correct?')) {
			return;
		}

		$whitelistedTables = DB::table('information_schema.tables')
			->select('TABLE_NAME')
			->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
			->whereIn('TABLE_NAME', $this->whitelisted)
			->pluck('TABLE_NAME')
			->toArray();

		$nonWhitelistedTables = DB::table('information_schema.tables')
			->select('TABLE_NAME')
			->where('TABLE_SCHEMA', config('database.connections.mysql.database'))
			->whereNotIn('TABLE_NAME', $whitelistedTables)
			->pluck('TABLE_NAME')
			->toArray();

		$dumpCommandWithData = 'mysqldump --host=' . config('database.connections.mysql.host') . ' --port=' . config('database.connections.mysql.port') .
			' -u ' . config('database.connections.mysql.username') . ' -p' . config('database.connections.mysql.password') .
			' --no-data=false ' . config('database.connections.mysql.database') . ' ' . implode(' ', $whitelistedTables);

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
}
