<?php
/**
 * PHP Script to simulate large numbers of rounds of the Monopoly board game
 *
 * This script has a small number of necessary limitations compared to the real game:
 *
 * 1) Each player begins the game at Go.  Because real games
 *    only last for a short number of rounds compared to the
 *    thousands and thousands this script simulates, actual
 *    odds would be slightly higher for the early spaces.
 *    Since the large number of rounds does away with this
 *    artificial "start" to the odds, this could be seen as
 *    a feature rather than a bug.
 *
 * 2) The script only counts the space where the player finally
 *    ends up.  For example, if a player starts on Go and rolls
 *    seven, moving to Chance, and draws the card advancing him
 *    to Illinois, the player's landing on Chance is not counted,
 *    but only Illinois.  Thus, the odds for Chance and Community
 *    Chest spaces seem unusually low.
 *
 * 3) Jail is counted as a space.  Thus, any players who roll
 *    three sets of doubles or are sent to Jail by a card
 *    register on the Jail space.  Go to Jail is counted as a 
 *    different space.  Any player who fails to roll doubles
 *    and stays in Jail is not counted.  This is why the total
 *    number of spaces landed on is lower than the total number
 *    of rolls.
 *
 * 4) The script includes all cards that move players to spaces
 *    and working Get Out of Jail Free cards.  However, players
 *    automatically use Get Out of Jail Free cards at the first
 *    available opportunity.  In real games this might not happen;
 *    if, for instance, a player goes to jail with a large number
 *    of profitable properties, they might purposely remain in
 *    jail so that the other players can land on their spaces,
 *    without any fear of landing on theirs.  There is no way
 *    to account for these kinds of choices.  Overall, including
 *    the cards while using them as soon as possible should
 *    nevertheless lead to very accurate odds.
 *
 * PHP version 5
 *
 * Kevin Kasson
 * February 8, 2014
 * monopoly.php
 *
 * 
 */

/* Begin by setting global variables */
$font = "/www/zxq.net/k/k/a/kkasson/htdocs/font.ttf" //This is the location of the font to use for the pie chart
$players = 4; //The numbers of players.  Default to four, unless...
if (isset($_GET['players'])) { //..the players value is passed through the address bar...
  if ((intval($_GET['players']) >= 2) && (intval($_GET['players']) <= 8)) { //...and is between 2 and 8
    $players = intval($_GET['players']);
  }
}
$rounds = 10000 / $players; //Total number of rounds of the game.  Default to 10,000 / players, unless...
if (isset($_GET['rounds'])) { //...the rounds value is passed through the address bar...  
  if ((intval($_GET['rounds']) > 0)  && (intval($_GET['rounds'] * $players) <= 100000)) { //...and is small enough to process
    $rounds = intval($_GET['rounds']);
  }
}
$space = array_fill(0, 41, 0); //Array containing how many times each space was landed on, set to 0 to begin
$i = 0;  //Counter for main loop, loops through all rolls in the game
$player = array_fill(0, $players, 0); //Player starting positions, 0 is Go, 39 is Boardwalk and 40 is used for players currently in jail
$hasCCJailCard = -1; //Variable for which player has the Community Chest Out of Jail Free Card.  Card is automatically used when player is taken to jail
$hasChanceJailCard = -1; //Variable for which player has the Chance Get Out of Jail Free Card.  Card is automatically used when player is taken to jail
$jc = array_fill(0, $players, 0);  //Jail counter, keeps tracks of how many times a player rolls while in jail, and lets him go on his third roll
$c = 0;  //Doubles counter, incremented when the player gets doubles, sent to jail at 3 doubles
$totalRolls = 0; //Current roll number, used for debug
$CCDeck = array_fill(0, 16, 0);  //Array containing the remaining Community Chest cards. Elements with a 0 value are unimportant, others have special meanings
$chanceDeck = array_fill(0, 16, 0); //Array containing the remaining Chance cards.  Elements with a 0 value are unimportant, others have special meanings

