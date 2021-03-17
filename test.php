<?php

$LOG = false;

if ($LOG != true) {
	error_reporting(E_ERROR);
}

function xml_is_equal(SimpleXMLElement $xml1, SimpleXMLElement $xml2, $text_strict = false) {
	// skontroluj text
	if ($text_strict) {
		if ("$xml1" != "$xml2") return "mismatched text content (strict)";
	} else {
		if (trim("$xml1") != trim("$xml2")) return "mismatched text content";
	}

	// kontrola vsetkych atributov
	$search1 = array();
	$search2 = array();
	foreach ($xml1->attributes() as $a => $b) {
		$search1[$a] = "$b";		// force string conversion
	}
	foreach ($xml2->attributes() as $a => $b) {
		$search2[$a] = "$b";
	}
	if ($search1 != $search2) return "mismatched attributes";

	// namespace kontrola
	$ns1 = array();
	$ns2 = array();
	foreach ($xml1->getNamespaces() as $a => $b) {
		$ns1[$a] = "$b";
	}
	foreach ($xml2->getNamespaces() as $a => $b) {
		$ns2[$a] = "$b";
	}
	if ($ns1 != $ns2) return "mismatched namespaces";

	// ziskaj namespace atributy
	foreach ($ns1 as $ns) {
		$search1 = array();
		$search2 = array();
		foreach ($xml1->attributes($ns) as $a => $b) {
			$search1[$a] = "$b";
		}
		foreach ($xml2->attributes($ns) as $a => $b) {
			$search2[$a] = "$b";
		}
		if ($search1 != $search2) return "mismatched ns:$ns attributes";
	}

	// ziskaj vsetky deti
	$search1 = array();
	$search2 = array();
	foreach ($xml1->children() as $b) {
		if (!isset($search1[$b->getName()]))
			$search1[$b->getName()] = array();
		$search1[$b->getName()][] = $b;
	}
	foreach ($xml2->children() as $b) {
		if (!isset($search2[$b->getName()]))
			$search2[$b->getName()] = array();
		$search2[$b->getName()][] = $b;
	}
	// prejde vsetky deti
	if (count($search1) != count($search2)) return "mismatched children count";
	foreach ($search1 as $child_name => $children) {
		if (!isset($search2[$child_name])) return "xml2 does not have child $child_name";
		if (count($search1[$child_name]) != count($search2[$child_name])) return "mismatched $child_name children count";
		foreach ($children as $child) {
			// zhoduju sa dake z search2 deti?
			$found_match = false;
			$reasons = array();
			foreach ($search2[$child_name] as $id => $second_child) {
				if (($r = xml_is_equal($child, $second_child)) === true) {
					$found_match = true;
					unset($search2[$child_name][$id]);
				} else {
					$reasons[] = $r;
				}
			}
			if (!$found_match) return "xml2 does not have specific $child_name child: " . implode("; ", $reasons);
		}
	}

	// prejde vsetky namespace deti
	foreach ($ns1 as $ns) {
		// ziska vsetky deti
		$search1 = array();
		$search2 = array();
		foreach ($xml1->children() as $b) {
			if (!isset($search1[$b->getName()]))
				$search1[$b->getName()] = array();
			$search1[$b->getName()][] = $b;
		}
		foreach ($xml2->children() as $b) {
			if (!isset($search2[$b->getName()]))
				$search2[$b->getName()] = array();
			$search2[$b->getName()][] = $b;
		}
		// prejde vsetky deti
		if (count($search1) != count($search2)) return "mismatched ns:$ns children count";
		foreach ($search1 as $child_name => $children) {
			if (!isset($search2[$child_name])) return "xml2 does not have ns:$ns child $child_name";
			if (count($search1[$child_name]) != count($search2[$child_name])) return "mismatched ns:$ns $child_name children count";
			foreach ($children as $child) {
				// zasa ci sa zhoduji
				$found_match = false;
				foreach ($search2[$child_name] as $id => $second_child) {
					if (xml_is_equal($child, $second_child) === true) {
						// ak najde zhodu vymaze druhy
						$found_match = true;
						unset($search2[$child_name][$id]);
					}
				}
				if (!$found_match) return "xml2 does not have specific ns:$ns $child_name child";
			}
		}
	}
	return true;
}

