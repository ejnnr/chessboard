<?php
	error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Chessboard - Test</title>
		<meta charset="utf8">
		<style>
			* {
				margin: 0;
				padding: 0;
			}
			.rank {
				clear: both;
			}
			.square {
				width: 50px;
				height: 50px;
				float: left;
				font-size: 50px;
				border: 1px solid black;
			}
		</style>
	</head>
	<body>
		<?php
			include "chessboard.class.php";
			$board1 = new chessboard("", "8/3k4/3r4/8/3B1n2/8/3K4/8 w - - 0 1");
			echo $board1->getHTML();
			var_dump($board1->isValidMove("d4", "c3"));
		?>
	</body>
</html>