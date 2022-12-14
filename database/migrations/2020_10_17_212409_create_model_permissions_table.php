<?php

use App\Exceptions\ConfigPropertyMissingException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModelPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (empty($tableNames)) {
            throw new ConfigPropertyMissingException(
                'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.'
            );
        }

        Schema::create(
            $tableNames['model_has_permissions'],
            function (Blueprint $table) use ($tableNames, $columnNames) {
                $table->unsignedBigInteger('permission_id');

                $table->string('model_type');
                $table->uuid($columnNames['model_morph_key']);
                $table->index(
                    [$columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_model_id_model_type_index'
                );

                $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');

                $table->primary(
                    ['permission_id', $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new ConfigPropertyMissingException(
                'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.'
            );
        }

        Schema::drop($tableNames['model_has_permissions']);
    }
}
