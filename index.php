<?php

	namespace Illuminate;
	use Mysqli;
  
  	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	error_reporting(1);
	define("ENV", 1);
	define("APP_NAME", "MUFAP");
	define("DS", DIRECTORY_SEPARATOR);

	if (ENV === 1) {
		define("HOST", "127.0.0.1");
		define("USER", "user");
		define("PASS", "password");
		define("PORT", "3306");
		define("DB_NAME", "mufap");
		define("CHAR_SET", "utf8mb4");
		define("PRODUCTION", HOST);
		define("PUT_FILE_DIR", "/var/www/html/mufapPKRV/CSV/");
    	define("LOG_FILE_DIR", "/var/www/html/mufapPKRV/");
    	// $__dir = LOG_FILE_DIR.DS;
	} else {
		define("HOST", "localhost");
		define("USER", "root");
		define("PASS", "");
		define("PORT", "3306");
		define("DB_NAME", "mufap");
		define("CHAR_SET", "utf8mb4");
		define("DEVELOPMENT", HOST);
		define("PUT_FILE_DIR", "./CSV/");
    	define("LOG_FILE_DIR", "./");
    	// $__dir = LOG_FILE_DIR.DS;
	}

	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	try {

	    $conn = new Mysqli(HOST, USER, PASS, DB_NAME, PORT);

	    $conn->set_charset(CHAR_SET);

	    $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

	} catch (\mysqli_sql_exception $e) {

	    throw new \mysqli_sql_exception($e->getMessage(), $e->getCode());

	}

	trait Homeostasis {

		public function cleanUp($data) {

			return mysql_real_escape_string(trim(htmlentities(strip_tags(addslashes($data)))));

		}

		public static function isWeekend($date) {

		    $current_date 	= strtotime($date);
		    $week_date		= date("l", $current_date);
		    $date 			= strtolower($week_date);
		    if($date == "saturday" || $date == "sunday") {
		    	$next_date = ($date == "saturday") ? strtotime("+2 day", $current_date) : strtotime("+1 day", $current_date);
				$next_date 		= date("Y-m-d", $next_date);
				return $next_date;
		        // return "true";
		    } else {
		        return "false";
		    }
		}

	}

	class SqlOperation {

		use Homeostasis;

		public static function getLastData($conn, $date) {

		    $sql = $conn->prepare("SELECT COUNT(1) FROM `mufap`.`tbl_pkrv` WHERE `current_date`=?");

		    $sql->bind_param("s", $date);

		    $sql->execute();

		    return $sql->get_result()->fetch_assoc();
		}

		public static function insertCSVData($conn, $current_date ,$getData , $next_date) {

			$sql = $conn->prepare("INSERT INTO `mufap`.`tbl_pkrv` (`current_date`,  `tenor`,  `mid_rate`,  `change`,  `next_date`) VALUES (?,?,?,?,?)");

	    	$sql->bind_param("sssss", self::cleanUp($current_date), self::cleanUp($getData[0]), self::cleanUp($getData[1]), self::cleanUp($getData[2]), self::cleanUp($next_date));

	 		$sql->execute();

			$sql->close();
		}
	}

    // Initialize a file URL to the variable
    $datetime 		= date("Y-m-d H:i:s");
	// $current_date 	= date("Y-m-d");
	$current_date 	= "2022-06-30";
	$str_date 		= strtotime($current_date);
	$next_date 		= strtotime("+1 day", $str_date);
	$next_date 		= date("Y-m-d", $next_date);
	public $year 			= date("Y");
	public $month 			= date("F");
	public $today 			= date("dmY");
	// $year 			= "2022";
	// $month 			= "June";
	// $today 			= "30062022";
    $url 			= "https://mufap.com.pk/pdf/PKRVs/{$year}/{$month}/PKRV{$today}.csv";
      
    // Use basename() function to return the base name of file
    $file_name 		= basename($url);
    define("FILE_NAME",  $file_name);

	$weekend = Homeostasis::isWeekend($current_date);
	// $result = SqlOperation::getLastData($conn, $weekend);

	if ($weekend == "false") {
		$current_date = $current_date;
		$weekend = "NotWeekend";
	// } elseif (count($result) != 0) {
	// 	echo "ElseIf-NotWeekend";
	// 	$current_date = $current_date;
	// 	echo $current_date;
	// 	echo "<br>";
	} else {
		$current_date = $weekend;
		$weekend = "Weekend";
	}
      
    // Use file_get_contents() function to get the file
    // from url and use file_put_contents() function to
    // save the file by using base name
    if (file_put_contents(PUT_FILE_DIR.DS.FILE_NAME, file_get_contents($url)))
    {
    	// count row number 
		$row = 0;
		// add you row number for skip 
		// hear we pass 1st row for skip in csv 
		$skip_row_number = array("1");

		// Insert data into database by CSV files
		$data_file = fopen(PUT_FILE_DIR.DS.FILE_NAME, "r");

	    while (($getData = fgetcsv($data_file, 100000, ",")) !== FALSE)
	    {
	    	$row++;	
			// count total filed of csv row 
		    $num = count($getData);
		    // check row for skip row 	
			if (in_array($row, $skip_row_number))	
		    {
				continue; 
				// skip row of csv
			}
			else
			{
		    	// $tenor    		= $getData[0];
		     	// $mid_rate    	= $getData[1];
		     	// $change      	= $getData[2];
		        SqlOperation::insertCSVData($conn, $current_date ,$getData , $next_date);
			}
	    }

	    fclose($data_file);

	    // `echo "{$datetime}|File 'PKRV{$today}' downloaded successfully|Data inserted$weekend" >> {$__dir}PKRV.log`;
	    $log = "{$datetime}|File 'PKRV{$today}' downloaded successfully|Data inserted|$weekend".PHP_EOL;
	    file_put_contents(LOG_FILE_DIR."PKRV.log", $log, FILE_APPEND);
        echo "File 'PKRV{$today}' downloaded successfully|Data inserted|$weekend";
    }
    else
    {
    	// `echo "{$datetime}|File 'PKRV{$today}' downloaded failed$weekend" >> {$__dir}PKRV.log`;
    	$log = "{$datetime}|File 'PKRV{$today}' downloaded failed|$weekend".PHP_EOL;
	    file_put_contents(LOG_FILE_DIR."PKRV.log", $log, FILE_APPEND);
        echo "File 'PKRV{$today}'downloading failed|$weekend";
    }

?>