function logg($string) {
	global $LOG;
	if ($LOG == true) {
		echo $string;
	}
}

function help() {
	echo "\n This is script designet to run tests on custom program and compare outpus followed by statistics.\n".
		 "\n $ php test.php --parse-script={parser.php} --directory={test_directory_path} --recursive\n".
		 "\n --parse-script= name of script to be tested or path to it from this script\n".
		 " --directory= path to directory containing test files [*.src, *.out, *.rc]\n".
		 " --recursive if this parameter is present script will look for test files in all subfolders\n".
		 "\n author:xpolic05@vutbr.cz\n";
    exit(0);
}

function getDirContents($dir, $filter = '', $recursive, &$results = array()) {
    $files = scandir($dir);

    foreach($files as $key => $value){
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value); 

        if(!is_dir($path)) {
        	
            if(preg_match($filter, $path)) {
	            $results[] = $path;
	        }
        } elseif($value != "." && $value != ".." && $recursive == true) {
            getDirContents($path, $filter, $recursive, $results);
        }
    }

    return $results;
}

$shortopts = "h";

$longopts  = array(
    "directory:",     	// testy
    "parse-script:",  	// nazov parseru
    "int-script",		// nazov interpretu
    "help",
    "recursive",
    "parse-only",
    "int-only",
);
$options = getopt($shortopts, $longopts);

$recursive = false;
$testComplexity = "both";

if (array_key_exists("help", $options)) {
	help();
}

if (array_key_exists("recursive", $options)) {
	$recursive = true;
}

if (array_key_exists("parse-only", $options)) {
	$testComplexity = "parse";
	if (array_key_exists("int-only", $options)) {	// oba argumenty nemozu byt zadane
		echo("There can not be both arguments: --parse-only and --int-only\n");
		exit(10);
	}
}

if (array_key_exists("int-only", $options)) {
	$testComplexity = "int";
}

$parseScript = "parse.php";
if ($options['parse-script'] != null) {
	$parseScript = $options['parse-script'];
}

$intScript = "interpret.py";
if ($options['int-script'] != null) {
	$intScript = $options['int-script'];
}

if ($options['directory'] != null) {
	$path = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . $options['directory'];
} else {
	$path = realpath(dirname(__FILE__));
}

logg("directory: " . $path . "\n");

// .src files
$source = getDirContents($path, '/\.src$/', $recursive);
#var_dump($source);

// .out files
$outFile = (getDirContents($path, '/\.out$/', $recursive));
#var_dump($outFile);

// .out files
$returnCode = (getDirContents($path, '/\.rc$/', $recursive));
#var_dump($returnCode);

// .src files
$input = getDirContents($path, '/\.in$/', $recursive);
#var_dump($source);

$lenComparation = array(count($source), count($outFile), count($returnCode), count($input));