/* Game Functions */
function fillCommunityChestDeck() {  //Refill the Community Chest deck with all the cards
  global $CCDeck;
  global $hasCCJailCard;
  $CCDeck = array_fill(0, 16, 0); //Fill the deck with 0 to begin, and then add the important cards.  It doesn't matter that the deck is "stacked" because the cards are randomized when drawn rather than in the array
  if ($hasCCJailCard == -1) { //If nobody has the Get Out of Jail Card, include it in the deck...
    $CCDeck[0] = 1; //Get Out of Jail
  }
  else { //...otherwise, don't reshuffle it.
    unset($CCDeck[0]);
    $CCDeck = array_values($CCDeck);
  }
  $CCDeck[1] = 2; //Advance to Go
  $CCDeck[2] = 3; //Go to Jail
}
function fillChanceDeck() {  //Refill the Chance deck with all the cards
  global $chanceDeck;
  global $hasChanceJailCard;
  $chanceDeck = array_fill(0, 16, 0); //Fill the deck with 0 to begin, and then add the important cards.  It doesn't matter that the deck is "stacked" because the cards are randomized when drawn rather than in the array
  if ($hasChanceJailCard == -1) { //If nobody has the Get Out of Jail Card, include it in the deck....
    $chanceDeck[0] = 1; //Get Out of Jail
  }
  else { //...otherwise, don't reshuffle it.
    unset($chanceDeck[0]);
    $chanceDeck = array_values($chanceDeck);
  }
  $chanceDeck[1] = 2; //Advance to Go
  $chanceDeck[2] = 3; //Go to Jail
  $chanceDeck[3] = 4; //Advance to Nearest Railroad
  $chanceDeck[4] = 5; //Advance to Nearest Utility
  $chanceDeck[5] = 6; //Advance to Illinois
  $chanceDeck[6] = 7; //Advance to St. Charles
  $chanceDeck[7] = 8; //Go Back 3
  $chanceDeck[8] = 9; //Advance to Boardwalk
  $chanceDeck[9] = 10; //Advance to Reading Railroad
}
function getCommunityChestCard() { //Draws a random Community Chest card and removes it from the deck, returning its value
  global $CCDeck;
  $q = array_rand($CCDeck);
  $CCReturnValue = $CCDeck[$q];
  if (count($CCDeck) == 1) {
    fillCommunityChestDeck();
  }
  else {
    unset($CCDeck[$q]);
    $CCDeck = array_values($CCDeck);
  }
  return $CCReturnValue;
}
function getChanceCard() { //Draws a random Chance card and removes it from the deck, returning its value
  global $chanceDeck;
  $m = array_rand($chanceDeck);
  $chanceReturnValue = $chanceDeck[$m];
  if (count($chanceDeck) == 1) {
    fillChanceDeck();
  }
  else {
    unset($chanceDeck[$m]);
    $chanceDeck = array_values($chanceDeck);
  }
  return $chanceReturnValue;
}
function getSpaceName($s) { //Converts a space number (0, Go through 39, Boardwalk and 40, In Jail) to its string name
  switch ($s) {
    case 0:
      return "Go";
    break;
    case 1:
      return "Mediterranean Avenue"; 
    break;
    case 2:
      return "Community Chest 1"; 
    break;
    case 3:
      return "Baltic Avenue"; 
    break;
    case 4:
      return "Income Tax"; 
    break;
    case 5:
      return "Reading Rainbow";
    break;
    case 6:
      return "Oriental Avenue";
    break;
    case 7:
      return "Chance 1"; 
    break;
    case 8:
      return "Vermont Avenue"; 
    break;
    case 9:
      return "Connecticut Avenue"; 
    break;
    case 10:
      return "Just Visiting";
    break;
    case 11:
      return "St. Charles Place";
    break;
    case 12:
      return "Electric Company"; 
    break;
    case 13:
      return "States Avenue"; 
    break;
    case 14:
      return "Virginia Avenue"; 
    break;
    case 15:
      return "Pennsylvania Railroad"; 
    break;
    case 16:
      return "St. James Place"; 
    break;
    case 17:
      return "Community Chest 2"; 
    break;
    case 18:
      return "Tennessee Avenue"; 
    break;
    case 19:
      return "New York Avenue"; 
    break;
    case 20:
      return "Free Parking"; 
    break;
    case 21:
      return "Kentucky Avenue"; 
    break;
    case 22:
      return "Chance 2";
    break;
    case 23:
      return "Indiana Avenue";
    break;
    case 24:
      return "Illinois Avenue";
    break;
    case 25:
      return "B & O Railroad"; 
    break;
    case 26:
      return "Atlantic Avenue"; 
    break;
    case 27:
      return "Ventnor Avenue"; 
    break;
    case 28:
      return "Water Works"; 
    break;
    case 29:
      return "Marvin Gardens"; 
    break;
    case 30:
      return "Go to Jail";
    break;
    case 31:
      return "Pacific Avenue";
    break;
    case 32:
      return "North Carolina Avenue"; 
    break;
    case 33:
      return "Community Chest 3"; 
    break;
    case 34:
      return "Pennsylvania Avenue";
    break;
    case 35:
      return "Short Line"; 
    break;
    case 36:
      return "Chance 3";
    break;
    case 37:
      return "Park Place";
    break;
    case 38:
      return "Luxury Tax";
    break;
    case 39:
      return "Boardwalk";
    break;
    case 40:
      return "In Jail";
    break;
  }
}
function doPlayer($n) { //This function handles the player's turn
  global $player;
  global $c;
  global $hasChanceJailCard;
  global $hasCCJailCard;
  global $jc;
  global $totalRolls;
  global $space;
  global $i;
  $totalRolls++;
  echo $totalRolls;
  if ($player[$n] == 40) {  //If the player is in jail...
    if ($hasCCJailCard == $n) { //...see if he has the Community Chest Get Out of Jail Free card
      $hasCCJailCard = -1;  //If he does, let him out and take away his card
      $player[$n] = 10;
    }
    elseif ($hasChanceJailCard == $n) { //Otherwise, see if he has the Chance Get Out of Jail Free card
      $hasChanceJailCard = -1;  //If he does, let him out and take away his card
      $player[$n] = 10;
    }
  }
  $dOne = mt_rand(1,6); //Roll the first die
  $dTwo = mt_rand(1,6); //Roll the second die
  if ($player[$n] == 40) { //If the player is still in jail (i.e. he didn't have a Get Out of Jail Free card)...
    if ($dOne == $dTwo) { //...and he rolls doubles...
      $player[$n] = 10;  //...let him leave.
    } 
    else { //If he doesn't roll doubles...
      if ($jc[$n] == 2) { //...and he's rolled three times already...
        $player[$n] = 10;
        $jc[$n] = 0; //...let him go.  Otherwise...
      }
      else {
        $jc[$n]++; //...increase his roll counter, and move to the next player.
        echo "Player " . $n . " rolled " . $dOne . " and " . $dTwo . " and is still in Jail.\r\n";
        return 0;
      }
    }
  }
  if ($dOne == $dTwo) { //Check for doubles
    $c++;  //Increase the doubles counter
    if ($c == 3) {  //Third set of doubles...
      $player[$n] = 40; //...send him to Jail
      $space[$player[$n]]++; //He went to Jail, so we'll increment the Jail counter.
      return 0;
    }
  }
  $dTotal = $dOne + $dTwo;
  $oldNum = $player[$n]; //Record the space the player was on at the start of his turn.  This is only for echoing later
  if (($player[$n] + $dTotal) > 39) { //If player would pass Go...
    $player[$n] -= 40; //...adjust his value accordingly.
  }
  $player[$n] += $dTotal; //Move player to his new space
  echo "Player " . $n . " rolled " . $dOne . " and " . $dTwo . " for a total of " . $dTotal . " and moved from " . $oldNum . getSpaceName($oldNum) . " to " . $player[$n] . getSpaceName($player[$n]) . ". ";
  switch ($player[$n]) { //This switch handles special spaces.  Most spaces are ignored, but Go to Jail, Chance, and Community Chest must be handled
    case 2:
    case 17:
    case 33: //Community Chest
      $communityChestCard = getCommunityChestCard(); //Draw a card
      switch ($communityChestCard) { //See if it's an important one
      case 1: //Get Out of Jail Free
        $hasCCJailCard = $n;
      break;
      case 2: //Advance to Go
        $player[$n] = 0;
      break;
      case 3: //Go to Jail;
        $player[$n] = 40;
        $space[$player[$n]]++; //He went to Jail, so we'll increment the Jail counter.
        return 0;
      break;
      }
    break;
    case 7:
    case 22:
    case 36:  //Chance
      $chanceCard = getChanceCard(); //Draw a Card
      switch ($chanceCard) { //See if it's an important one
        case 1: //Get Out of Jail Free
          $hasChanceJailCard = $n;
        break;
        case 2: //Advance to Go
          $player[$n] = 0;
        break;
        case 3: //Go to Jail;
          $player[$n] = 40;
          $space[$player[$n]]++; //He went to Jail, so we'll increment the Jail counter.
          return 0;
        break;
        case 4: //Advance to Railroad;
          $gotoRailroad = 5;
          if ($player[$n] == 7) {
            $gotoRailroad = 15;
          }
          if ($player[$n] == 22) {
            $gotoRailroad = 25;
          }
          $player[$n] = $gotoRailroad;
        break;
        case 5: //Advance to Utility;
          $gotoUtility = 12;
          if ($player[$n] == 22) {
            $gotoUtility = 28;
          }
          $player[$n] = $gotoUtility;
        break;
        case 6: //Advance to Illinois;
          $player[$n] = 24;
        break;
        case 7: //Advance to St. Charles;
          $player[$n] = 11;
        break;
        case 8: //Go Back 3
          $player[$n] -= 3;
        break;
        case 9: //Advance to Boardwalk;
          $player[$n] = 39;
        break;
        case 10: //Advance to Reading;
          $player[$n] = 5;
        break;
      }
    break;
    case 30: //He landed on Go to Jail
      $space[$player[$n]]++; //He went to Jail, so we'll set the Go to Jail counter before he's moved.
      $player[$n] = 40;
      return 0;
    break;
  }
  echo "<br />\r\n";
  $space[$player[$n]]++; //This is the space that the player ended up on.  Increment its counter.
  if (($dOne == $dTwo) && ($player[$n] != 40)) { //If the player rolled doubles and didn't end up in Jail...
    doPlayer($n); //...let him roll again
  }
}

