<?PHP

class Colors {
	private $foreground_colors = array();
	private $background_colors = array();

	public function __construct() {
		// Set up shell colors
		$this->foreground_colors['black'] = '0;30';
		$this->foreground_colors['dark_gray'] = '1;30';
		$this->foreground_colors['blue'] = '0;34';
		$this->foreground_colors['light_blue'] = '1;34';
		$this->foreground_colors['green'] = '0;32';
		$this->foreground_colors['light_green'] = '1;32';
		$this->foreground_colors['cyan'] = '0;36';
		$this->foreground_colors['light_cyan'] = '1;36';
		$this->foreground_colors['red'] = '0;31';
		$this->foreground_colors['light_red'] = '1;31';
		$this->foreground_colors['purple'] = '0;35';
		$this->foreground_colors['light_purple'] = '1;35';
		$this->foreground_colors['brown'] = '0;33';
		$this->foreground_colors['yellow'] = '1;33';
		$this->foreground_colors['light_gray'] = '0;37';
		$this->foreground_colors['white'] = '1;37';

		$this->background_colors['black'] = '40';
		$this->background_colors['red'] = '41';
		$this->background_colors['green'] = '42';
		$this->background_colors['yellow'] = '43';
		$this->background_colors['blue'] = '44';
		$this->background_colors['magenta'] = '45';
		$this->background_colors['cyan'] = '46';
		$this->background_colors['light_gray'] = '47';
	}

	// Returns colored string
	public function getColoredString($string, $foreground_color = null, $background_color = null) {
		$colored_string = "";

		// Check if given foreground color found
		if (isset($this->foreground_colors[$foreground_color])) {
			$colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
		}
		// Check if given background color found
		if (isset($this->background_colors[$background_color])) {
			$colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
		}

		// Add string and end coloring
		$colored_string .=	$string . "\033[0m";

		return $colored_string;
	}

	// Returns all foreground color names
	public function getForegroundColors() {
		return array_keys($this->foreground_colors);
	}

	// Returns all background color names
	public function getBackgroundColors() {
		return array_keys($this->background_colors);
	}
}


function countGPUs(){
	//command line output from aticonfig --lsa
	$cmd = "DISPLAY=:0.0 aticonfig aticonfig --lsa";
	exec($cmd, $output);

	//find ati series number
	$match='/\d\d\d\d/';
	preg_match_all($match, implode($output), $gpus);
	$num = count($gpus[0]);

	return $num;
}

function getCores(&$gpus,$adapter){
	//command line output from aticonfig --odgc --adapter=0
	$cmd = "DISPLAY=:0.$adapter aticonfig --odgc --adapter=$adapter";
	exec($cmd, $output);

	//find core info
	$match='/\\d+/';
	preg_match_all($match, implode($output), $result);

	//remap results into gpu array, has to be a better way to do this.
	$gpus[$adapter][0] = $result[0][0];
	$gpus[$adapter][1] = $result[0][1];
	$gpus[$adapter][2] = $result[0][2];
	$gpus[$adapter][3] = $result[0][3];
	$gpus[$adapter][4] = $result[0][4];
	$gpus[$adapter][5] = $result[0][5];
	$gpus[$adapter][6] = $result[0][6];
	$gpus[$adapter][7] = $result[0][7];
	$gpus[$adapter][8] = $result[0][8];
	$gpus[$adapter][9] = $result[0][9];
	$gpus[$adapter][10] = $result[0][10];
}

function getFan(&$gpus,$adapter){
	//command line output aticonfig --pplib-cmd "get fanspeed 0";
	$cmd = "DISPLAY=:0.$adapter aticonfig --pplib-cmd \"get fanspeed 0\"";
	exec($cmd, $output);

	//find fan speed
	$match='/\d+%/';
	preg_match_all($match, implode($output), $result);

	//strip out the % sign
	$result[0][0] = preg_replace("/%/",'',$result[0][0]);
	
	//put the result into gpu array
	$gpus[$adapter][11]=$result[0][0];
}

function getTemp(&$gpus,$adapter){
	//command line output from aticonfig --odgt --adapter=0
	$cmd = "DISPLAY=:0.$adapter aticonfig --odgt --adapter=$adapter";
	exec($cmd, $output);

	//find temp of gpu
	$match='/\d\d\.\d\d/';
	preg_match_all($match, implode($output), $result);

	//put the result into gpu array
	$gpus[$adapter][12]=$result[0][0];	
}

function queryGpus(&$gpus){
	//How many GPUs?
	$num = countGPUs();
	$adapter = 0;
	
	while ($adapter<$num){
		//Make an array for each gpu
		//gpus	array |0      |1   |2     |3     |4     |5     |6    |7    |8    |9    |10  |11 |12
		//            |adapter|name|cocucl|mecucl|cocupe|mecupe|comin|comax|memin|memax|load|fan|temp

		//get cores info [0..10]
		getCores($gpus,$adapter);
		//get fan info [11]
		getFan($gpus,$adapter);	
		//get temp info [12]
		getTemp($gpus,$adapter);
		$adapter++;
	}
}

function adjustFan($gpus,$adapter,$speed){
	$cmd_odsc = "DISPLAY=:0.$adapter aticonfig --pplib-cmd \"set fanspeed 0 $speed\"";
	exec($cmd_odsc, $output);
}

