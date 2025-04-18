/**
 * tournaments module
 * live games for team tournaments
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Falco Nogatz <fnogatz@gmail.com>
 * @copyright Copyright © 2013 Falco Nogatz
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


  function customFunctionOnCheckLiveBroadcastStatus() {
    var theObj, theObjFrom, theObjTo;
    if ((theObjFrom = document.getElementById("GameLiveStatus")) && (theObjTo = document.getElementById("GameLiveStatus2"))) {
      theObjTo.innerHTML = theObjFrom.innerHTML;
      theObjTo.title = theObjFrom.title;
    }
    for (var gg in gameId) {
      if (theObj = document.getElementById("newMoves_" + gameId[gg])) {
        theObj.className = "noNewMoves";
      }
    }
    gameNewMoves = new Array();
    SetHighlight(false);
  }

  function customFunctionOnPgnTextLoad() {
    var theObj;

    gameId = new Array();

    gameWhiteTeam = new Array();
    gameBlackTeam = new Array();
    gameWhiteTitle = new Array();
    gameBlackTitle = new Array();
    gameWhiteElo = new Array();
    gameBlackElo = new Array();
    gameMatch = new Array();
    gameBoard = new Array();
    gameNewMoves = new Array();
    for (var gg = 0; gg < numberOfGames; gg++) {
      gameId[gg] = gg;
      gameWhiteTeam[gg] = customPgnHeaderTag("WhiteTeam", null, gg);
      gameBlackTeam[gg] = customPgnHeaderTag("BlackTeam", null, gg);
      gameWhiteTitle[gg] = customPgnHeaderTag("WhiteTitle", null, gg);
      gameBlackTitle[gg] = customPgnHeaderTag("BlackTitle", null, gg);
      gameWhiteElo[gg] = customPgnHeaderTag("WhiteElo", null, gg);
      gameBlackElo[gg] = customPgnHeaderTag("BlackElo", null, gg);
      gameMatch[gg] = crc32(gameWhiteTeam[gg]) + crc32(gameBlackTeam[gg]);
      gameBoard[gg] = customPgnHeaderTag("Board", null, gg);
      var thisCrc = (typeof(pgnHeader[gg]) == "string") ? (crc32(pgnHeader[gg].replace(/(^\s*|\s*$)/g, "")) % 65535) + 65535 : 0;
      newGameLengthFromCrc[thisCrc] = LiveBroadcastDemo ? gameDemoMaxPly[gg] : pgnGame[gg].replace(/(^\s*|\s*$)/g, "").length;
      gameNewMoves[gg] = ((newGameLengthFromCrc[thisCrc] !== gameLengthFromCrc[thisCrc]) && (!LiveBroadcastEnded));
    }
    gameLengthFromCrc = newGameLengthFromCrc.splice(0, newGameLengthFromCrc.length);

    printGames();
    if (autoHighlightNewmoves) {
      SetHighlight((typeof(gameNewMoves) != "undefined") && (typeof(gameNewMoves[currentGame]) != "undefined") && (gameNewMoves[currentGame]));
    }

    if ((LiveBroadcastTicker < 2) && delayIniGame) {
      Init(typeof(gameId[iniGame - 1]) != "undefined" ? gameId[iniGame - 1] : "0");
    }

    if (firstCustomFunctionOnPgnTextLoad) {
      firstCustomFunctionOnPgnTextLoad = false;
      if (theObj = document.getElementById("SideToMove")) { theObj.className = "sideToMove"; }
    }
  }

  function fixRound(thisRound) {
    return thisRound.replace(/\..*$/, ""); // only use the first number of rounds like 1.2.3
  }

  function printGames() {
    var ggId, fixedRound, currentEventRound, whitePlayer, whiteTitle, whiteElo, blackPlayer, blackTitle, blackElo, row;
    for (var gg in gameId) {
      ggId = gameId[gg];
      fixedRound = fixRound(gameRound[ggId]);
      currentEventRound = gameEvent[ggId] + fixedRound;

      whiteTitle = gameWhiteTitle[ggId];
      whiteElo = gameWhiteElo[ggId];
      whitePlayer = gameWhite[ggId];
      blackTitle = gameBlackTitle[ggId];
      blackElo = gameBlackElo[ggId];
      blackPlayer = gameBlack[ggId];

      row = findRow(whitePlayer, blackPlayer);
      if (row) {
        row.classList.add('game');
        row.id = 'gameRow_'+ggId;
        var res = row.querySelector('td.tm');
        res.classList.add('result');
        res.innerHTML = '<span id="newMoves_' + ggId + '" class="' + (gameNewMoves[ggId] ? 'newMoves' : 'noNewMoves') + '" title="new moves received">*</span>';
/*        row.querySelector('td.tm').innerHTML = '<span class="result" title="' + gameResult[ggId] + '">' + gameResult[ggId] + '</span><span id="newMoves_' + ggId + '" class="' + (gameNewMoves[ggId] ? 'newMoves' : 'noNewMoves') + '" title="new moves received">*</span>';*/
      }
    }

    Array.prototype.forEach.call(document.querySelectorAll('tr.game'), function(el) {
      var ggId = el.id.replace('gameRow_', '');
      el.addEventListener("click", function() {
        selectGame(ggId);
      });
    });
  }

  function selectGame(gameNum) {
    if (!showBoard) { return; }
    Init(gameNum);
    highlightGame(gameNum);
  }

