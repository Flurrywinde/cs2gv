<?php
require('cs2gv-class.php');

// GLOBALS
//
$debug = false;
$stat2track = $_POST['stat'];
$orig_cs = ''; // Raw text of the cs code to convert
//$orig_cs_array = array(); // Array of lines			No longer doing this way. Del this line and next.
//$stage1_data = array(); // An array of cs objects of the data in $orig_cs after first pass of processing.

// THE CODE
//

// If form data sent, process it. Otherwise skip to HTML below.
if ($_POST['changefile']) {
	echo "<h1>kk</h1>";
	// Set $orig_cs to contain posted choicescript text
	if(strlen($_POST['cs']) > 0) {
		$orig_cs = stripslashes($_POST['cs']);
		//echo $orig_cs;
	} else break; // User entered an empty textarea for the code. Do nothing and just present the textarea again.

	// Parse $orig_cs into in array of cs elements.
	$objCS = new classBlock($orig_cs, 1, 0); // Linenum = 1 and indent level = 0
	$objCS->proc_block(); // A whole scene can be considered a single block of cs code
	$print_r_cs = print_r($objCS->block, true);
	//echo $print_r_cs;
	$objCS2GV = new cs2gv('');
	$objCS2GV->block = $objCS->block;
	$objCS2GV->makecsgv($objCS2GV->block); // Should rewrite code to use class csgv instead of classBlock in the lines above. For now, this is a workaround.
	$objCS2GV->makeVars($objCS2GV->gvblock[0], '', array());
	$csgv = print_r($objCS2GV->gvblock, true);
	$csgv = $objCS2GV->makeGV()."\n\n".$csgv;

	// $csD3 is json for D3's nest() function.
	$csD3 = $objCS2GV->makeNestedjson();
}

/* <script type="text/javascript">
	//alert('hi');
	//svg = Viz(document.getElementById('gcode').innerHTML, 'svg');
	//alert(document.getElementById('gcode').innerHTML);
	//alert(svg);
	//document.body.innerHTML += svg;
</script>*/
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>ChoiceScript Parser and GraphVis Code Production</title>
<script type="text/vnd.graphviz" id="gcode">
<?php echo $csgv; ?>
</script>
<script type="text/javascript" src="viz.js"></script>
  </head>
<body>
<script>document.body.innerHTML += Viz(document.getElementById('gcode').innerHTML, 'svg');</script>
<h1>ChoiceScript Parser and GraphVis Code Production</h1>
<form action="cs2gv.php" method="post">
<div style="width: 100%; display: table;">
	<div style="display: table-row">
		<div style="display: table-cell; background-color:black; padding:10px;">
			<p style="color:white;">INPUT: ChoiceScript Scene Text</p>
			<textarea style="width: 375px; height: 600px;" name="cs"><?php echo $orig_cs; ?></textarea>
			<p style="color:white;">Stat to track: <input type="text" name="stat"></p>
		</div>
		<div style="display: table-cell; background-color:green; padding:10px;">
			<p style="color:white;">OUTPUT: Array of Objects</p>
			<textarea style="width: 375px; height: 600px;" name="print_r"><?php echo $print_r_cs; ?></textarea>
			<p style="color:white;">This is just a print_r() of the array containing all the parsed CS code.</p> 
		</div>
		<div style="display: table-cell; background-color:blue; padding:10px;">
			<p style="color:white;">OUTPUT: GraphVis Code</p>
			<textarea style="width: 375px; height: 600px;" name="csgv"><?php echo $csgv; ?></textarea>
			<p style="color:white;">Copy-paste this into a GraphVis viewer, such as the one <a href="http://sandbox.kidstrythisathome.com/erdos/">here</a> or <a href="http://www.webgraphviz.com/">here</a> to see your CS code in a graphical tree.</p> 
		</div>
		<div style="display: table-cell; background-color:blue; padding:10px;">
			<p style="color:white;">OUTPUT: D3 JSON Code</p>
			<textarea style="width: 400px; height: 600px;" name="csD3json"><?php echo $csD3; ?></textarea>
			<p style="color:white;">JSON code to be further processed by d3.nest() in another program.</p> 
		</div>
	</div>
</div>
<div style="width:100%; padding: 15px; align: center; background-color:red;">
		<p style="color:white; align:center;"><input type="submit" value="Submit" name="changefile"></p>
</div>
</form>
<div style="width: 100%; display: table;">
	<div style="display: table-row">
		<div style="display: table-cell; background-color:black; padding:10px;">
			<p style="color:white;">CODE: cs2gv.php</p>
			<textarea style="width: 400px; height: 600px;" name="cs"><?php echo htmlentities(file_get_contents('cs2gv.php')); ?></textarea>
		</div>
		<div style="display: table-cell; background-color:green; padding:10px;">
			<p style="color:white;">CODE: cs2gv-class.php</p>
			<textarea style="width: 400px; height: 600px;" name="print_r"><?php echo htmlentities(file_get_contents('cs2gv-class.php')); ?></textarea>
		</div>
	</div>
</div>
</body>
</html>