// ak chyba daky testovaci subor tak ho doplni
$restartCondition = false;
for ($i = 0; $i < count($source); $i++) {
	$nameOfSource = pathinfo($source[$i]);
	$fileNameDiff = array($nameOfSource['filename'], 
	pathinfo($outFile[$i])['filename'], 
	pathinfo($returnCode[$i])['filename'], 
	pathinfo($input[$i])['filename']);

	// niesu rovnake
	if (count(array_unique($fileNameDiff)) != 1) {
		// treba doplnit subor .out
		if ($nameOfSource['filename'] != pathinfo($outFile[$i])['filename']) {

			$newFileName = $nameOfSource['dirname'] . DIRECTORY_SEPARATOR . $nameOfSource['filename'] . ".out";

			if (file_put_contents($newFileName, "") !== false) {
			    logg("File " . $newFileName . " created \n");
				array_splice( $outFile, $i+1, 0, $newFileName );
			} else {
			    echo("Cannot create file " . $newFileName . "\n");
			    exit(10);
			}
		}
		// treba doplnit subor .in
		if ($nameOfSource['filename'] != pathinfo($input[$i])['filename']) {

			$newFileName = $nameOfSource['dirname'] . DIRECTORY_SEPARATOR . $nameOfSource['filename'] . ".in";

			if (file_put_contents($newFileName, "") !== false) {
			    logg("File " . $newFileName . " created \n");
				array_splice( $input, $i+1, 0, $newFileName );
			} else {
			    echo("Cannot create file " . $newFileName . "\n");
			    exit(10);
			}
		}
		// treba doplnit subor .rc
		if ($nameOfSource['filename'] != pathinfo($returnCode[$i])['filename']) {

			$newFileName = $nameOfSource['dirname'] . DIRECTORY_SEPARATOR . $nameOfSource['filename'] . ".rc";

			if (file_put_contents($newFileName, "0") !== false) {
			    logg("File " . $newFileName . " created \n");
				array_splice( $outFile, $i+1, 0, $newFileName );
			} else {
			    echo("Cannot create file " . $newFileName . "\n");
			    exit(10);
			}
		}
	}
}


// zaklad vysledneho html
$HTML = "<TABLE style='width: 100%;border:line;border-collapse: collapse;'>
         <tbody><tr style='background-color:#666666;color:white;border-collapse: collapse'>
         <th>Číslo</th><th>Názov</th><th>Úspešnosť</th><th>Predpokladaná návratová hodnota </th> 
         <th> Návratová hodnota referenčného testu</th></tr>";

$totalTestsCount = count($source);
$testCount = 0;
$pass = 0;
$codePass = 0;
$directoryStatistic = explode("/", $source[0]);
array_pop($directoryStatistic);
$directortPass = 0;
$directoryTotal = 0;