/* Play the Game */
fillCommunityChestDeck();  //Fill the Community Chest deck before the game begins
fillChanceDeck();  //Fill the Chance deck before the game begins
echo "<h2>Monopoly Simulation with " . $players . " players and " . $rounds . " rounds</h2>\r\n";
echo "<table id='rollsTable' style='display: none;'>";
while ($i < $rounds) { //Each iteration is one round of the game, going through all the players
  echo "<tr>";
  for ($p = 0; $p < $players; $p++) {  //This loops through each of the players
    $c = 0; //Reset the Doubles counter before he starts his turn...
    echo "<td>";
    doPlayer($p); //...and then let him start rolling
    echo "</td>";
  }
  echo "</tr>\r\n";
  $i = $i + 1;
}
echo "</table>\r\n";
echo "<br /><br /><a onClick=\"document.getElementById('rollsTable').style.display='block';return false;\" href='#' id='showRolls'>Show Rolls Table</a><br /><br />\r\n";
$spacesTotal = 0;
for ($s = 0; $s <= 40; $s++) { //We're done playing now, let's print out the results
  echo getSpaceName($s) . ": " . $space[$s] . "<br />\r\n";
  $spacesTotal += $space[$s];
}
echo "Total count: " . $spacesTotal . " spaces landed on and " . $totalRolls . " rolls.  [" . ($totalRolls - $spacesTotal) . " rolls while in Jail]";

