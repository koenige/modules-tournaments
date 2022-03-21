/**
 * tournaments module
 * livegame JS
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Erik Kothe <kontakt@erikkothe.de>
 * @copyright Copyright © 2015 Erik Kothe
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function getGamedata(url) {
    if(this.live){
      var xmlhttp;
      if (window.XMLHttpRequest) {
          xmlhttp = new XMLHttpRequest();
      } else {
          xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
      }
      xmlhttp.onreadystatechange = function() {
          if (xmlhttp.readyState == XMLHttpRequest.DONE ) {
             if(xmlhttp.status == 200){
               setBoard(xmlhttp.responseText);  
             }
             else if(xmlhttp.status == 400) {
                console.log(400);
             }
             else {
                console.log("!200 AND !400")
             }
          }
      }
      xmlhttp.open("GET", url, true);
      xmlhttp.send();
    }
}

function setBoard(gamedata){
  this.gamedata = JSON.parse(gamedata);
  
  if(typeof this.lastmove == 'undefined') this.lastmove = this.gamedata.PlyCount;

  if(this.lastmove == this.gamedata.PlyCount){
    //document.getElementById("fuss").innerHTML = "Aktuell";    
  }else{
    if(MoveCount == this.lastmove){
      gamerefresh();
    }
  }

  this.live = this.gamedata.Live;
 if(this.live === false){
    gamerefresh(); 
  }
  
  this.lastmove = this.gamedata.PlyCount;
}

function gamerefresh(){
  document.getElementById("fuss").innerHTML = "";
  SetPgnMoveText(this.gamedata.Moves);
  SetMove(this.gamedata.PlyCount,0);
  MoveCount = this.gamedata.PlyCount;  
  
  var Partie = document.getElementById(this.gamedata.ID);

  var BlackClock = Partie.getElementsByClassName('BlackClock')[0];
  if (BlackClock) BlackClock.innerHTML = this.gamedata.BlackClock;
  var WhiteClock = Partie.getElementsByClassName('WhiteClock')[0];
  if (WhiteClock) WhiteClock.innerHTML = this.gamedata.WhiteClock;  
  var ECO = Partie.getElementsByClassName('ECO')[0];
  if (ECO) ECO.innerHTML = this.gamedata.ECO;  
  var LastUpdate = Partie.getElementsByClassName('LastUpdate')[0];
  if (LastUpdate) LastUpdate.innerHTML = this.gamedata.LastUpdate;  
  var PgnMoveText = Partie.getElementsByClassName('PgnMoveText')[0];  
  PgnMoveText.innerHTML = this.gamedata.PgnMoveText;  
}