// prechadza testy a porovnava
for ($i = 0; $i < $totalTestsCount; $i++)
{
	$testCount++;
	$output = null;
	$result_code = null;
	$sourceDestination = explode("/", $source[$i]);
	$testName = end($sourceDestination);

	array_pop($sourceDestination);

	if ($directoryStatistic != $sourceDestination) {
		

		$thisDirectoryPass = $pass - $directortPass;
		$thisDirectoryTotal = $testCount - $directoryTotal - 1;
		$thisDirectoryFail = $thisDirectoryTotal - $thisDirectoryPass;

		$directortPass = $pass;
		$directoryTotal = $testCount - 1;

		$HTML .= "<tr style='color:black;background-color:white'>";
		$HTML .= "<td>folder</td><td>".implode("/", $directoryStatistic)."</td>";
		$HTML .= "<td>" . $thisDirectoryPass . " z " . $thisDirectoryTotal . "</td></tr>";

		$directoryStatistic = $sourceDestination;
	}
			// ********************************* PARSE ****************************************** \\ 
	if ($testComplexity == "parse") {

	    logg("$ php " . $parseScript . " < " . $source[$i] . " > tmpOutput \n");
		exec("php " . $parseScript . " < " . $source[$i] . " > tmpOutput", $output, $result_code);

		try {
			$xml1 = new SimpleXMLElement(file_get_contents('tmpOutput'));
			$xml2 = new SimpleXMLElement(file_get_contents($outFile[$i]));
		
			$result = xml_is_equal($xml1, $xml2);
			if ($result === true) {
			    // XML su rovnake
				$pass++;
				logg("\e[0;30;42m   TEST[" . $testName . "] passed by XML\e[0m\n");

				$HTML .= "<tr style='color:green;background-color:#ccffcc'>";
				$HTML .= "<td>".$testCount."<td><i>".$testName."</i></td>";
				$HTML .= "</td><td>OK</td>";

			} else {
			    throw new Exception('XML are not the same');
			}
		}
		catch (Exception $e) {
			// zapise output zo skriptu do suboru pre diff

			logg("$ diff --ignore-case --ignore-trailing-space --ignore-space-change --ignore-blank-lines --ignore-tab-expansion tmpOutput " . 
				$outFile[$i] . "\n");
			exec("diff --ignore-case --ignore-trailing-space --ignore-space-change --ignore-blank-lines --ignore-tab-expansion tmpOutput " . 
				$outFile[$i] . " &>/dev/null", $testResult, $testReturnCode);
			if ($testReturnCode == 0) {
				$pass++;
				logg("\e[0;30;42m   TEST[" . $testName . "] passed\e[0m\n");

				$HTML .= "<tr style='color:green;background-color:#ccffcc'>";
				$HTML .= "<td>".$testCount."<td><i>".$testName."</i></td>";
				$HTML .= "</td><td>OK</td>";
			} else if($testReturnCode == 1) {
				logg(implode("\n", $testResult) . "\n");
				$HTML .= "<tr style='color:red;background-color:#ffcccc'>";
				$HTML .= "<td>".$testCount."<td><i>".$testName."</i></td>";
				$HTML .= "</td><td>FAIL</td>";
			} else {
				logg(" *** DIFF ERROR ***\n");
			}

		}
	}		// ********************************* BOTH ****************************************** \\  
	else if ($testComplexity == "both") {

		logg("$ php " . $parseScript . " < " . $source[$i] . " > tmpOutput \n");
		exec("php " . $parseScript . " < " . $source[$i] . " > tmpOutput", $output, $result_code);

		$output = null;
		$result_code = null;

		logg("$ python " . $intScript . " --source=tmpOutput --input=" . $input[$i] . " > tmpOutput \n");
		exec("python " . $intScript . " --source=tmpOutput --input=" . $input[$i] . " > tmpOutput", $output, $result_code);
		
		logg("$ diff --ignore-case --ignore-trailing-space --ignore-space-change --ignore-blank-lines --ignore-tab-expansion tmpOutput " . 
			$outFile[$i] . "\n");
		exec(" diff --ignore-case --ignore-trailing-space --ignore-space-change --ignore-blank-lines --ignore-tab-expansion tmpOutput " . 
			$outFile[$i] . " &>/dev/null", $testResult, $testReturnCode);
		if ($testReturnCode == 0) {
			$pass++;
			logg("\e[0;30;42m   TEST[" . $testName . "] passed\e[0m");

			$HTML .= "<tr style='color:green;background-color:#ccffcc'>";
			$HTML .= "<td>".$testCount."<td><i>".$testName."</i></td>";
			$HTML .= "</td><td>OK</td>";
		} else if($testReturnCode == 1) {
			$HTML .= "<tr style='color:red;background-color:#ffcccc'>";
			$HTML .= "<td>".$testCount."<td><i>".$testName."</i></td>";
			$HTML .= "</td><td>FAIL</td>";
		} else {
			logg(" *** DIFF ERROR ***\n");
		}
	}		// ******************************* INTERPRET **************************************** \\ 
	else if ($testComplexity == "int") {
		logg("$ python " . $intScript . " --source=" . $source[$i] . " --input=" . $input[$i] . " > tmpOutput \n");
		exec("python " . $intScript . " --source=" . $source[$i] . " --input=" . $input[$i] . " > tmpOutput", $output, $result_code);
		
		logg("$ diff --ignore-case --ignore-trailing-space --ignore-space-change --ignore-blank-lines --ignore-tab-expansion tmpOutput " . 
			$outFile[$i] . "\n");
		exec(" diff --ignore-case --ignore-trailing-space --ignore-space-change --ignore-blank-lines --ignore-tab-expansion tmpOutput " . 
			$outFile[$i] . " &>/dev/null", $testResult, $testReturnCode);
		if ($testReturnCode == 0) {
			$pass++;
			logg("\e[0;30;42m   TEST[" . $testName . "] passed\e[0m");

			$HTML .= "<tr style='color:green;background-color:#ccffcc'>";
			$HTML .= "<td>".$testCount."<td><i>".$testName."</i></td>";
			$HTML .= "</td><td>OK</td>";
		} else if($testReturnCode == 1) {
			$HTML .= "<tr style='color:red;background-color:#ffcccc'>";
			$HTML .= "<td>".$testCount."<td><i>".$testName."</i></td>";
			$HTML .= "</td><td>FAIL</td>";
		} else {
			logg(" *** DIFF ERROR ***\n");
		}
	}
	//    ********************** RETURN CODE ****************************     \\
	// zapise return code zo skriptu do suboru pre diff
	file_put_contents("tmpOutput", strval($result_code));

	logg(" $returnCode[$i]: " . $returnCode[$i] . "\n");
	$codeToCompare = file_get_contents($returnCode[$i]);

	logg("\n$ diff --ignore-all-space tmpOutput " . $returnCode[$i] . "\n");
	exec("diff --ignore-all-space tmpOutput " . $returnCode[$i], $testResult, $testReturnCode);

	if ($testReturnCode == 0) {
		$codePass++;
		logg("     \e[0;30;42mCODE[" . $result_code . "]DONE\e[0m\n");

		$HTML .= '<td bgcolor="#ccffcc">'.$codeToCompare."</td>".'<td bgcolor="#ccffcc">'.$result_code."</td>";
	} else if ($testReturnCode == 1) {
		logg(intval($testResult[1]));
		$HTML .= '<td bgcolor="#ffcccc">'.$codeToCompare."</td>".'<td bgcolor="#ffcccc">'.$result_code."</td>";
	} else {
		logg(" *** DIFF ERROR ***\n");
	}
	$HTML .= "</tr>" . "\n";
}
logg("\n tests: " . $totalTestsCount . " passed= " . $pass . " errors= " . $totalTestsCount - $pass . "   returned codes passed= " . $codePass . " wrong= " . $totalTestsCount - $codePass . "\n");

