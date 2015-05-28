<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		Model::unguard();

		$this->call('UsersTableSeeder');
		$this->call('InputFilesTableSeeder');

		$this->call('EntityTypesTableSeeder');
		//$this->call('EntitiesTableSeeder');
	
		$this->call('VariableTypesTableSeeder');
		//$this->call('TimesTableSeeder');
		
		$this->call('DatasetCategoriesTableSeeder');
		$this->call('DatasetSubcategoriesTableSeeder');
		
		$this->call('ChartTypesTableSeeder');
		$this->call('ChartTypeDimensionsTableSeeder');

		//$this->call('DatasetsTableSeeder');
		//$this->call('VariablesTableSeeder');
		//$this->call('DataValuesTableSeeder');

	}

}