/* Pie Chart */
$sx = 600; //Pie chart width
$sy = 600; //Height
$sz = 50; //And depth
$cx = $sx / 2;
$cy = $sy / 2;
for($u = 0; $u <= 40; $u++) { //Convert the results to angles
   $angle[$u] = (($space[$u] / $spacesTotal) * 360);
   $angle_sum[$u] = array_sum($angle);
}
$im  = imagecreate ($sx,$sy+$sz); //Create the image for the chart
$background = imagecolorallocate($im, 255, 255, 255); //Set the colors
$colors[0] = imagecolorallocate($im,150,150,150); //Actual color
$colord[0] = imagecolorallocate($im,100,100,100); //Darker shade for the side of the chart
$colors[1] = imagecolorallocate($im,100,17,105);
$colord[1] = imagecolorallocate($im,100/1.5,17/1.5,105/1.5);
$colors[2] = imagecolorallocate($im,150,150,150);
$colord[2] = imagecolorallocate($im,100,100,100);
$colors[3] = imagecolorallocate($im,100,17,105);
$colord[3] = imagecolorallocate($im,100/1.5,17/1.5,105/1.5);
$colors[4] = imagecolorallocate($im,150,150,150);
$colord[4] = imagecolorallocate($im,100,100,100);
$colors[5] = imagecolorallocate($im,0,0,0);
$colord[5] = imagecolorallocate($im,0,0,0);
$colors[6] = imagecolorallocate($im,195,211,250);
$colord[6] = imagecolorallocate($im,195/1.5,211/1.5,250/1.5);
$colors[7] = imagecolorallocate($im,150,150,150);
$colord[7] = imagecolorallocate($im,100,100,100);
$colors[8] = imagecolorallocate($im,195,211,250);
$colord[8] = imagecolorallocate($im,195/1.5,211/1.5,250/1.5);
$colors[9] = imagecolorallocate($im,195,211,250);
$colord[9] = imagecolorallocate($im,195/1.5,211/1.5,250/1.5);
$colors[10] = imagecolorallocate($im,150,150,150);
$colord[10] = imagecolorallocate($im,100,100,100);
$colors[11] = imagecolorallocate($im,207,54,168);
$colord[11] = imagecolorallocate($im,207/1.5,54/1.5,168/1.5);
$colors[12] = imagecolorallocate($im,150,150,150);
$colord[12] = imagecolorallocate($im,100,100,100);
$colors[13] = imagecolorallocate($im,207,54,168);
$colord[13] = imagecolorallocate($im,207/1.5,54/1.5,168/1.5);
$colors[14] = imagecolorallocate($im,207,54,168);
$colord[14] = imagecolorallocate($im,207/1.5,54/1.5,168/1.5);
$colors[15] = imagecolorallocate($im,0,0,0);
$colord[15] = imagecolorallocate($im,0,0,0);
$colors[16] = imagecolorallocate($im,235,155,28);
$colord[16] = imagecolorallocate($im,235/1.5,155/1.5,28/1.5);
$colors[17] = imagecolorallocate($im,150,150,150);
$colord[17] = imagecolorallocate($im,100,100,100);
$colors[18] = imagecolorallocate($im,235,155,28);
$colord[18] = imagecolorallocate($im,235/1.5,155/1.5,28/1.5);
$colors[19] = imagecolorallocate($im,235,155,28);
$colord[19] = imagecolorallocate($im,235/1.5,155/1.5,28/1.5);
$colors[20] = imagecolorallocate($im,150,150,150);
$colord[20] = imagecolorallocate($im,100,100,100);
$colors[21] = imagecolorallocate($im,255,0,0);
$colord[21] = imagecolorallocate($im,255/1.5,0,0);
$colors[22] = imagecolorallocate($im,150,150,150);
$colord[22] = imagecolorallocate($im,100,100,100);
$colors[23] = imagecolorallocate($im,255,0,0);
$colord[23] = imagecolorallocate($im,255/1.5,0,0);
$colors[24] = imagecolorallocate($im,255,0,0);
$colord[24] = imagecolorallocate($im,255/1.5,0,0);
$colors[25] = imagecolorallocate($im,0,0,0);
$colord[25] = imagecolorallocate($im,0,0,0);
$colors[26] = imagecolorallocate($im,255,255,40);
$colord[26] = imagecolorallocate($im,255/1.5,255/1.5,40/1.5);
$colors[27] = imagecolorallocate($im,255,255,40);
$colord[27] = imagecolorallocate($im,255/1.5,255/1.5,40/1.5);
$colors[28] = imagecolorallocate($im,150,150,150);
$colord[28] = imagecolorallocate($im,100,100,100);
$colors[29] = imagecolorallocate($im,255,255,40);
$colord[29] = imagecolorallocate($im,255/1.5,255/1.5,40/1.5);
$colors[30] = imagecolorallocate($im,150,150,150);
$colord[30] = imagecolorallocate($im,100,100,100);
$colors[31] = imagecolorallocate($im,40,150,30);
$colord[31] = imagecolorallocate($im,40/1.5,100,30/1.5);
$colors[32] = imagecolorallocate($im,60,60,60);
$colord[32] = imagecolorallocate($im,40,40,40);
$colors[33] = imagecolorallocate($im,40,150,30);
$colord[33] = imagecolorallocate($im,40/1.5,100,30/1.5);
$colors[34] = imagecolorallocate($im,40,150,30);
$colord[34] = imagecolorallocate($im,40/1.5,100,30/1.5);
$colors[35] = imagecolorallocate($im,0,0,0);
$colord[35] = imagecolorallocate($im,0,0,0);
$colors[36] = imagecolorallocate($im,150,150,150);
$colord[36] = imagecolorallocate($im,100,100,100);
$colors[37] = imagecolorallocate($im,73,28,235);
$colord[37] = imagecolorallocate($im,73/1.5,28/1.5,235/1.5);
$colors[38] = imagecolorallocate($im,150,150,150);
$colord[38] = imagecolorallocate($im,100,100,100);
$colors[39] = imagecolorallocate($im,73,28,235);
$colord[39] = imagecolorallocate($im,73/1.5,28/1.5,235/1.5);
$colors[40] = imagecolorallocate($im,200,200,200);
$colord[40] = imagecolorallocate($im,200/1.5,200/1.5,200/1.5);
$angle_sum[count($angle_sum) - 1] += 1; //Correction to make sure the chart fills the pie
for ($z = 1; $z <= $sz; $z++) { //Create the 3D part of the chart
  imagefilledarc($im, $cx, ($cy + $sz) - $z, $sx, $sy, 0, $angle_sum[0], $colord[0], IMG_ARC_NOFILL); //Draw the first slice
  for($v = 1; $v <= 40; $v++) {
    imagefilledarc($im, $cx, ($cy + $sz) - $z, $sx, $sy, $angle_sum[$v - 1], $angle_sum[$v], $colord[$v], IMG_ARC_NOFILL); //And all the rest
  }
}
imagefilledarc($im, $cx, $cy, $sx, $sy, 0, $angle_sum[0], $colors[0], IMG_ARC_PIE); //Create the actual chart.  This draws the first slice
imagefilledarc($im, $cx, $cy, $sx, $sy, 0, $angle_sum[0], imagecolorallocate($im,40,40,40), IMG_ARC_EDGED | IMG_ARC_NOFILL); //And its border
for ($w = 1; $w <= 40; $w++) {
  imagefilledarc($im, $cx, $cy, $sx, $sy, $angle_sum[$w - 1], $angle_sum[$w], $colors[$w], IMG_ARC_PIE); //Draw each slice...
  imagefilledarc($im, $cx, $cy, $sx, $sy, $angle_sum[$w - 1], $angle_sum[$w], imagecolorallocate($im,40,40,40), IMG_ARC_EDGED | IMG_ARC_NOFILL); //...and its border
}
imagettftext($im, 18, $angle_sum[0] / -2, $cx + cos(deg2rad(((0 + $angle_sum[0]) / 2) + 1)) * ($sx / 2) * 0.84 - 22, $cy + sin(deg2rad(((0 + $angle_sum[0]) / 2) + 1)) * ($sy / 2) * 0.84 + 2,imagecolorallocate($im,255,255,255), $font, "Go: " . number_format($space[0] / $spacesTotal * 100, 2) . "%"); //Write the percentage for Go
for ($x = 1; $x <= 40; $x++) {
  imagettftext($im, 18, ($angle_sum[$x - 1] + $angle_sum[$x]) / -2, $cx + cos(deg2rad((($angle_sum[$x - 1] + $angle_sum[$x]) / 2)+1)) * ($sx / 2) * 0.84 + 4, $cy + sin(deg2rad((($angle_sum[$x - 1] + $angle_sum[$x]) / 2)+1)) * ($sy / 2) * 0.84 + 2,imagecolorallocate($im,255,255,255), $font, number_format($space[$x] / $spacesTotal * 100, 2) . "%"); //And all the other spaces
}
imagepng($im, "pie.png");  //Export the pie chart
imagedestroy($im); //And clear up the image