function adjustCore($gpus,$adapter,$speed){
        $mecupe = $gpus[$adapter][5];
	$cmd_odsc = "DISPLAY=:0.$adapter aticonfig --odsc=$speed,$mecupe --adapter=$adapter";
	exec($cmd_odsc, $output);
}

function printStats($gpus){
	$count = countGPUs();
	//echo ("Found $count GPUs\n");
	$adapter = 0;
	while ($adapter < $count){
		$name=$gpus[$adapter][1];
		$cocucl=$gpus[$adapter][2];
		$mecucl=$gpus[$adapter][3];
		$cocupe=$gpus[$adapter][4];
		$mecupe=$gpus[$adapter][5];
		$comin=$gpus[$adapter][6];
		$comax=$gpus[$adapter][7];
		$memin=$gpus[$adapter][8];
		$memax=$gpus[$adapter][9];
		$load=$gpus[$adapter][10];
		$fan=$gpus[$adapter][11];
		$temp=$gpus[$adapter][12];

		echo ("GPU $adapter: Series $name\n");
		echo ("Core: Current: $cocucl Peak: $cocupe Range: $comin $comax\n");
		echo ("Memory: Current: $mecucl Peak: $mecupe Range: $memin $memax\n");
		echo ("Load: $load% Fan: $fan% Temp: $temp C\n\n");
		$adapter++;
	}
}

function maintainGPUs($gpus,$optimalTemp,$toleranceTemp,$warningTemp,$warningFan,$warningLoad,$minimumFan,$warningCore){
	$count = countGPUs();
	$adapter=0;
	while ($adapter < $count){
		$temp=$gpus[$adapter][12];
		$fan=$gpus[$adapter][11];
                $load=$gpus[$adapter][10];
		$cocucl=$gpus[$adapter][2];
                $comax=$gpus[$adapter][7];
		$comin=$gpus[$adapter][6];
                $corePercent=number_format( ($cocucl / $comax)*100 );
		$fanString = str_pad($fan,3,' ',STR_PAD_LEFT);
                $loadString = str_pad($load,3,' ',STR_PAD_LEFT);
                $coreString = str_pad($corePercent,3,' ',STR_PAD_LEFT);

		$colors = new Colors();
		//color temp
		if ($temp>($optimalTemp+$toleranceTemp)){
			$tempColor = $colors->getColoredString("$temp", "red", "");
		}
		else{
			$tempColor = $colors->getColoredString("$temp", "green", "");
		}
		//color fan
		if ($fan>$warningFan){
			$fanColor = $colors->getColoredString("$fanString%", "red", "");
		}
		else{
			$fanColor = $colors->getColoredString("$fanString%", "green", "");
		}
		//color load
		if ($load<$warningLoad){
			$loadColor = $colors->getColoredString("$loadString%", "red", "");
		}
		else{
			$loadColor = $colors->getColoredString("$loadString%", "green", "");
		}
		//color core
		if ($corePercent<$warningCore){
			$coreColor = $colors->getColoredString("$coreString%", "red", "");
		}
		else{
			$coreColor = $colors->getColoredString("$coreString%", "green", "");
		}


		echo ("GPU:$adapter $cocucl"."Mhz"." Load:$loadColor CoreClock:$coreColor Temp:$tempColor Fan:$fanColor ");

                //too hot, increase fan, lower core speed
		if ($temp > ($optimalTemp+$toleranceTemp)){
			if ($fan < 100){
				echo("Increasing Fan ");
                                if ($temp > $warningTemp){
                                	adjustFan($gpus,$adapter,100);
				}
				else{
					adjustFan($gpus,$adapter,$fan+1);
				}
			}
			if (($fan == 100) && ($temp > $warningTemp)){
				echo('ALERT! Reducing Core. ');
				//reduce core by 10, don't go below core min
                                if ( ($cocucl-10) >= ($comin) ){ 
					adjustCore($gpus,$adapter,$cocucl-10);
				}
			}
		}


		//increase clock, if temps and fan are cool
		if ($fan <= ($warningFan-10)){
			//if (($cocucl+5) <= ($comax+($comax*0.10))){
			if (($cocucl+5) <= ($comax)){ 
				adjustCore($gpus,$adapter,$cocucl+5);
				echo("Increasing Core. ");
			}
		}

                //too cold
		if ($temp < ($optimalTemp-$toleranceTemp)){
			if ($fan > $minimumFan){
				echo("Decreasing Fan. ");
				adjustFan($gpus,$adapter,$fan-1);
			}
			if ($fan <= $minimumFan){
				//echo("At Minimun Fan Speed $minimumFan%");
				adjustFan($gpus,$adapter,$minimumFan);
			}
		}
		echo("\n");
		$adapter++;
	}
}

//////////////////////////////////////
//////////////////////////////////////

$gpus = Array();

//Set these to where you want them. Best advice is keep temp below 80c and fan below 80% for longer life.
//$constants = Array();
$optimalTemp = 78;
$toleranceTemp = 1;
$warningTemp = 85;
$warningFan = 85;
$warningLoad = 95;
$minimumFan = 40;
$warningCore = 99;

//print all stats
queryGpus($gpus);
echo ("\n");
printStats($gpus);
sleep(3);

while (true){
	//clear the screen
	//passthru('clear');
	queryGpus($gpus);
	maintainGPUs($gpus,$optimalTemp,$toleranceTemp,$warningTemp,$warningFan,$warningLoad,$minimumFan,$warningCore);
	sleep(3);
}


?>
