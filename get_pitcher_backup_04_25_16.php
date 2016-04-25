<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require('simple_html_dom.php');

//CONNECT TO SQL        //
$mysqli = new mysqli("localhost", "root", "root", "dkings");
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
// END SQL CONNECTION  //

//    TESTING LOOP //
////  Need to verify that loop breaks at line 30  ////
$pageID = '30976';
//////////for ($y = 0; $y < 5;) {

//////////$html = file_get_html('http://espn.go.com/mlb/player/gamelog/_/id/'.$pageID.'/year/2016');
$html = file_get_html('http://espn.go.com/mlb/player/gamelog/_/id/30981/year/2015/corey-kluber');
//Test to see if page is default
$generalStats = $html->find('ul.general-info li');
if ($generalStats[0] != NULL) {

//Grab General Stats of Player
$generalStats = NULL;
$generalStats = $html->find('h1');
$name = $generalStats[0];
preg_match('~>(.*?)<~', $name, $output);
$name = $output[1];

$generalStats = $html->find('ul.general-info li');
$pos_num = $generalStats[0];
preg_match('~>(.*?)<~', $pos_num, $output);
$position = substr($output[1], -2);
if ($position != 'SP' && $position != 'RP') {
  echo "Player found is not a pitcher!!!";
}
$number   = substr($output[1], 1, 2);

$throw_bat = $generalStats[1];
preg_match('~Throws: (.*?),~', $throw_bat, $output2);
$throw = $output2[1];
preg_match('~Bats: (.*?)<~', $throw_bat, $output3);
$bat = $output3[1];

$teamArray = $generalStats[2];
preg_match('~<a(.*?)/a~', $teamArray, $output4);
$input = $output4[1];
preg_match('~>(.*?)<~', $input, $output5);
$team = $output5[1];

$generalStats2 = $html->find('ul.player-metadata li');
$birthDate = $generalStats2[0];
preg_match('~<span>Birth Date</span>(.*?) \(Age~', $birthDate, $output6);
$date = $output6[1];
$date = str_replace(',', '', $date);
//$date = str_replace(' ', '-', $date);
$date =  date('Y/m/d', strtotime($date));

$ht_wt = $generalStats2[4];
preg_match('~</span>(.*?),~', $ht_wt, $output7);
$height = $output7[1];

//Check to see if player is alread in database
$id = NULL;
$sql0 = "SELECT player_id FROM players WHERE player_name = '$name' AND position = '$position'";
$res = $mysqli->query($sql0);
$res->data_seek(0);
while ($row = $res->fetch_assoc()) {
  $id = $row['player_id'];
}
if ($id == NULL) {
$sql1 = "INSERT INTO players (`player_name`, `position`, `number`, `team`, `throw`, `bat`, `height`, `birth_date`) VALUES ('$name','$position','$number','$team','$throw','$bat','$height','$date')";
$res = $mysqli->query($sql1);

$sql0 = "SELECT player_id FROM players WHERE player_name = '$name' AND position = '$position'";
$res = $mysqli->query($sql0);
$res->data_seek(0);
while ($row = $res->fetch_assoc()) {
  $id = $row['player_id'];
}

echo "Inserted new record ".$sql1."<br>";
}
//Grab Field Stats of Player
$table = array();
$table = $html->find('table',1);

$sql3 = "INSERT INTO `pitcher_stats`(`player_id`,`game_date`, `opponent`, `win_result`, `score_result`, `innings_pitched`,
  `hits`, `runs`, `earned_runs`, `home_runs`, `walks`, `strikeouts`, `ground_balls`, `fly_balls`, `pitches`,
  `batters_faced`, `game_score`) VALUES ";

//Build table from from table
$headData     = array();
$mainTable    = 0;
$skipFirstRow = 0;
$counter      = 0;
foreach(($table->find('tr')) as $row) {
  $newRow = 1;
    $rowData = array();
    foreach($row->find('td') as $cell) {
        $cellData = $cell->innertext;
        //End Player loop if cell = Monthly Totals (needs to be updated to end if $cellData = Totals)
        if ($cellData == "Monthly Totals") {
        break;
        }
        //If cell = Regular Season... or cell = Year to Date then skip (needs to be updated to include monthly total or Date)
        if ($cellData != " Regular Season Games through April 23, 2016" && $cellData != "Year to Date") {

        if ($skipFirstRow > 1) {
        $counter++;
        //for column 2 select right 3 or right 2 characters
          if ($counter == 1) {
            if ($skipFirstRow > 2) {
              $sql3 .= ",";
            }
            $cellData .= " 2016";
            $date =  date('Y/m/d', strtotime($cellData));
            $sql3 .= "('".$id."','".$date."'";

            $newRow = 0;
          } elseif ($counter == 2) {
            $sql3 .= ",'".trim(substr($cellData, -3))."'";
            //for column 3 echo win or loss as boolean and echo score as seperate column
          } elseif ($counter == 3) {
            if (substr($cellData,0,-1) == "W") {
              $sql3 .= ",'1',";
            } else {
              $sql3 .= ",'0',";
            }
            preg_match('~<a(.*?)/a~', $cellData, $output);
            $input2 = $output[1];
            preg_match('~>(.*?)<~', $input2, $output2);
            $sql3 .= "'".$output2[1]."'";
          } elseif ($counter == 16 || $counter == 17 || $counter == 18) {
          } else {
            $sql3 .= ",'".$cellData."'";
            $newRow = 0;
          }
        }
      }
  }
  if ($skipFirstRow > 1) {
    $sql3 .= ")";
    $counter = 0;
}
    $skipFirstRow++;
}

echo "<br>".$sql3."<br>";

//Input new game stats into pitcher_stats table
$gameDate = NULL;
$sql5 = "SELECT MAX(game_date) AS max_game_date FROM pitcher_stats WHERE player_id = '$id'";
$res = $mysqli->query($sql5);
$res->data_seek(0);
while ($row = $res->fetch_assoc()) {
  $gameDate = $row['max_game_date'];
}
if ($gameDate == NULL) {
//////////$res = $mysqli->query($sql3);
echo "Inserted new record ".$sql3."<br>";
} else {
  echo "No new records to be added.";
}
}
//////////$y++;
//////////$pageID++;
//////////}
?>