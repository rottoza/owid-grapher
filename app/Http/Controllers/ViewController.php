<?php namespace App\Http\Controllers;

use App;
use App\Chart;
use App\Setting;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Debugbar;
use DB;
use URL;

class ViewController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		return 'No chart selected to view';
	}

	public function testall()
	{
		$ids = DB::table('charts')->select('id')->where('origin_url', '!=', "")->lists('id');
		$charts = [];

		foreach ($ids as $id) {
			$charts[] = [
				'localUrl' => \Request::root() . "/view/" . $id,
				'liveUrl' => "http://ourworldindata.org/grapher/view/" . $id
			];
		}

		return view('testall')->with([ 'charts' => $charts ]);
	}

	public function show($slug)
	{
		$chart = Chart::where('slug', $slug)->orWhere('id', $slug)->first();		
		if (!$chart)
			return App::abort(404, "No such chart");

		return $this->showChart($chart);
	}

	public function exportPNG($slug, Request $request) {
		$chart = Chart::where('slug', $slug)
					  ->orWhere('id', $slug)
					  ->first();

		if (!$chart)
			return App::abort(404, "No such chart");

		$phantomjs = base_path() . "/node_modules/.bin/phantomjs";
		$rasterize = base_path() . "/phantomjs/rasterize.js";
		$target = $request->root() . "/" . $slug . ".export" . "?" . $request->getQueryString();
		$file = public_path() . "/exports/" . $slug . ".png";
		$command = $phantomjs . " " . $rasterize . " " . escapeshellarg($target) . " " . escapeshellarg($file);
		exec($command);

		$file = public_path() . "/exports/life-expectancy.png";
		return response()->file($file);
	}

	public function exportCSV($slug, Request $request) {
		$chart = Chart::where('slug', $slug)
					  ->orWhere('id', $slug)
					  ->first();

		if (!$chart)
			return App::abort(404, "No such chart");

		$config = json_decode($chart->config);

		// Allow overriding selected-countries with url param
		$countryStr = $request->input('country');
		if (!empty($countryStr)) {
			$countryCodes = explode(" ", $countryStr);
			$query = DB::table('entities')
				->select('id', 'name')
				->whereIn('code', $countryCodes);
			$config->{"selected-countries"} = $query->get();
		}

		$dims = json_decode($config->{"chart-dimensions"});
		$varIds = array_map(function($dim) { return $dim->variableId; }, $dims);

		// Grab the variable names for the header row
		$variableNameById = DB::table('variables')
			->whereIn('id', $varIds)
			->select('id', 'name')
			->lists('name', 'id');

		$entityNames = array_map(function($entity) { return $entity->name; }, $config->{"selected-countries"});
		$entityIds = DB::table('entities')
			->whereIn('name', $entityNames)
			->lists('id');

		$rows = [];
		$headerRow = ['Country', 'Year'];
		foreach ($varIds as $id) {
			$headerRow[]= $variableNameById[$id];
		}
		$rows[]= $headerRow;

		$currentRow = null;

		// Now we pull out all the actual data
		$dataQuery = DB::table('data_values')
			->whereIn('data_values.fk_var_id', $varIds);
		
		if ($entityIds)
			$dataQuery = $dataQuery->whereIn('data_values.fk_ent_id', $entityIds);

		$dataQuery = $dataQuery
			->select('value', 'year',
					 'data_values.fk_var_id as var_id', 
					 'entities.id as entity_id', 'entities.name as entity_name',
					 'entities.code as entity_code')
			->join('entities', 'data_values.fk_ent_id', '=', 'entities.id')
			->orderBy('entities.name', 'DESC')
			->orderBy('year', 'ASC')
			->orderBy('fk_var_id', 'ASC');

		foreach ($dataQuery->get() as $result) {
			if (!$currentRow || $currentRow[0] != $result->entity_name || $currentRow[1] != $result->year) {
				if ($currentRow)
					$rows[]= $currentRow;

				// New row
				$currentRow = [$result->entity_name, $result->year];
				for ($i = 0; $i < sizeof($varIds); $i++) {
					$currentRow[]= "";
				}
			}

			$index = 2+array_search($result->var_id, $varIds);
			$currentRow[$index] = $result->value;
		}

		// Use memory file pointer so we can have fputcsv escape for us
		$fp = fopen('php://memory', 'w+');
		foreach ($rows as $row) {
			fputcsv($fp, $row);
		}
		rewind($fp);
		$csv = stream_get_contents($fp);
		fclose($fp); 
		return response($csv, 200)
				->header('Content-Type', 'text/csv')
				->header('Content-Disposition', 'attachment; filename="' . $chart->slug . '.csv' . '"');		
	}


	public function showChart(Chart $chart) 
	{
		$referer_s = \Request::header('referer'); 
		if ($referer_s) {
			$root = parse_url(\Request::root());
			$referer = parse_url($referer_s);
			if ($root['host'] == $referer['host'] && !str_contains($referer_s, ".html") && !str_contains($referer_s, "wp-admin") && !str_contains($referer_s, "preview=true") && !str_contains($referer_s, "OWID-grapher") && !str_contains($referer_s, "how-to")) {
				$chart->origin_url = "https://" . $root['host'] . $referer['path'];
				$chart->save();
			}
		}

		if( $chart ) {
			$data = new \StdClass;
			$logoUrl = Setting::where( 'meta_name', 'logoUrl' )->first();
			$data->logoUrl = ( !empty( $logoUrl ) )? url('/') .'/'. $logoUrl->meta_value: '';
			$canonicalUrl = URL::to($chart->slug);			
			return view( 'view.show', compact( 'chart', 'data', 'canonicalUrl' ));
		} else {
			return 'No chart found to view';
		}
	}
}