$sourceDestination = explode("/", $source[7]);
array_pop($sourceDestination);

$thisDirectoryPass = $pass - $directortPass;
$thisDirectoryTotal = $testCount - $directoryTotal;
$thisDirectoryFail = $thisDirectoryTotal - $thisDirectoryPass;

$directortPass = $pass;
$directoryTotal = $testCount;

$HTML .= "<tr style='color:black;background-color:white'>";
$HTML .= "<td>folder</td><td>".implode("/", $sourceDestination)."</td>";
$HTML .= "<td>" . $thisDirectoryPass . " z " . $thisDirectoryTotal . "</td></tr>";

if ($totalTestsCount == 0) { // division by zero 
	$totalTestsCount = 10000;
}

$HTML = "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">".
    	"<title> TEST.PHP -testovací skript pre IPP projekt </title> <style> td,th { border-collapse:collapse;text-align:center}".
    	"</style></head><body><h1>Výsledky testov</h1><br><h2> Celkovo testov " . $totalTestsCount . ": Testov úspešne prešlo " . 
    	$pass . " / Testov zlyhalo " . $totalTestsCount - $pass . ". (". round(100*$pass/$totalTestsCount)."% úspech)     Návratové kódy: prešlo " . 
    	$codePass . " / zlyhalo " . $totalTestsCount - $codePass . " (" . round(100*$codePass/$totalTestsCount) . "% úspech)</h2> ".$HTML."</tbody></table></body></html>";
if ($LOG == true) {
	file_put_contents("output.html", $HTML);
} else {
	echo $HTML . "\n";
}

unlink('tmpOutput');

?>