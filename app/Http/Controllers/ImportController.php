<?php namespace App\Http\Controllers;

use App\Source;
use App\Dataset;
use App\DatasetCategory;
use App\DatasetSubcategory;
use App\DatasetTag;
use App\LinkDatasetsTags;
use App\VariableType;
use App\InputFile;
use App\Variable;
use App\DataValue;
use App\EntityIsoName;
use App\Entity;
use App\Setting;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DB;
use App\Commands\ImportCommand;
use Carbon\Carbon;

class ImportController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{	

		$datasets = Dataset::where('namespace', '=', 'owid')->orderBy('name')->get();
		$categories = DatasetCategory::all();
		$subcategories = DatasetSubcategory::all();
		$varTypes = VariableType::all();
		$sourceTemplate = Setting::where('meta_name', 'sourceTemplate')->first();

		$data = [
			'datasets' => $datasets,
			'categories' => $categories,
			'subcategories' => $subcategories,
			'varTypes' => $varTypes,
			'sourceTemplate' => $sourceTemplate
		];	

		$response = view('import.index')->with('data', $data);
		return $response;
	}

	/**
	 * Import a collection of variables
	 */
	public function variables(Request $request) {
		$input = $request->all();

		return DB::transaction(function() use ($input) {
			$datasetMeta = $input['dataset'];
			// entityKey is a unique list of entity names/codes e.g. ['Germany', 'Afghanistan', 'USA']
			$entityKey = $input['entityKey'];
			// entities is a list of indices for entityKey 
			$entities = $input['entities'];
			$years = $input['years'];
			$variables = $input['variables'];

			$datasetProps = [
				'name' => $datasetMeta['name'],
				'description' => $datasetMeta['description'],
				'fk_dst_cat_id' => $datasetMeta['categoryId'],
				'fk_dst_subcat_id' => $datasetMeta['subcategoryId']
			];

			if (isset($datasetMeta['id'])) {
				$dataset = Dataset::find($datasetMeta['id']);
				$dataset->update($datasetProps);
			} else {
				$dataset = Dataset::create($datasetProps);
			}
			$datasetId = $dataset->id;

			// First, we insert all of the entities with "on duplicate key update", ensuring
			// they all exist in the database.
			$insertQuery = "INSERT INTO entities (name) VALUES";

			$pdo = DB::connection()->getPdo();
			foreach ($entityKey as $name) {
				if ($name != $entityKey[0])
					$insertQuery .= ",";
				$insertQuery .= " (" . $pdo->quote($name) . ")";
			}

			$insertQuery .= " ON DUPLICATE KEY UPDATE name=VALUES(name);";

			DB::statement($insertQuery);

			// Now we need to pull them back out again so we know what ids to go with what names
			$entityNameToId = DB::table('entities')
				->select('id', 'name')
				->whereIn('name', $entityKey)
				->lists('id', 'name');

			$sourceIdsByName = [];

			// Now we feed in our set of variables and associated data
			foreach ($variables as $variable) {
				$sourceName = $variable['source']['name'];
				if (isset($sourceIdsByName[$sourceName])) {
					$sourceId = $sourceIdsByName[$sourceName];
				} else {
					// Create or update the associated source data
					$sourceId = isset($variable['source']['id']) ? $variable['source']['id'] : null;
					$sourceDesc = $variable['source']['description'];

					if ($sourceId) {
						Source::find($sourceId)->update($variable['source']);
					} else {
						$sourceId = Source::create(
							['datasetId' => $datasetId, 'name' => $sourceName, 'description' => $sourceDesc]
						)->id;
					}

					$sourceIdsByName[$sourceName] = $sourceId;					
				}

				$values = $variable['values'];
				$variableProps = [
					'name' => $variable['name'],
					'description' => $variable['description'],
					'unit' => $variable['unit'],
					'fk_var_type_id' => 3,
					'fk_dst_id' => $datasetId,
					'sourceId' => $sourceId,
					'uploaded_by' => \Auth::user()->name, 
					'uploaded_at' => Carbon::now(),
					'updated_at' => Carbon::now()
				];

				if (isset($variable['overwriteId'])) {
					Variable::find($variable['overwriteId'])->update($variableProps);
					$varId = $variable['overwriteId'];
				} else {
					$varId = Variable::create($variableProps)->id;
				}

				// Delete any existing data values
				DB::table('data_values')->where('fk_var_id', '=', $varId)->delete();

				$newDataValues = [];
				for ($i = 0; $i < sizeof($years); $i++) {
					if ($values[$i] === '') // Empty cells, as opposed to zeroes, most likely should not be inserted at all
						continue;

					$newDataValues[] = [
						'fk_var_id' => $varId,
						'fk_ent_id' => $entityNameToId[$entityKey[$entities[$i]]],
						'year' => $years[$i],
						'value' => $values[$i],
					];

					// For very big datasets, there may be too many to do in a single insert
					// Limit is about 25565 placeholders. So we stop and push in a bunch every so often
					if (sizeof($newDataValues) > 10000) {
						DB::table('data_values')->insert($newDataValues);
						$newDataValues = [];
					}
				}

				if (sizeof($newDataValues) > 0)
					DB::table('data_values')->insert($newDataValues);

				// Delete any sources which are no longer in use by variables
				DB::statement("DELETE FROM sources WHERE sources.id NOT IN (SELECT variables.sourceId FROM variables)");
			}

			return [ 'datasetId' => $datasetId ];
		});
	}

	public function hasValue($value) {
		return ( !empty( $value ) || $value === "0" || $value === 0 )? true: false;
	}
}