/* Bar Graph */
$columns  = count($space);
$width = 720; 
$height = 600; 
$padding = 4; 
$column_width = $width / $columns;
$im = imagecreate($width,$height);
$gray_lite = imagecolorallocate ($im,238,238,238);
$gray_dark = imagecolorallocate ($im,127,127,127);
$white     = imagecolorallocate ($im,255,255,255);
$colors[0] = imagecolorallocate($im,150,150,150);
$colors[1] = imagecolorallocate($im,100,17,105);
$colors[2] = imagecolorallocate($im,150,150,150);
$colors[3] = imagecolorallocate($im,100,17,105);
$colors[4] = imagecolorallocate($im,150,150,150);
$colors[5] = imagecolorallocate($im,0,0,0);
$colors[6] = imagecolorallocate($im,195,211,250);
$colors[7] = imagecolorallocate($im,150,150,150);
$colors[8] = imagecolorallocate($im,195,211,250);
$colors[9] = imagecolorallocate($im,195,211,250);
$colors[10] = imagecolorallocate($im,150,150,150);
$colors[11] = imagecolorallocate($im,207,54,168);
$colors[12] = imagecolorallocate($im,150,150,150);
$colors[13] = imagecolorallocate($im,207,54,168);
$colors[14] = imagecolorallocate($im,207,54,168);
$colors[15] = imagecolorallocate($im,0,0,0);
$colors[16] = imagecolorallocate($im,235,155,28);
$colors[17] = imagecolorallocate($im,150,150,150);
$colors[18] = imagecolorallocate($im,235,155,28);
$colors[19] = imagecolorallocate($im,235,155,28);
$colors[20] = imagecolorallocate($im,150,150,150);
$colors[21] = imagecolorallocate($im,255,0,0);
$colors[22] = imagecolorallocate($im,150,150,150);
$colors[23] = imagecolorallocate($im,255,0,0);
$colors[24] = imagecolorallocate($im,255,0,0);
$colors[25] = imagecolorallocate($im,0,0,0);
$colors[26] = imagecolorallocate($im,255,255,40);
$colors[27] = imagecolorallocate($im,255,255,40);
$colors[28] = imagecolorallocate($im,150,150,150);
$colors[29] = imagecolorallocate($im,255,255,40);
$colors[30] = imagecolorallocate($im,150,150,150);
$colors[31] = imagecolorallocate($im,40,150,30);
$colors[32] = imagecolorallocate($im,60,60,60);
$colors[33] = imagecolorallocate($im,40,150,30);
$colors[34] = imagecolorallocate($im,40,150,30);
$colors[35] = imagecolorallocate($im,0,0,0);
$colors[36] = imagecolorallocate($im,150,150,150);
$colors[37] = imagecolorallocate($im,73,28,235);
$colors[38] = imagecolorallocate($im,150,150,150);
$colors[39] = imagecolorallocate($im,73,28,235);
$colors[40] = imagecolorallocate($im,200,200,200);
imagefilledrectangle($im,0,0,$width,$height,$white); 
$max_value = max($space);
for ($k = 0; $k < $columns; $k++) {
  $column_height = ($height / 100) * (($space[$k] / $max_value) * 100);
  $x1 = $k * $column_width; 
  $y1 = $height - $column_height; 
  $x2 = (($k + 1) * $column_width) - $padding; 
  $y2 = $height; 
  imagefilledrectangle($im,$x1,$y1,$x2,$y2,$colors[$k]); //Draw each bar
  imageline($im,$x1,$y1,$x1,$y2,$gray_lite); //And add some effects
  imageline($im,$x1,$y2,$x2,$y2,$gray_lite); 
  imageline($im,$x2,$y1,$x2,$y2,$gray_dark); 
} 
imagepng($im, "pie2.png"); //Export the bar graph
imagedestroy($im); //And clear up the image
echo "<br /><img src='pie.png' />&nbsp;&nbsp;<img src='pie2.png' />";
?>