function highlightGame(gameNum) {
	var theObj;
	if (!showBoard) { return; }
	if (theObj = document.getElementById("gameRow_" + oldSelectedGame)) {
	  if (oldSelectedGame !== gameNum) {
		theObj.style.fontWeight = "";
	  }
	}
	if (theObj = document.getElementById("gameRow_" + gameNum)) {
	   theObj.style.fontWeight = "bold";
	}
	oldSelectedGame = gameNum;
}

function customFunctionOnPgnGameLoad() {
	var theObj;

	//highlightGame(currentGame);

	if (theObj = document.getElementById("GameWhite")) {
		var whiteTitle = customPgnHeaderTag("WhiteTitle");
		var whiteElo = customPgnHeaderTag("WhiteElo");
		theObj.title = "white player:   " + gameWhite[currentGame] + (showPlayersInfo ? (whiteTitle ? "   " + whiteTitle : "") + (whiteElo ? "   " + whiteElo : "") : "");
		if (theObj.innerHTML === "") { theObj.innerHTML = "&nbsp;"; }
	}
	if (theObj = document.getElementById("GameBlack")) {
		var blackTitle = customPgnHeaderTag("BlackTitle");
		var blackElo = customPgnHeaderTag("BlackElo");
		theObj.title = "black player:   " + gameBlack[currentGame] + (showPlayersInfo ? (blackTitle ? "   " + blackTitle : "") + (blackElo ? "   " + blackElo : "") : "");
		if (theObj.innerHTML === "") { theObj.innerHTML = "&nbsp;"; }
	}

	if (theObj = document.getElementById("GameEvent")) {
		theObj.title = "event: " + gameEvent[currentGame] + (gameRound[currentGame] ? "   round: " + gameRound[currentGame] : "") + (gameDate[currentGame] ? "   date: " + gameDate[currentGame] : "") + (gameSite[currentGame] ? "   site: " + gameSite[currentGame] : "");
	}

	if (autoHighlightNewmoves) {
		SetHighlight((typeof(gameNewMoves) != "undefined") && (typeof(gameNewMoves[currentGame]) != "undefined") && (gameNewMoves[currentGame]));
	}

	setRowClicks();
}

