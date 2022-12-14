<?php

namespace App\Installer;

use \Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Installer
{
    private $isFresh = false;

    const CACHE_INSTALLED_KEY = 'shoutzor.installed';

    /**
     * Contains the installer steps in the correct order of execution
     * The slug must consist of (lowercase) a-z or dashes only!
     * @var array[]
     */
    public static array $installSteps = [
        [
            'name' => 'Database migrations',
            'description' => 'Creates tables and indexes in the database',
            'method' => 'migrateDatabase'
        ],
        [
            'name' => 'Database seeding',
            'description' => 'Adds initial data to the database',
            'method' => 'seedDatabase'
        ],
        [
            'name' => 'Optimize install',
            'description' => 'Optimizes the app cache',
            'method' => 'optimizeInstall'
        ],
        [
            'name' => 'Finishing up',
            'description' => 'Finalize the installation',
            'method' => 'finishInstall'
        ]
    ];

    /**
     * Checks the cache if Shoutz0r has been installed
     * If not, it will check the DB and once installed set the Cache key accordingly.
     * This method uses the octane cache specifically because it will help to shave off
     * just a little bit extra time over redis, and it's perfectly fine to cache on every
     * backend-instance separately.
     * @return bool
     */
    public static function isInstalled() : bool {
        try {
            if(Cache::store('octane')->get(Installer::CACHE_INSTALLED_KEY, false)) {
                return true;
            }

            // The cached value was not `true`. Therefor we need to keep re-checking.
            // Due to local caching, we cannot cache a `false` value as this could
            // cause problems with scaled instances of the backend; Where one container
            // could run the installation, and other instances wouldn't be aware.
            if(Installer::checkIfInstalled()) {
                Cache::store('octane')->forever(Installer::CACHE_INSTALLED_KEY, true);
                return true;
            }
        }
        catch(Exception $e) {
            // Log the error
            Log::critical("Failed to check if Shoutz0r is installed", [
                'code'      => $e->getCode(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'exception' => $e->getMessage(),
                'trace'     => json_encode($e->getTrace())
            ]);
        }

        return false;
    }

    /**
     * Checks if the version of shoutzor exists in the database 
     * to determine if shoutzor is installed
     */
    public static function checkIfInstalled() : bool {
        return DB::table('settings')->where('key', 'version')->exists();
    }

    public function __construct($isDev = false, $isFresh = false) {
        $this->isDev = $isDev;
        $this->isFresh = $isFresh;
    }

    /**
     * Tests & Configures the SQL settings to use
     * @param string $dbtype
     * @param string $host
     * @param string $port
     * @param string $database
     * @param string $username
     * @param string $password
     * @return InstallStepResult
     */
    public function testSqlConnection(): InstallStepResult
    {
        $success = true;
        $exception = null;

        // Test database connection
        try {
            // Test the PDO connection
            DB::connection()->getPdo();

        } catch (\PDOException $e) {
            return new InstallStepResult(false, $e->getMessage(), $e);
        } catch (Exception $e) {
            $exception = $e;
            $success = false;
        }

        return new InstallStepResult($success, $success ? Artisan::output() : $exception?->getMessage() ?? '', $exception);
    }

    /**
     * Executes the artisan migrate command
     * @return InstallStepResult
     */
    public function migrateDatabase(): InstallStepResult
    {
        $success = true;
        $exception = null;

        try {
            // If isFresh equals true we want to perform a fresh migration
            if($this->isFresh) {
                Artisan::call('migrate:fresh --force');
            }
            # Else execute the database migrations regularly
            else {
                Artisan::call('migrate --force');
            }
        } catch (Exception $e) {
            $success = false;
            $exception = $e;
        }

        return new InstallStepResult($success, $success ? Artisan::output() : $exception?->getMessage() ?? '', $exception);
    }

    /**
     * Executes the artisan db:seed command
     * @return InstallStepResult
     */
    public function seedDatabase(): InstallStepResult
    {
        $success = true;
        $exception = null;

        try {
            # Seed the database
            Artisan::call('db:seed --force');
        } catch (Exception $e) {
            $success = false;
            $exception = $e;
        }

        return new InstallStepResult($success, $success ? Artisan::output() : $exception?->getMessage() ?? '', $exception);
    }

    /**
     * Generates laravel cache for things like Routes & Views to speed up requests
     * @return InstallStepResult
     */
    public function optimizeInstall(): InstallStepResult
    {
        $success = true;
        $exception = null;

        try {
            # Clear all caches first
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');
            Artisan::call('permission:cache-reset');
            Artisan::call('lighthouse:clear-cache');
            Artisan::call('cache:clear');

            # Then re-create some caches
            Artisan::call('optimize');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (Exception $e) {
            $success = false;
            $exception = $e;
        }

        return new InstallStepResult($success, $success ? Artisan::output() : $exception?->getMessage() ?? '', $exception);
    }

    /**
     * Finishes up the Shoutz0r installation by setting shoutzor.installed to true, and rebuilding the config cache
     * @return InstallStepResult
     */
    public function finishInstall(): InstallStepResult
    {
        $success = true;
        $exception = null;

        try {
            # Set installed to true in the cache
            Cache::put(Installer::CACHE_INSTALLED_KEY, true);
        } catch (Exception $e) {
            $success = false;
            $exception = $e;
        }

        return new InstallStepResult($success, $success ? Artisan::output() : $exception?->getMessage() ?? '', $exception);
    }

    /**
     * rebuilds the config cache
     * @return InstallStepResult
     */
    public function rebuildConfigCache(): InstallStepResult
    {
        $success = true;
        $exception = null;

        try {
            # Execute the database migrations
            Artisan::call('config:cache');
        } catch (Exception $e) {
            $success = false;
            $exception = $e;
        }

        return new InstallStepResult($success, $success ? Artisan::output() : $exception?->getMessage() ?? '', $exception);
    }

    /**
     * Runs the seeder that aids during development
     * @return InstallStepResult
     */
    public function developmentSeedDatabase(): InstallStepResult
    {
        $success = true;
        $exception = null;

        try {
            # Seed the database
            Artisan::call('db:seed --class=DevelopmentSeeder --force');
        } catch (Exception $e) {
            $success = false;
            $exception = $e;
        }

        return new InstallStepResult($success, $success ? Artisan::output() : $exception?->getMessage() ?? '', $exception);
    }
}
