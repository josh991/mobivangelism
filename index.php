<?php

	// $rawXml = $_POST["xml"];
	$rawXml = file_get_contents('./input.xml', FILE_USE_INCLUDE_PATH);
	$parse_xml = simplexml_load_string($rawXml);

	// Set FromUserName as session ID; removed the underscore.
	session_id(str_replace("_", "", (string) $parse_xml->FromUserName)); 
	session_start();

	// Begin session population with user data.
	$_SESSION["userFrom"] = (string) $parse_xml->FromUserName;
	$_SESSION["userTo"] = (string) $parse_xml->ToUserName;
	$_SESSION["userContent"] = (string) $parse_xml->Content;	

	
	//////////////////////////////////////////////////////////////////////
	//  Internals and Logic

	class Factory {
		
		public static function sanitize(){
			$input = strtolower(substr(trim($_SESSION["userContent"]), 0, 1));
			$_SESSION["guess"] = (string) $input;

			if ( preg_match("/[a-z]/", $_SESSION["guess"]) ) {
				// if input is a letter then proceed.
				self::gameState();
			} elseif ($_SESSION["guess"] === "1") {
				self::newGame();
			} else {
				$_SESSION["resContent"] = "Invalid input! Please select a letter of the alphabet.";
			}
		}

		private static function gameState(){
			if ($_SESSION["word_orig"]) {
				// Game in progress.
				self::inProgress();
			} else {
				// No game in progress. Start a new game.
				self::newGame();
			}
		}

		private static function newGame(){
			// Clean session
			$_SESSION["resContent"] = null;
			$_SESSION["guess_history"] = null;
			$_SESSION["guess"] = null;
			$_SESSION["gameStatus"] = null;
			$_SESSION["tries_rem"] = 4;

			// Get a random word from list. File in current dir.
			$wordList = file_get_contents('./wordlist1.txt', FILE_USE_INCLUDE_PATH);
			$wordsToArray = explode("\r\n", $wordList);
			$word = $wordsToArray[ rand(0, count($wordsToArray)) ];
			$_SESSION["word_orig"] = $word;	
			$_SESSION["word"] = $word;			

			self::prepWordDisplay();
		}

		private static function prepWordDisplay(){
			$_SESSION["word_length"] = strlen($_SESSION["word_orig"]);

			// replace characters with underscores.
			for ($i=0; $i < $_SESSION["word_length"]; $i++) { 				
				$_SESSION["word"][$i] = "_";
			}

			// Spaces between the underscores makes for a better experience for the user.
			$_SESSION["word"] = join(str_split($_SESSION["word"]), " ");

			self::inProgress();

		}

		private static function inProgress(){			
			// Test the user's input.			
			// Check if the input has already been submitted.			

			$dupGuess = preg_match("/".$_SESSION["guess"]."/", $_SESSION["guess_history"]);
						
			if ($dupGuess) {
				// handle duplicate entries from user.
				if ($_SESSION["gameStatus"] !== "end") {
					$_SESSION["resContent"] = self::msgTemplate("You have already tried the letter");
				}

			} else {				

				if ($_SESSION["gameStatus"] !== "end") {
				
					// Add input to input history
					$_SESSION["guess_history"] .= $_SESSION["guess"];

					$charCheckIndex = strpos($_SESSION["word_orig"], $_SESSION["guess"]);

					if ($charCheckIndex === false) {
						
						// Guess is wrong, try again.
						
						// check if game is finished
						if ($_SESSION["tries_rem"] === 1) {							
							$_SESSION["gameStatus"] = "end";
							$_SESSION["tries_rem"] = 0;
							$_SESSION["resContent"] = self::msgLost();
						} else {
							// Deduct a guess
							$_SESSION["tries_rem"]--;	
							$_SESSION["resContent"] = self::msgTemplate("The word does not contain the letter");
						}						

					} else {
						
						// Guess is correct						

						// Check how many times the letter appears in the word
						$count = str_replace($_SESSION["guess"], $_SESSION["guess"], $_SESSION["word_orig"], $n);
						$offset = 0;

						// Enter character in blank space for each occurance.
						for ($o=0; $o < $n; $o++) { 
							$occurances = strpos($_SESSION["word_orig"], $_SESSION["guess"], $offset);
							// replace index on blanks with correct character.
							$_SESSION["word"][$occurances*2] = $_SESSION["guess"];
							$offset = $occurances+1;
						}

						$_SESSION["resContent"] = self::msgTemplate("The word contains the letter");

						// check is game is finished.
						if (!preg_match("/_/", $_SESSION["word"])) {
							$_SESSION["resContent"] = self::msgWon();
							$_SESSION["gameStatus"] = "end";
						}

					}

				}				

			}		

		}

		// Convert guess history in session to an array.
		private static function guessHistory(){
			return join(str_split($_SESSION["guess_history"]), ", ");
		}

		// response Message templates

		private static function msgTemplate($tmpl){			
			$message = "\r\n";
			$message .= $_SESSION["word"]." (". $_SESSION["word_length"]." letters)\r\n";
			$message .= "_________________________\r\n";
			$message .= $tmpl. " '". $_SESSION["guess"]. "'.\r\n";
			$message .= "Guesses: {". self::guessHistory() ."}\r\n";
			$message .= "Guesses left: ". $_SESSION["tries_rem"];
			return $message;
		}

		private static function msgLost(){
			$message = "Better luck next time!\r\n";
			$message .= "Enter '1' to start a new game.\r\n";
			$message .= "The word is: '". $_SESSION["word_orig"]."'";
			return $message;
		}

		private static function msgWon(){
			$message = "Awesome. You have won! \r\n";
			$message .= "The word is: '". $_SESSION["word_orig"]."'\r\n";
			$message .= "Enter '1' to start a new game.";
			return $message;					
		}

	}

	//////////////////////////////////////////////////////////////////////

	Factory::sanitize();

	// Response to WeChat
	$response = "
		<xml>
			<ToUserName><![CDATA[". $_SESSION["userFrom"] ."]]></ToUserName>
			<FromUserName><![CDATA[". $_SESSION["userTo"] ."]]></FromUserName>
			<CreateTime>". time() ."</CreateTime>
			<MsgType><![CDATA[text]]></MsgType>
			<Content><![CDATA[". $_SESSION["resContent"] ."]]></Content>
			<FuncFlag>0</FuncFlag>
		</xml>
	";

	echo $response;

?>