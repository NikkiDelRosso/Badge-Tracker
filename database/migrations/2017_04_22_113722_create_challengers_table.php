<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChallengersTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('challengers', function (Blueprint $table) {
			$table->increments('id');
			$table->string('name');
			$table->integer('joined_season_id')->unsigned()->nullable();
			$table->date('join_date');
			$table->integer('current_season_badges')->default(0);
			$table->timestamps();

			$table->foreign('joined_season_id')->references('id')->on('seasons');
		});

		Schema::create('challenger_data', function (Blueprint $table) {
			$table->increments('id');
			$table->integer('challenger_id')->unsigned();
			$table->string('name');
			$table->text('data');
			$table->timestamps();

			$table->foreign('challenger_id')->references('id')->on('challengers');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('challenger_data');
		Schema::dropIfExists('challengers');
	}
}