function customFunctionOnMove() {
	var extraMoves = 2;

	document.getElementById("GamePrevMoves").innerHTML = "";
	document.getElementById("GameCurrMove").innerHTML = "";
	document.getElementById("GameNextMoves").innerHTML = "";
	var theObj = document.getElementById("GamePrevMoves");
	var thisPly = Math.max(CurrentPly - extraMoves - 1, StartPly);
	if (thisPly > StartPly) { theObj.innerHTML += "... "; }
	for (; thisPly < Math.min(CurrentPly + extraMoves, StartPly + PlyNumber); thisPly++) {
		if (thisPly == CurrentPly) {
			theObj = document.getElementById("GameNextMoves");
		}
		if (thisPly % 2 === 0) { theObj.innerHTML += Math.floor(1 + thisPly / 2) + ". "; }
		if (thisPly == CurrentPly - 1) {
			theObj = document.getElementById("GameCurrMove");
		}
		theObj.innerHTML += Moves[thisPly] + " ";
	}
	if (thisPly < StartPly + PlyNumber) { theObj.innerHTML += "..."; }

	if (theObj = document.getElementById("SideToMove")) {
		theObj.style.backgroundColor = CurrentPly % 2 ? "black" : "white";
	}
}

function gup(name) {

	name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
	var regexS = "[\\?&]"+name+"=([^&#]*)";
	// commented below to match first occurrence (to avoid users overruling setting)
	// regexS = regexS+"(?!.*"+regexS+")"; // matches the LAST occurrence
	var regex = new RegExp( regexS, "i" );
	var results = regex.exec( window.location.href );
	if (results !== null) { return decodeURIComponent(results[1]); }

	// allows for short version of the URL parameters, for instance sC matches squareColor
	var compact_name = name.charAt(0);
	for (var i=1; i<name.length; i++) {
		if (name.charAt(i).match(/[A-Z]/)) { compact_name = compact_name + name.charAt(i).toLowerCase(); }
	}
	name = compact_name;

	name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
	regexS = "[\\?&]"+name+"=([^&#]*)";
	// commented below to match first occurrence (to avoid users overruling setting)
	// regexS = regexS+"(?!.*"+regexS+")"; // matches the LAST occurrence
	regex = new RegExp( regexS, "i" );

	results = regex.exec( window.location.href );
	if (results !== null) { return decodeURIComponent(results[1]); }

	return "";
}


function findRow(whiteName, blackName) {
	var els = document.querySelectorAll('.team_ergebnis > tbody > tr');
	for (var i = 0; i < els.length; i++) {
		if (els[i].querySelector('td.th').textContent.trim() === whiteName.trim() && els[i].querySelector('td.ta').textContent.trim() === blackName.trim())
			return els[i];
		if (els[i].querySelector('td.th').textContent.trim() === blackName.trim() && els[i].querySelector('td.ta').textContent.trim() === whiteName.trim())
			return els[i];
	}
	return false;
}

function setRowClicks() {
	Array.prototype.forEach.call(document.querySelectorAll('.team_ergebnis > tbody > tr'), function(row, row_ix) {
		if (row_ix >= numberOfGames) {
			return false;
		}

		var result = row.querySelector('td.tm').textContent.trim();
		if (['- : +', '+ : -'].indexOf(result) > -1) {
			return false;
		}

		row.style.cursor = 'pointer';

		if (result === ':') {
			row.querySelector('td.tm').innerHTML = 'live';
			row.querySelector('td.tm').style.fontStyle = 'italic'
		}

		row.onclick = function() {
		Array.prototype.forEach.call(document.querySelectorAll('.team_ergebnis > tbody > tr'), function(row) {
			row.style.fontWeight = 'normal';
		});
		this.style.fontWeight = 'bold';

		var ix = findRowIndex(row);
		if (ix !== false) {
			selectGame(ix);
		}
		return false;
	}
	});
}


function findRowIndex(row) {
	var els = document.querySelectorAll('.team_ergebnis > tbody > tr');
	for (var i = 0, j = 0; i < els.length; i++) {
		if (els[i].querySelector('td.ta').textContent === row.querySelector('td.ta').textContent && 
			els[i].querySelector('td.th').textContent === row.querySelector('td.th').textContent) {
			return j;
		} else {
			var result = els[i].querySelector('td.tm').textContent.trim();
			if (['- : +', '+ : -'].indexOf(result) > -1) {
			  continue;
			}
			j++;
		}
	}
	return false;
}
