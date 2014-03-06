<?php
	class chessboardException extends Exception {}
	
	/*
		Exception codes:
		1 - 100: General errors (empty arguments, ...)
			1: Unknown general error
			2: Empty/null argument
			3: Wrong argument type
			4: Invalid argument
	*/
	
	/**
	* A class to load FENs or PGNs and show them on a chessboard
	*/
	
	class chessboard
	{
		private $fen;
		private $pgn;
		private $board; /* 1st dimension: ranks (beginning with 1st rank), 2nd dimension: files (beginning with a-file) */
		private $turn;
		private $castlings; /* Associative array; Keys: K, Q, k, q */
		private $enPassant;
		private $halfMoves; /* number of half-moves since the last pawn move of capture */
		private $moveNumber;
		private $whitePieces = array("P", "N", "B", "R", "Q", "K");
		private $blackPieces = array("p", "n", "b", "r", "q", "k");
		private $moves; /* 1st dimension: half-move number, 2nd dimension: start, target, comment annotation symbol and promotion */
		
		function __construct($pgn = "", $fen = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1")
		{	
			/* load fen and pgn if not null */
			if (!empty($fen))
			{
				$this->loadFen($fen);
			}
			else
			{
				throw new chessboardException("__construct: fen may not be null", 2);
			}
			
			if (!empty($pgn))
			{
				$this->pgn = $pgn;
			}
		}
		
		/**
		* int parseSquare (string square)
		*
		* converts "e4" into 54
		*/
		
		private function parseSquare($square)
		{
			if (strlen((string)$square) == 2)
		    {
		        if (preg_match("/[a-h][1-8]/", (string)$square))
		        {
		            switch (substr($square, 0, 1))
		            {
		                case "a":
		                    $square = "1" . substr($square, 1, 1);
		                    break;
		                case "b":
		                    $square = "2" . substr($square, 1, 1);
		                    break;
		                case "c":
		                    $square = "3" . substr($square, 1, 1);
		                    break;
		                case "d":
		                    $square = "4" . substr($square, 1, 1);
		                    break;
		                case "e":
		                    $square = "5" . substr($square, 1, 1);
		                    break;
		                case "f":
		                    $square = "6" . substr($square, 1, 1);
		                    break;
		                case "g":
		                    $square = "7" . substr($square, 1, 1);
		                    break;
		                case "h":
		                    $square = "8" . substr($square, 1, 1);
		                    break;
		                default:
		                    throw new chessboardException("Function parseSquare: argument square must start with a number or a letter from a to h", 4);
		            }
		        }
		        else
		        {
		            if (!preg_match("/[1-8][1-8]/", (string)$square))
		            {
		                throw new chessboardException("Function parseSquare: argument square must start with a number or a letter from a to h", 4);
		            }
		        }
		    }
		    else
		    {
		        throw new chessboardException("Function parseSquare: square must have a length of two", 4);
		    }
			
			return $square;
		}
		
		/**
		* void loadFen (string fen)
		*
		* Loads a FEN
		*/
		
		function loadFen($fen)
		{
			if (!empty($fen))
			{
				$this->fen = $fen;
				
				/* split fen */
				$sections = explode(" ", $fen);
				
				/* validate fen */
				if (count($sections) == 6)
				{
					if (strpos($sections[0], "K") === FALSE || strpos($sections[0], "k") === FALSE)
					{
						throw new chessboardException("Function loadFen: there must be a black and a white king on the board.", 4);
					}
					/* split position into ranks */
					$ranks = explode("/", $sections[0]);
					
					/* set other values if valid */
					if (($sections[1] == "w") || ($sections[1] == "b"))
					{
						$this->turn = $sections[1];
					}
					else
					{
						throw new chessboardException("Function loadFen: " . $sections[1] . "is not a valid value for active color. Please use 'w' or 'b'.", 4);
					}
					
					/* set possible castlings */
					$this->castlings = array("K" => false, "Q" => false, "k" => false, "q" => false);
					
					if (strpos($sections[2], "K") !== false) /* '!== false' is necessary. See http://php.net/manual/en/function.strpos.php for further information */
					{
						$this->castlings["K"] = true;
					}
					
					if (strpos($sections[2], "Q") !== false)
					{
						$this->castlings["Q"] = true;
					}
					
					if (strpos($sections[2], "k") !== false)
					{
						$this->castlings["k"] = true;
					}
					
					if (strpos($sections[2], "q") !== false)
					{
						$this->castlings["q"] = true;
					}
					
					/* set en passant square */
					if ($sections[3] != "-")
					{
						if (preg_match("/[a-h][36]/", $sections[3])) /* check if a the value is a valid square on the 3rd or 6th rank */
						{
							$this->enPassant = $sections[3];
						}
						else
						{
							throw new chessboardException("Function loadFen: " . $sections[3] . " is not a valid en passant square", 4);
						}
					}
					else
					{
						/* no en passant square */
						$this->enPassant = "";
					}
					
					/* set number of half-moves since the last pawn move of capture */
					if (is_numeric($sections[4]))
					{
						$this->halfMoves = $sections[4];
					}
					else
					{
						throw new chessboardException("Function loadFen: half-moves must be a number", 3);
					}
					
					/* set move-number */
					if (is_numeric($sections[5]))
					{
						$this->moveNumber = $sections[5];
					}
					else
					{
						throw new chessboardException("Function loadFen: move-number must be a number", 3);
					}
					
					$i_rank = 0;
					foreach ($ranks as $rank)
					{
						$i_file = 0;
						$this->board[] = array();
						while (!empty($rank))
						{
							if (is_numeric(substr($rank, 0, 1)))
							{
								$this->board[$i_rank][] = "";
								$new = ((int) substr($rank, 0, 1)) - 1;
								if ($new == 0)
								{
									$new = "";
								}
								$rank = $new . substr($rank, 1);
							}
							else
							{
								$this->board[$i_rank][] = substr($rank, 0, 1);
								$rank = substr($rank, 1);
							}
							
							$i_file++;
						}
						
						$i_rank++;
					}
					$this->board = array_reverse($this->board);
				}
				else
				{
					throw new chessboardException("Function loadFen: fen must have 6 sections", 4);
				}
				
			}
			else
			{
				throw new chessboardException("Function loadFen: Argument fen may not be empty", 2);
			}
		}
		
		/*
		* checks if a move is possible, but ignores if it would leave the king in check and whose turn it is
		*/
		
		function isPossibleMove($start, $target, $board, $castling = FALSE) /* castling is used to prevent a loop */
		{	
			$start = $this->parseSquare($start);
			$target = $this->parseSquare($target);
		    $start = array((int)substr($start, 0, 1) - 1, ((int)substr($start, 1, 1)) - 1);
		    $target = array((int)substr($target, 0, 1) - 1, ((int)substr($target, 1, 1)) - 1);
		    
		    if ($start == $target)  /* Start and target square are the same */
		    {
		        return FALSE;
		    }
		    
			if (in_array($board[$start[1]][$start[0]], $this->whitePieces) && in_array($board[$target[1]][$target[0]], $this->whitePieces)) /* check if a piece of the same color is on the square */
			{
				return FALSE;  
			}
			
			if (in_array($board[$start[1]][$start[0]], $this->blackPieces) && in_array($board[$target[1]][$target[0]], $this->blackPieces)) /* check if a piece of the same color is on the square */
			{
				return FALSE;  
			}
			
		    switch ($board[$start[1]][$start[0]])
		    {
		        case "":
		            return FALSE;
		            
		        case "N": /* knight */
		        case "n":
					if (((abs($start[0] - $target[0]) == 2) && (abs($start[1] - $target[1]) == 1)) || ((abs($start[0] - $target[0]) == 1) && (abs($start[1] - $target[1]) == 2)))
					{
						return TRUE;  

					}
					else
					{
						return FALSE;
					}
		        
		        case "K": /* King */
		            if ((abs($start[0] - $target[0]) == 1 && abs($start[1] - $target[1]) == 1) || (abs($start[0] - $target[0]) == 0 && abs($start[1] - $target[1]) == 1) || (abs($start[0] - $target[0]) == 1 && abs($start[1] - $target[1]) == 0))
					{
						return TRUE;  
					}
					
					if ($start == array(4,0) && $target = array(6,0) && $this->castlings["K"] && $castling && $board[0][7] == "R" && $board[0][5] == "")
					{
				    	foreach ($this->board as $rankNumber => $rank)
            			{
            				foreach ($rank as $fileNumber => $square)
            				{
        						if (in_array($square, $this->blackPieces))
        						{
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "f1", $this->board))
        							{
        								return FALSE;
        							}
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "e1", $this->board))
        							{
        								return FALSE;
        							}
        						}
            				}
            			}
					    return TRUE;
					}
					
					if ($start == array(4,0) && $target = array(2,0) && $this->castlings["Q"] && $castling && $board[0][0] == "R" && $board[0][3] == "")
					{
					    foreach ($this->board as $rankNumber => $rank)
            			{
            				foreach ($rank as $fileNumber => $square)
            				{
        						if (in_array($square, $this->blackPieces))
        						{
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "d1", $this->board))
        							{
        								return FALSE;
        							}
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "e1", $this->board))
        							{
        								return FALSE;
        							}
        						}
            				}
            			}
					    return TRUE;
					}
					
					return FALSE;
		        
		        case "k":    
					if ((abs($start[0] - $target[0]) == 1 && abs($start[1] - $target[1]) == 1) || (abs($start[0] - $target[0]) == 0 && abs($start[1] - $target[1]) == 1) || (abs($start[0] - $target[0]) == 1 && abs($start[1] - $target[1]) == 0))
					{
						return TRUE;  
					}
					if ($start == array(4,7) && $target = array(6,7) && $this->castlings["k"] && $castling && $board[7][7] == "r" && $board[7][5] == "")
					{
				    	foreach ($this->board as $rankNumber => $rank)
            			{
            				foreach ($rank as $fileNumber => $square)
            				{
        						if (in_array($square, $this->whitePieces))
        						{
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "f8", $this->board))
        							{
        								return FALSE;
        							}
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "e8", $this->board))
        							{
        								return FALSE;
        							}
        						}
            				}
            			}
					    return TRUE;
					}
					
					if ($start == array(4,7) && $target = array(2,7) && $this->castlings["q"] && $castling && $board[7][0] == "r" && $board[7][3] == "")
					{
					    foreach ($this->board as $rankNumber => $rank)
            			{
            				foreach ($rank as $fileNumber => $square)
            				{
        						if (in_array($square, $this->whitePieces))
        						{
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "d8", $this->board))
        							{
        								return FALSE;
        							}
        							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), "e8", $this->board))
        							{
        								return FALSE;
        							}
        						}
            				}
            			}
					    return TRUE;
					}
					
					
					return FALSE;
		          
				case "B": /* bishop */
				case "b":	
					if (abs($start[0] - $target[0]) == abs($start[1] - $target[1]))
					{
						$x = $start[0];
						$y = $start[1];
						$change_x = ($target[0] - $start[0]) / abs($start[0] - $target[0]);
						$change_y = ($target[1] - $start[1]) / abs($start[1] - $target[1]);
						$x += $change_x;
						$y += $change_y;
						while (!($x == $target[0] && $y == $target[1]))
						{
							
							if (!empty($board[$y][$x]))
							{
								return FALSE;
							}
							$x += $change_x;
							$y += $change_y;
						}
						
						return TRUE;  

					}
					else
					{
						return FALSE;
					}
					
				case "R": /* rook */
				case "r":	
					if (($start[0] - $target[0] == 0) || ($start[1] - $target[1] == 0))
					{
						$change_x = (($target[0] - $start[0] == 0) ? 0 : ($target[0] - $start[0]) / abs($start[0] - $target[0]));
						$change_y = (($target[1] - $start[1] == 0) ? 0 : ($target[1] - $start[1]) / abs($start[1] - $target[1]));
						$x = $start[0];
						$y = $start[1];
						$x += $change_x;
						$y += $change_y;
						while (!($x == $target[0] && $y == $target[1]))
						{
							if (!empty($board[$y][$x]))
							{
								return FALSE;
							}
							$x += $change_x;
							$y += $change_y;
						}
						return TRUE;
					}
					else
					{
						return FALSE;
					}
				
				case "Q": /* queen */
				case "q":

					if (($start[0] - $target[0] == 0) || ($start[1] - $target[1] == 0))
					{
						$change_x = (($target[0] - $start[0] == 0) ? 0 : ($target[0] - $start[0]) / abs($start[0] - $target[0]));
						$change_y = (($target[1] - $start[1] == 0) ? 0 : ($target[1] - $start[1]) / abs($start[1] - $target[1]));
						$x = $start[0];
						$y = $start[1];
						$x += $change_x;
						$y += $change_y;
						while (!($x == $target[0] && $y == $target[1]))
						{
							if (!empty($board[$y][$x]))
							{
								return FALSE;
							}
							$x += $change_x;
							$y += $change_y;
						}
						return TRUE;	
					}
					if (abs($start[0] - $target[0]) == abs($start[1] - $target[1]))
					{
						$x = $start[0];
						$y = $start[1];
						$change_x = ($target[0] - $start[0]) / abs($start[0] - $target[0]);
						$change_y = ($target[1] - $start[1]) / abs($start[1] - $target[1]);
						$x += $change_x;
						$y += $change_y;
						while (!($x == $target[0] && $y == $target[1]))
						{
							
							if (!empty($board[$y][$x]))
							{
								return FALSE;
							}
							$x += $change_x;
							$y += $change_y;
						}
						return TRUE;
					}
					return FALSE;
					
				case "P": /* white pawn */
					if ($target[1] - $start[1] == 1 && $this->board[$target[1]][$target[0]] == "")
					{
						return TRUE;	
					}
					if ($start[1] == 1 && $target[0] == $start[0] && $target[1] == 3 && $this->board[$target[1]][$target[0]] == "") /* two squares forward if pawn hasn't moved yet */
					{
						return TRUE;
					}
					if (($target[1] - $start[1] == 1 ) && (abs($target[0] - $start[0]) == 1) && (!empty($board[$target[1]][$target[0]])))
					{
						return TRUE;
					}
					if (!empty($this->enPassant) && ($target[1] - $start[1] == 1 ) && (abs($target[0] - $start[0]) == 1) && $this->parseSquare($this->enPassant) == (string)$target[0] . (string)$target[1])
					{
						return TRUE;
					}
					return FALSE;
					
				case "p": /* black pawn */
					if ($target[1] - $start[1] == -1  && $this->board[$target[1]][$target[0]] == "")
					{
						return TRUE;	
					}
					if ($start[1] == 6 && $target[0] == $start[0] && $target[1] == 4 && $this->board[$target[1]][$target[0]] == "") /* two squares forward if pawn hasn't moved yet */
					{
						return TRUE;
					}
					if (($target[1] - $start[1] == -1 ) && (abs($target[0] - $start[0]) == 1) && (!empty($board[$target[1]][$target[0]])))
					{
						return TRUE;
					}
					if (!empty($this->enPassant) && ($target[1] - $start[1] == -1 ) && (abs($target[0] - $start[0]) == 1) && $this->parseSquare($this->enPassant) == (string)$target[0] . (string)$target[1])
					{
						return TRUE;
					}
					return FALSE;	
		    }
		}
		
		function isValidMove($start, $target)
		{	
			if (!$this->isPossibleMove($start, $target, $this->board, TRUE))
			{
				return FALSE;
			}
			
			$start = $this->parseSquare($start);
			$target = $this->parseSquare($target);
			
			
			
		    $start = array((int)substr($start, 0, 1) - 1, ((int)substr($start, 1, 1)) - 1);
		    $target = array((int)substr($target, 0, 1) - 1, ((int)substr($target, 1, 1)) - 1);
			
			if (in_array($this->board[$start[1]][$start[0]], $this->whitePieces) && $this->turn == "b")
			{
				return FALSE;
			}
			if (in_array($this->board[$start[1]][$start[0]], $this->blackPieces) && $this->turn == "w")
			{
				return FALSE;
			}
			
			$board_temp = $this->board;
			$board_temp[$start[1]][$start[0]] = "";
			$board_temp[$target[1]][$target[0]] = $this->board[$start[1]][$start[0]];
			
			foreach ($board_temp as $rankNumber => $rank)
			{
				foreach ($rank as $fileNumber => $square)
				{
					if ($this->turn == "w")
					{
						if ($square == "K")
						{
							$kingPosition = ($fileNumber + 1) . ($rankNumber + 1);
						}
					}
					if ($this->turn == "b")
					{
						if ($square == "k")
						{
							$kingPosition = ($fileNumber + 1) . ($rankNumber + 1);
						}
					}
				}
				
			}
			
			foreach ($board_temp as $rankNumber => $rank)
			{
				foreach ($rank as $fileNumber => $square)
				{
					if ($this->turn == "w")
					{
						if (in_array($square, $this->blackPieces))
						{
							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), $kingPosition, $board_temp))
							{
								return FALSE;
							}
						}
					}
					if ($this->turn == "b")
					{
						if (in_array($square, $this->whitePieces))
						{
							if ($this->isPossibleMove(($fileNumber + 1) . ($rankNumber + 1), $kingPosition, $board_temp))
							{
								return FALSE;
							}
						}
					}
				}
			}
			
			return TRUE;
		}
		
		function doMove($start, $target)
		{
			if (!$this->isValidMove($start, $target))
			{
				throw new chessboardException("function doMove: $start - $target isn't a legal move.", 4);
			}
			
			$start = $this->parseSquare($start);
			$target = $this->parseSquare($target);
		    $start = array((int)substr($start, 0, 1) - 1, ((int)substr($start, 1, 1)) - 1);
		    $target = array((int)substr($target, 0, 1) - 1, ((int)substr($target, 1, 1)) - 1);
			
			
			switch ($this->board[$start[1]][$start[0]])
		    {
		        case "K": /* King */
					if ($start == array(4,0) && $target == array(6,0) && $this->castlings["K"] && $this->board[0][7] == "R")
					{
						$this->board[0][5] = "R";
						$this->board[0][7] = "";
					}
					
					if ($start == array(4,0) && $target == array(2,0) && $this->castlings["Q"] && $this->board[0][0] == "R")
					{
					    $this->board[0][3] = "R";
						$this->board[0][0] = "";
					}
					
					break;
		        
		        case "k":    
					if ($start == array(4,7) && $target == array(6,7) && $this->castlings["k"] && $this->board[7][7] == "r")
					{
				    	$this->board[7][5] = "R";
						$this->board[7][7] = "";
					}
					
					if ($start == array(4,7) && $target == array(2,7) && $this->castlings["q"] && $this->board[7][0] == "r")
					{
						$this->board[7][3] = "R";
						$this->board[7][0] = "";
					}
					
					break;
		          
				case "P": /* white pawn */
					if (empty($this->enPassant))
					{
						break;
					}
					if ($this->parseSquare($this->enPassant) == (string)$target[0] . (string)$target[1])
					{
						$this->board[$target[1] - 1][$target[0]] = "";
					}
					break;
				case "p": /* black pawn */
					if (empty($this->enPassant))
					{
						break;
					}
					if ($this->parseSquare($this->enPassant) == (string)$target[0] . (string)$target[1])
					{
						$this->board[$target[1] + 1][$target[0]] = "";
					}	
					break;
		    }
			$this->board[$target[1]][$target[0]] = $this->board[$start[1]][$start[0]];
			$this->board[$start[1]][$start[0]] = "";
			
			$this->turn = (($this->turn == "w") ? "b" : "w"); /* change the right to move */
		}
		
		
		/**
		* string getHTML ()
		*
		* Returns HTML-Code for the board
		*
		* only for testing
		*/
		
		function getHTML()
		{
			$return = "";
			foreach (array_reverse($this->board) as $rank)
			{
			    $return .= "<div class='rank'>";
				foreach ($rank as $square)
				{
					$return .= "<div class='square'>";
					$return .= $square;
					$return .= "</div>";
				}
				$return .= "</div>";
			}
			return $return;
		}
		
	}
?>
