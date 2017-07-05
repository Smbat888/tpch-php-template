<?php

/*

   Parses data collected during the benchmark and produces an SQL script with
   the data and a bunch of images (latency and tps of the pgbench part).

   Expected cmdline parameters are

      $ php collect.php INPUT_DIRECTORY OUTPUT_IMAGES_DIRECTORY

   so if the benchmark data are in 'bench-results' directory, and you want to
   get images into the 'bench-images' directory, do this

      $ php collect.php ./bench-results ./bench-images

   The processing may take a lot of time (and CPU/memory), because it has to
   parse all the pgbench results and build all the images.

   This requires a working gnuplot installation (to build the images).

*/
	include("template.class.php");

	date_default_timezone_set('Europe/Prague');

	if (count($argv) < 4) {
		echo "ERROR: not enough parameters\n";
		exit();
	}

	$input  = $argv[1];
	$scale_factor = $argv[2];
	$platform_name = $argv[3];
	$config_json = file_get_contents($argv[4]);
	$refresh_avg = refresh_avg_results($argv[5]);

	$loadTime = array();

	if (! file_exists($input)) {
		echo "ERROR: input directory '$input' does not exist\n";
		exit();
	} else if (! is_dir($input)) {
		echo "ERROR: is not a directory: '$input'\n";
		exit();
	} else if (! $scale_factor) {
		echo "ERROR: scale factor isn't provided\n";
		exit();
	} else if (! $platform_name) {
		echo "ERROR: platform name isn't provided\n";
		exit();
	} else if (is_file($config_json)) { //TODO check is file
		echo "ERROR: config json '$config_json' does not exist\n";
		exit();
	} else if (! is_dir($argv[5])) {
		echo "ERROR: is not a directory: '. $argv[5] .'\n";
		exit();
	}

	/* query timeout limit */
	define('QUERY_TIMEOUT', 300);

	echo "input directory: $input\n";
	echo "scale factor is: $scale_factor\n";

	// load the results from directory
	$data = load_tpch($input);

	// add information from the shell log
	parse_log($data, "$input/bench.log");
	echo "-----------------";
	echo $data['stats']['load'];

	// write the results into CSV
	print_tpch_csv($data, 'output.csv', $platform_name, $config_json, $scale_factor, $refresh_avg);

	/* FUNCTIONS */

	/* loads postgresql stats from the directory (expects stats-before/stats-after log files) */
	/* param $dir - directory with benchmark results */
	function load_stats($dir) {

		$diff = array();

		$before = file("$dir/stats-before.log");
		$after = file("$dir/stats-after.log");

		// pg_stat_bgwriter

		$matches_before = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)/', $before[2], $matches_before);

		$matches_after = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)/', $after[2], $matches_after);

		$diff['checkpoints_timed'] = $matches_after[1] - $matches_before[1];
		$diff['checkpoints_req'] = $matches_after[2] - $matches_before[2];
		$diff['buffers_checkpoint'] = $matches_after[3] - $matches_before[3];
		$diff['buffers_clean'] = $matches_after[4] - $matches_before[4];
		$diff['maxwritten_clean'] = $matches_after[5] - $matches_before[5];
		$diff['buffers_backend'] = $matches_after[6] - $matches_before[6];
		$diff['buffers_alloc'] = $matches_after[7] - $matches_before[7];

		// pg_stat_database

		$matches_before = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([a-zA-Z_\-]+)\s+\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)/', $before[7], $matches_before);

		$matches_after = array();
		preg_match('/^\s+([0-9]+)\s\|\s+([a-zA-Z_\-]+)\s+\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)\s\|\s+([0-9]+)/', $after[7], $matches_after);

		$diff['xact_commit'] = $matches_after[4] - $matches_before[4];
		$diff['xact_rollback'] = $matches_after[5] - $matches_before[5];
		$diff['blks_read'] = $matches_after[6] - $matches_before[6];
		$diff['blks_hit'] = $matches_after[7] - $matches_before[7];
		$diff['tup_returned'] = $matches_after[8] - $matches_before[8];
		$diff['tup_fetched'] = $matches_after[9] - $matches_before[9];
		$diff['tup_inserted'] = $matches_after[10] - $matches_before[10];
		$diff['tup_updated'] = $matches_after[11] - $matches_before[11];
		$diff['tup_deleted'] = $matches_after[12] - $matches_before[12];

		$diff['hit_ratio'] = round(floatval(100*$diff['blks_hit']) / ($diff['blks_hit'] + $diff['blks_read']),1);

		return $diff;

	}

	/* loads query stats from the directory (expects results.log file) */
	/* param $dir - directory with benchmark results */
	function load_queries($dir) {

		$queries = array();
		$results = file("$dir/results.log");

		foreach ($results AS $line) {

			if (substr_count($line, '=') > 0) {

				$tmp = explode('=', $line);
				$qn = intval($tmp[0]); /* query id */

				$queries[$qn]['duration'] = floatval($tmp[1]);
				$queries[$qn]['hash'] = get_plan_hash("$dir/explain/$qn");

			}
		}

		return $queries;

	}

	/* loads tpc-h results from the directory */
	/* param $dir - directory with benchmark results */
	function load_tpch($dir) {
		$out = array();
		$out['stats'] = load_stats($dir);
		$out['queries'] = load_queries($dir);
		return $out;
	}

	function score_eval($current, $min, $max = QUERY_TIMEOUT) {

		// cancelled queries should get 0
		if ($current >= $max) {
			return 0;
		}

		// otherwise use the inverse (always "current > min")
		return $min/$current;

	}

	function time_eval($current, $max = QUERY_TIMEOUT) {

		return min(floatval($current), $max);

	}

	/* loads bench.log and appends it to the results */
	/* param $data - benchmark data */
	/* param $logfile - logfile to read data from */
	function parse_log(&$data, $logfile) {
		$log = file($logfile);
		$t = 0;
		$ddir = '';
		$idir = '';
		$dt = new DateTime();
		//just for the fun: what would it be in UTC?
		$dt->setTimezone(new DateTimeZone("GMT+4"));
		$start_timestamp = 0;
		for ($i = 0; $i < count($log); $i++) {
			$matches = array();
			if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : preparing TPC-H database/', $log[$i], $matches)) {
				$t = intval($matches[1]);
				$dt->setTimestamp($t);
				$begin_date = $dt->format('Y-m-d H:i:s');
				$start_timestamp = $matches[1];
				$data['start_date'] = $begin_date;
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   loading data/', $log[$i], $matches)) {
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   creating primary keys/', $log[$i], $matches)) {
				$data['stats']['load'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   creating foreign keys/', $log[$i], $matches)) {
				$data['stats']['pkeys'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   creating indexes/', $log[$i], $matches)) {
				$data['stats']['fkeys'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] :   analyzing/', $log[$i], $matches)) {
				$data['stats']['indexes'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
				$dt->setTimestamp($t);
				$end_date = $dt->format('Y-m-d H:i:s');
				$data['end_date'] = $end_date;
				$data['load_time'] = $matches[1] - $start_timestamp;
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : running TPC-H benchmark/', $log[$i], $matches)) {
				$data['stats']['analyze'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			} else if (preg_match('/[0-9][0-9]:[0-9][0-9]:[0-9][0-9] \[([0-9]+)\] : finished TPC-H benchmark/', $log[$i], $matches)) {
				$data['stats']['benchmark'] = intval($matches[1]) - $t;
				$t = intval($matches[1]);
			}

		}

	}

	/* writes benchmark results to the CSV file */
	/* param $data - benchmark data */
	/* param $logfile - logfile to read data from */
	function print_tpch_csv($data, $outfile, $platform_name, $config_json, $scale_factor, $refresh_avg) {
		$tpchArray = array('load','pkeys','fkeys','indexes','analyze','benchmark');

		$queryArray = array('Q1',
					'Q2','Q3','Q4','Q5','Q6','Q7','Q8','Q9','Q10',
					'Q11','Q12','Q13','Q14','Q15','Q16','Q17','Q18','Q19',
					'Q20','Q21','Q22');

		$qureyHashArray = array('Q1 Hash','Q2 Hash','Q3 Hash','Q4 Hash','Q5 Hash',
					'Q6 Hash','Q7 Hash','Q8 Hash','Q9 Hash','Q10 Hash','Q11 Hash','Q12 Hash',
					'Q13 Hash','Q14 Hash','Q15 Hash','Q16 Hash','Q17 Hash','Q18 Hash',
					'Q19 Hash','Q20 Hash','Q21 Hash','Q22 Hash');

		$json = json_decode($config_json, true);

		$measurmentResults = array(
			array("key" => "Start of Database Load", "value" =>  $data['start_date'])
			, array("key" => "End of Database Load", "value" => $data['end_date'] )
			, array("key" => "Database Load Time", "value" => $data['load_time'])
			, array("key" => "TPC-H Power", "value" => get_power($data, $scale_factor, $refresh_avg))
			, array("key" => "TPC-H Throughput", "value" => get_throghput($data, $scale_factor))
			, array("key" => "TPC-H Composite Query-Per-Hour Performance Metric", "value" => get_query_per_hour($data, $scale_factor, $refresh_avg))
		);

		$generatingSteps = array();

		for ($i = 0; $i <= 5; $i++) {
			$singleItem = array();
			$singleItem['key']= $tpchArray[$i];
			$t = $data['stats'][$tpchArray[$i]];
			$singleItem['value']= $t;
			array_push($query, $singleItem);
		}

		echo "++++++++++++++++++++++++++++++";
		echo $generatingSteps;

		$query = array();

		for ($i = 0; $i <= 21; $i++) {
			$singleItem = array();
			$singleItem['key'] = $queryArray[$i];
			$t = ($data['queries'][$i+1]['duration'] < QUERY_TIMEOUT) ? $data['queries'][$i+1]['duration'] : null;
			$singleItem['value']= $t;
			array_push($query, $singleItem);
		}

		echo "++++++++++++++++++++++++++++++";
		echo $query;

		$refreshResults = array(
			array("key" => "R1", "value" =>  $refresh_avg['Q1'])
			, array("key" => "R2", "value" => $refresh_avg['Q2'])
		);

		foreach ($measurmentResults as $result) {
			$row = new Template("table_row.tpl");

			foreach ($result as $key => $value) {
				$row->set($key, $value);
			}
			$measurmentResultsTemplate[] = $row;
		}

		foreach ($generatingSteps as $step) {
			$row = new Template("table_row.tpl");

			foreach ($step as $key => $value) {
				$row->set($key, $value);
			}
			$generatingStepsTemplate[] = $row;
		}

		foreach ($query as $singleQuery) {
			$row = new Template("table_row.tpl");

			foreach ($singleQuery as $key => $value) {
				$row->set($key, $value);
			}
			$queryTemplate[] = $row;
		}

		foreach ($refreshResults as $result) {
			$row = new Template("table_row.tpl");

			foreach ($result as $key => $value) {
				$row->set($key, $value);
			}
			$refreshResultsTemplate[] = $row;
		}

		// TODO  check
		$fullMeasurmentResults = Template::merge($measurmentResultsTemplate);
		$fullGeneratingSteps = Template::merge($generatingStepsTemplate);
		$fullQuery = Template::merge($queryTemplate);
		$fullRefreshResults = Template::merge($refreshResultsTemplate);

		$content  = new Template("table_content.tpl");
		$content->set("platformName", $platform_name);
		$content->set("memory", $json['system_specification']['hdd']);
		$content->set("disk", $json['system_specification']['ram']);
		$content->set("cpu", $json['system_specification']['cpu']);
		$content->set("sf", $scale_factor);
		$content->set("duration", "7.254562");
		$content->set("hitRatio", $data['stats']['hit_ratio']);
		$content->set("measurmentResults", $fullMeasurmentResults);
		$content->set("generatingSteps", $fullGeneratingSteps);
		$content->set("query", $fullQuery);
		$content->set("refreshResults", $fullRefreshResults);

		$layout = new Template("layout.tpl");
		$layout->set("title", "Report");
		$layout->set("content", $content->output());

		echo $layout->output();

		$output_html_filename = $platform_name . ' _SF_' . $scale_factor . '.html';
		file_put_contents($output_html_filename, $layout->output());
	}

	function load_checkpoints($logfile) {
		$log = file($logfile);
		$checkpoints = array();
		foreach ($log AS $row) {
			$row = trim($row);
			if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}).([0-9]{3}) [A-Z]+ [0-9]+ :[a-z0-9]+\.[a-z0-9]+   LOG:  checkpoint starting: (.*)$/', $row, $matches)) {
				$checkpoints[] = array('start' => mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]) + TIME_DIFF, 'cause' => $matches[8]);
			} else if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}).([0-9]{3}) [A-Z]+ [0-9]+ :[a-z0-9]+\.[a-z0-9]+   LOG:  checkpoint complete: wrote ([0-9]+) buffers/', $row, $matches)) {
				$checkpoints[count($checkpoints)-1]['end'] = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]) + TIME_DIFF;
				$checkpoints[count($checkpoints)-1]['buffers'] = $matches[8];
			}
		}
		return $checkpoints;
	}

	/* returns a hash (used to compare multiple plans) */
	function get_plan_hash($file) {
		$plan = file($file);
		$tmp = '';
		foreach ($plan AS $line) {
			$line = preg_replace('/[0-9]/', '', $line);
			$line = preg_replace('/^\s+/', '', $line);
			$line = preg_replace('/\s+$/', '', $line);
			$tmp .= $line;
		}
		return md5($tmp);
	}

	/* loads refresh query stats from given file */
	/* param $dir - directory with benchmark results */
	function load_refresh_avg_result($path) {
		$counter = 0;
		$result = 0;
		$results = file($path);
		foreach ($results AS $line) {
			if (substr_count($line, '=') > 0) {
				$tmp = explode('=', $line);
				$result += floatval($tmp[1]);
				$counter++;
			}
		}
		return floatval($result/$counter);
	}

	/* Calculating avg of refresh queries. */
	/* param $dir - directory with refresh query results */
	function refresh_avg_results($dir) {
		$refresh_queries = array();
		$rq11 = load_refresh_avg_result($dir."/rq11_results.log");
		$rq12 = load_refresh_avg_result("$dir/rq12_results.log");
		$refresh_queries['Q1'] = floatval(($rq11 + $rq12) / 2);
		$rq21 = load_refresh_avg_result("$dir/rq21_results.log");
		$rq22 = load_refresh_avg_result("$dir/rq22_results.log");
		$refresh_queries['Q2'] = floatval(($rq21 + $rq22) / 2);
		return $refresh_queries;
	}

	function get_power($data, $scale_factor, $refresh_avg) {
		$t = 1;
		for ($i = 1; $i <= 22; $i++) {
			$t *= $data['queries'][$i]['duration'];
		}
		$query_avg = $refresh_avg['Q1'] * $refresh_avg['Q2'];
		return (3600 * $scale_factor)/pow($query_avg * $t, 1/24);
	}

	function get_throghput($data, $scale_factor) {
		$stream_number = 1;
		$query_number = 22;
		return floatval(($scale_factor *$stream_number * $query_number * 3600)/$data['stats']['benchmark']);
	}

	function get_query_per_hour($data, $scale_factor, $refresh_avg) {
		return sqrt(get_power($data, $scale_factor, $refresh_avg) * get_throghput($data, $scale_factor));
	}

?>
