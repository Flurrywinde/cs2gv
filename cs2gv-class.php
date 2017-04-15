
<?php 
class classBase {
// Serves as a base class for other classes that contain CS code elements.
	const BLOCK = 0, STORYTEXT = 1, LABEL = 2, cGOTO = 3;
	const CHOICE = 4, OPTION = 5;
	const FINISH = 28, COMMENT = 6, CREATE = 7, TEMP = 8, SET = 9;
	const cIF = 10, cELSE = 11, cELSEIF = 12;
	const ENDING = 13, FAKE_CHOICE = 14, GOTO_SCENE = 15;
	const INPUT_TEXT = 16, INPUT_NUMBER = 17, IMAGE = 18;
	const LINE_BREAK = 19, PAGE_BREAK = 20, RAND = 21, STAT_CHART = 22;
	const ALLOW_REUSE = 23, DISABLE_REUSE = 24, SELECTABLE_IF = 25;
	const ACHIEVEMENT = 26, ACHIEVE = 27, UNKNOWN = 100, BASE = 101;

	public $type = BASE;	// The type of the CS elt. An elt may be a single line command, multiple line command, or a block of commands.
	public $rawcode;		// The raw CS code. May be a single line or multiple.
	public $linenum; 		// Line number of the CS elt (first line of a block if the elt is a block).
	public $indent_level;	// The indentation level of $rawcode's first line. Set this to 0 if calling proc_block() on a scene .txt file. Otherwise, it's set automatically as the code is processed. 
}

class classBlock extends classBase {
	public $block = array();

	public function add_cselt($cselt) {
		array_push($this->block, $cselt);
	}
	
	public function proc_block() {
		// Processes $this->rawcode line by line.
		// Sets $this->block to an array of the CS code elements in $this->rawcode. Nested blocks will also have been recursively parsed by proc_block().
	
		$block_lines = explode("\n", $this->rawcode);
		
		// Add next block of cs code to $this->block. proc_line() may array_shift() more lines off $block_lines.
		while($line = array_shift($block_lines)) {
			$first_line_num = $this->linenum;
			$obj = $this->proc_line($line, $block_lines);
			if($obj === false) continue; // Don't need to process a spacer line further... (Could $obj be false in other cases?)
			$obj->linenum = $first_line_num;
			$this->add_cselt($obj);
			if($debug) echo $line."<br>\n";
			if($debug) "[$first_line_num]Type: ".$obj->type.", Body: \n".$obj->rawcode."<br>\n"; // Instead of showing rawcode, should show the object data, but this is just for debugging anyway and will be removed.
			$this->linenum++;
		}
	}
	
	private function proc_line($line, &$block_lines) {
	// Check for indention error. If okay, make appropriate object and fill its properties. 
		
		// Determine indent level
		$indent_level = $this->get_indent($line);
		if(($indent_level != $this->indent_level) &&
		(strlen(trim($line)) > 0)) {
			//print_r($this);
			die('Indent error'); // Blank lines can have whitespace without triggering an error.
		}
		//if($debug) "Indent level: $indent_level<BR>\n";
		
		// Get first token in $line
		$line_array = explode(' ', trim($line));
		$first_token = array_shift($line_array);
		//if($debug) 'FIRST: '.$first_token."<BR>\n";
		
		// Look at $first_token to determine what the CS code is and make an appropriate object.
		if(strlen($first_token) == 0) {
			// Empty line. Just skip.
			if($debug) "SPACER<br>\n";
			return false;
		} elseif($first_token[0] != '*') {
			// Not a command, so is text
			$ret = new storytext($line, $this->linenum, $indent_level);
			$ret->text = $line;
			$lines_parsed = $this->get_rest_of_storytext($ret, $block_lines);
			$this->linenum += $lines_parsed;
			return $ret;
		} elseif($first_token == '*comment') {
			// A comment
			$ret = new comment($line, $this->linenum, $indent_level);
			$ret->comment = implode(' ', $line_array);
			return $ret;
		} elseif($first_token == '*label') {
			// A label
			$ret = new label($line, $this->linenum, $indent_level);
			$ret->label = implode(' ', $line_array);
			return $ret;
		} elseif($first_token == '*goto') {
			// A goto
			$ret = new class_goto($line, $this->linenum, $indent_level);
			$ret->label = implode(' ', $line_array);
			return $ret;
		} elseif($first_token == '*choice') {
			// A choice
			$ret = new choice($line, $this->linenum, $indent_level);
			// *choice can have parameters, but ignore. Add support later.
			$lines_parsed = $this->get_options($ret, $block_lines);
			$this->linenum += $lines_parsed;
			return $ret;
		} elseif(in_array($first_token, array('*set', '*temp', '*create'))) {
			// A set
			$ret = new class_set($line, $line_array[0], implode(' ', array_slice($line_array, 1)), $this->linenum, $indent_level);
			//$ret->expr = implode(' ', $line_array);
			return $ret;
		} elseif($first_token == '*if') {
			// An if
			$ret = new class_if($line, $this->linenum, $indent_level);
			$ret->expr = implode(' ', $line_array)."\n";
			$lines_parsed = $this->get_subblock($ret, $block_lines);
			$this->linenum += $lines_parsed;
			return $ret;
		} elseif($first_token == '*elseif') {
			// An elseif
			$ret = new class_elseif($line, $this->linenum, $indent_level);
			$ret->expr = implode(' ', $line_array)."\n";
			$lines_parsed = $this->get_subblock($ret, $block_lines);
			$this->linenum += $lines_parsed;
			return $ret;
		} elseif($first_token == '*else') {
			// An else
			$ret = new class_else($line, $this->linenum, $indent_level);
			$lines_parsed = $this->get_subblock($ret, $block_lines);
			$this->linenum += $lines_parsed;
			return $ret;
		} elseif($first_token == '*finish') {
			// A finish
			$ret = new finish($line, $this->linenum, $indent_level);
			$ret->label = implode(' ', $line_array);
			return $ret;
		} elseif($first_token == '*ending') {
			// A goto
			$ret = new ending($line, $this->linenum, $indent_level);
			$ret->label = implode(' ', $line_array);
			return $ret;
		} elseif($first_token == '*goto_scene') {
			// A goto
			$ret = new goto_scene($line, $this->linenum, $indent_level);
			$ret->label = implode(' ', $line_array);
			return $ret;
		} else {
			// Unknown (to this code at least) command
			$ret = new unknown($line, $this->linenum, $indent_level);
			array_unshift($line_array, $first_token);
			$ret->suffix = implode(' ', $line_array)."\n";
			$lines_parsed = $this->get_subblock($ret, $block_lines);
			$this->linenum += $lines_parsed;
			return $ret;
		}
	}
	
	private function get_rest_of_storytext($obj, &$block_lines) {
		$linecount = 0;
		
		// Keep getting lines until a command is found
		while($line = array_shift($block_lines)) {
			$trimmedline = trim($line); // Ignore indent level. The real cs interpreter doesn't do this, but it should be okay here.
			if($trimmedline[0] != '*') {
				// Not a command, so is more storytext
				$obj->rawcode .= $trimmedline."\n";
				$obj->text .= $trimmedline."\n";
				$linecount++;
			} else {
				// End of block. Put last line back on and return the cs object.
				array_unshift($block_lines, $line);
				return $linecount;
			}
		}
		// Reached end of $block_lines (either end of the scene .txt file or current block)
		return $linecount;
	}

	private function get_subblock($obj, &$block_lines) {
		$linecount = 0;		// count of lines in potential subblock including spacer lines
		$blinecount = 0;	// Not including spacer lines. If this is > 0, we have a block, else we don't.
		$subblock_code = '';	// Raw code of the subblock. Can't use $obj->rawcode because that includes the parent command (e.g. the *if line, etc).  
		$expected_indent_level = $obj->indent_level + 1;
		$subblock_indent_level = -1; // -1 means hasn't been set yet. This is to get the indent level of the first line.
		while($line = array_shift($block_lines)) {
			$actual_indent_level = $this->get_indent($line);
			if($debug) "'$line' at $actual_indent_level (expected: $expected_indent_level)\n";
			if($actual_indent_level >= $expected_indent_level) { // should make sure first indent is equal, but this is easier. Will get all sub-subblocks in one fell swoop this way.
				// Keep getting indented lines
				$obj->rawcode .= $line."\n";
				$subblock_code .= $line."\n";
				$linecount++;
				$blinecount++;
				if($subblock_indent_level == -1) $subblock_indent_level = $actual_indent_level;
			} elseif(strlen(trim($line)) == 0) {
				// Keep spacer lines too
				$obj->rawcode .= "\n";
				$subblock_code .= "\n";
				$linecount++;
			} elseif($actual_indent_level == $expected_indent_level-1) {
				// End of block. Put last line back on and return the cs object.
				// Doesn't have to be -1? Could be -2 or more? 
				array_unshift($block_lines, $line);
				break;
			} else {
				array_unshift($block_lines, $line);
				break;
				/* Actually, this is ok. if($debug) "Died on '$line'\n";
				die ('Indent level (probably) less than expected in function get_subblock().'); */
			}
		}
		// Reached end of $block_lines (either end of the scene .txt file or current block)
		
		// Recursively call proc_block() on rawcode
		if($blinecount > 0) {
			$new_block = new classBlock(rtrim($subblock_code), $linecount, $subblock_indent_level);
			$obj->block = $new_block;
			$new_block->proc_block();
		}
		return $linecount;
	}
	
	private function get_options($obj, &$block_lines) {
		$linecount = 0;		// count of lines (really options)
		$expected_indent_level = $obj->indent_level + 1;
		if($debug) "ASDF\n";
		while($line = array_shift($block_lines)) {
			$choicetext = trim($line);  // Get rid of indent
			$actual_indent_level = $this->get_indent($line);
			if($debug) "'$line' at $actual_indent_level (expected: $expected_indent_level)\n";
			if($actual_indent_level >= $expected_indent_level) { // should make sure first indent is equal, but this is easier. Will get all sub-subblocks in one fell swoop this way.
				// Just get rid of these prefixes for now
				if(strpos($choicetext,'*if') == 0                   ||
						strpos($choicetext, '*selectable_if' == 0)  ||
						strpos($choicetext, '*hide_reuse' == 0)     ||
						strpos($choicetext, '*allow_reuse' == 0)) {
							$choicetext = strstr($choicetext, '#');
				}
				// Process the option.
				if(substr($choicetext,0,1) == '#') {
					// We found an option, just like we're supposed to.
					// Run get_subblock() on it.
					$new_option = new option($line, $this->linenum, $actual_indent_level);
					$new_option->text = substr($line, 1); // Chop off the '#'
					$obj->add_option($new_option);
					$lines_parsed = $this->get_subblock($new_option, $block_lines);
					$this->linenum += $lines_parsed;
					$linecount++;
				} else {
					die('Found a non-#-option in a *choice block: '.$choicetext);
				}
			} elseif(strlen(trim($line)) == 0) {
				// Keep spacer lines too
				$obj->rawcode .= "\n";
				$linecount++;
			} elseif($actual_indent_level == $expected_indent_level-1) {
				// End of block. Put last line back on and return the cs object.
				// It did happen. I guess after get_subblock(), it comes back here and checks it again. die("I don't think this will ever happen. get_subblock() finishes a choice body.");
				array_unshift($block_lines, $line);
				break;
			} else {
				array_unshift($block_lines, $line);
				break;
				/* Actually, this is ok. if($debug) "Died on '$line'\n";
								die ('Indent level (probably) less than expected in function get_options().');*/
			}
		}
		// Reached end of $block_lines (either end of the scene .txt file or current block)
		if($debug) "ENDASDF\n";
		return $linecount;
	}
	
	private function get_indent($line) {
		for($i=0; $i<strlen($line); $i++) {
			if(substr($line, $i, 1) == "\t") {
				//if($debug) "found a tab<br>\n";
			} else break;
		}
		return $i;
	}
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = BLOCK;
		$this->rawcode = $rawcode;
		$this->linenum = $linenum;
		$this->indent_level = $indent_level;
	}
}

class storytext extends classBase {
	public $text;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = STORYTEXT;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class comment extends classBase {
	public $comment;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = COMMENT;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class label extends classBase {
	public $label;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = LABEL;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class class_goto extends classBase {
	public $label;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = cGOTO;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class class_if extends classBase {
	public $expr;
	public $block;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = cIF;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class class_elseif extends classBase {
	public $expr;
	public $block;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = cELSEIF;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class class_else extends classBase {
	public $block;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = cELSE;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class class_set extends classBase {
	public $var;
	public $expr;
	
	function __construct($rawcode, $var, $expr, $linenum = 0, $indent_level = 0) {
		$this->type = SET;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
		$this->var = $var;
		$this->expr = $expr;
	}
}

class goto_scene extends classBase {
	public $scene;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = GOTO_SCENE;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class image extends classBase {
	public $image;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = IMAGE;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class line_break extends classBase {
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = LINE_BREAK;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class page_break extends classBase {
	public $text;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = PAGE_BREAK;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class finish extends classBase {
	public $text;
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = FINISH;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class ending extends classBase {
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = ENDING;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class option extends classBase {
	public $text; // The text that's shown to the reader.
	public $block; // A csblock object containing the option's code.
	public $prefix; // String holding *selectable_if, *if, *hide_reuse, etc. Probably will just be ignored for making GV code.
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = OPTION;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class choice extends classBase {
	public $options = array(); // Array of class option elements
	
	public function add_option($option) {
		array_push($this->options, $option);
	}
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = CHOICE;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class unknown extends classBase {
	public $suffix, $block; // Unknown commands may have both or neither. $suffix is what comes after the * command.
	
	function __construct($rawcode, $linenum = 0, $indent_level = 0) {
		$this->type = UNKNOWN;
		$this->linenum = $linenum;
		$this->rawcode = $rawcode;
		$this->indent_level = $indent_level;
	}
}

class gvcs {
    const PAGE = 0, COND = 1, FINISH = 2; // Constants for $this->type. A "PAGE" means what appears on a page in a cs game, i.e. text, *set's, etc, followed by a *choice, *page_break, etc. A *goto is also considered something that will end a page for graphviz purposes, even though it will appear on the same page in an actual game.
    
    // Parms to class gvcs's set_text() method to control text formatting:
    public $page_rows = 3; // How many rows of text to show on the tree node
    public $linelen = 30;	// How long each line (row) is
    
    public $use_html = true;
    public $label_font_size = 10;
    public $label_color = 'blue';
    public $body_font_size = 7;
    
    public $type;   
    public $label;
    public $show_label = true; // Don't show the label if it's implicit, i.e. it's not in the actual code but generated just for graphviz purposes.
    public $text;
    public $sets = array();
    public $edges = array();
    public $vars = array();
    
    private function truncate($string, $length = 100, $append = "...")
    {
        if (strlen($string) <= intval($length)) {
            return $string;
        }

        return substr($string, 0, $length) . $append;
    }
    
    public function truncwrap($string, $wholelen = 150, $linelen = 50, $append = '...') {
	// Removes all newlines from string, truncates it to $wholelen length, then adds <BR /> at as close to $linelen intervals but preserving whole words, and finally appends '...' if $string was longer than $wholelen.
	return wordwrap($this->truncate(str_replace(array("\n", "\r"), ' ', $string), $wholelen, $append), $linelen, '<BR />', true);
    }
    
    public function set_text($text) {
	// Sets $this->text to $text, first formatting it by $this->type.
	// Requires $this->label to have been set already. Should check for this error.
	//echo "text: $text<br>";
	if($this->type != PAGE) die('set_text() called on a gvcs object that was not of type PAGE.');
        //$tlabel = $this->truncate($this->label, 10);
	$tlabel = $this->label; // For debugging. Put back previous line.
	$fbody = $this->truncwrap($text, $this->linelen * $this->page_rows - strlen($tlabel), $this->linelen);

	if($this->use_html) {
	    //$ret = '<'; calling function now responsible for this
	    //if($this->show_label) $ret .= '<FONT COLOR="'.$this->label_color.'" POINT-SIZE="'.$this->label_font_size.'">'.$tlabel.': </FONT>';
	    if(true) $ret .= '<FONT COLOR="'.$this->label_color.'" POINT-SIZE="'.$this->label_font_size.'">'.$tlabel.': </FONT>'; // For debugging, always show label. Put back previous line.
	    
	    $ret .= '<FONT POINT-SIZE="'.$this->body_font_size.'">  '.$fbody.'</FONT>';
	    $this->text = $ret;
	} else {
	    //$ret = '"'; calling function now responsible for this
	    //if($this->show_label) $ret .= $tlabel.': ';
	    //$ret .= $fbody.'"';
	    $ret .= $tlabel;
	    $this->text = $ret;
	}
    }
    
    public function addedge($text, $parent, $child, $sets = array()) {
	$new_edge = new edge($text, $parent, $child, $sets);
	
	array_push($this->edges, $new_edge);
    }
    
    public function addset($type, $var, $opr) {
	$new_set = new classSet($type, $var, $opr);
	
	array_push($this->sets, $new_set);
    }
    
}

class classSet {
    const CREATE = 0, TEMP = 1, SET = 2;  // Types for $type below.
    
    public $type; 
    public $var;
    public $opr; // Operation to perform, such as %+10
    
    function __construct($type, $var, $opr) {
	$this->type = $type;
	$this->var = $var;
	$this->opr = $opr;
    }
}

class edge {
    public $text;
    public $parent;
    public $child;
    public $sets;
    
    function __construct($text, $parent, $child, $sets = array()) {
	$this->text = $text;
	$this->parent = $parent;
	$this->child = $child;
	$this->sets = $sets;
    }
}

class varVal {
    public $value;
    public $path = array();
}

class cs2gv extends classBlock {
    public $gvblock = array(); // This is $block (which is the array of cs elements) after it's been processed to turn it into a form more friendly for gv, i.e. reducing nodes by combining *set's and storytext together, etc.
    private $numifs = 0; // Increment this each time a COND is added to $gvblock. It's purpose is to be appended to the implicitly created COND labels to ensure uniqueness.
    private $num_unreachableQ = 0; // I think this can only happen when a storytext is right after a goto or something like that, making it unreachable. I want the diagram to show it correctly, though, so I will increment this after appending it to $implicit_label.
    private $implicit_label = 'START'; // $implicit_label is the label of the cselt if it's not explicitly given one in the cs code. For example, the first page of a story often has text without a preceeding label. Give this page an implicit label of 'START'. A page in an *if block or *choice option is another example of pages that need implicit labels.
    private $last_if; // Hack. Use this to make else not need its own node (go from previous elseif (or if)'s false edge direct to else's block). Just reuse this for later if sections. Also, use this to check for the fallout.
    private $labelkeys = array(); // Hack. Use make_labelkeys() to fill this in with an array mapping a node's label with the corresponding key in $gvblock.
    
    public function makecsgv($block) {
	// Processes $block (which contains cs objects), filling in $gvblock with gvcs objects, which are objects that can be converted into graphviz code.
	if(!is_array($block)) die('$block parm is not an array.');
	//reset($block);
	//if(count($block) == 1) {echo print_r($block, true)."<BR><BR>"; $debug1 = true;}
	//if($debug1) echo 'kanon';
	if(count($block) == 1 && $block[0]->type == cGOTO) {
	    // Assume block is an option block of just a goto, which should not be processed, lest it create a duplicate orphan edge with an implicit label pointing to the same place as the edge already created in proc_choices().
	    return;
	}
	//if($debug1) echo 'kanon';
        while($cselt = array_shift($block)) {
	    //if($debug1) {echo 'asdf'; print_r($cselt); echo "<BR><BR>\n\n";} 
	    //if(strpos($cselt->text, 'go to bed')) {print_r($block); echo "<BR><BR>"; print_r($cselt); }
	    //echo 'Elts left: '.count($block)."<BR>\n";
	    //echo $this->showeach($block);
	    $this->proc_cselt($block, $cselt); 
        }
    }
    
    private function showeach($array) {
	// Just a debugging function. Delete later.
	for($i=0;$i<count($array);$i++) {
	    echo $array[$i]->text.' '.$array[$i]->label.', ';
	}
	echo "<BR>\n";
    }
    
    private function proc_cselt(&$block, $cselt) {
	// Puts $cselt into a new gvcs object, $gvelt, then looks at the next cs elt (by calling get_rest_of_page()) until a branch is added. IOW, a branch (e.g. *goto and *choice) completes a gvcs object. If $gvelt is completed here, push it onto $gvblock. Otherwise, call the function that gets the rest of the cs elts and then pushes it onto $gvblock. Reason it can't all be done in this one function: the first object ($cselt) is a special case. If it's a label, that becomes the label of $gvelt, but after the first object, a label indicates a new gvelt. Also, if there's no label, this function creates an implicit one.
	
	//if(strpos($cselt->text, 'go to bed')) {print_r($block); echo "<BR><BR>"; print_r($cselt); die;}
	//if(count($block)==0) print_r($cselt);
	
	$gvelt = new gvcs;
	//echo 'proc_cselt - type: '.$cselt->type.', text: '.$cselt->text."<BR>\n";
	if($cselt->type == STORYTEXT) {
	    $gvelt->type = PAGE;
	    $gvelt->text = $cselt->text;
	    $gvelt->label = $this->implicit_label;
	    $gvelt->show_label = false; // If first elt is already storytext, it has no label.
	    $this->get_rest_of_page($block, $gvelt);
	} elseif($cselt->type == UNKNOWN || $cselt->type == COMMENT) {
	    // Ignore the unknown cs command or comment
	    //$gvelt->label = $this->implicit_label; Don't think need this and next line
	    //$this->get_rest_of_page($block, $gvelt);
	} elseif($cselt->type == LABEL) {
	    $gvelt->type = PAGE;
	    $gvelt->label = $cselt->label;
	    $this->implicit_label = $cselt->label;
	    $this->get_rest_of_page($block, $gvelt);
	} elseif($cselt->type == SET) {
	    $gvelt->type = PAGE;
	    $gvelt->label = $this->implicit_label;
	    $gvelt->addset($cselt->type, $cselt->var, $cselt->expr);
	    $this->get_rest_of_page($block, $gvelt);
	} elseif($cselt->type == cGOTO) {
	    // A *goto as the start of a page means the page is empty, is just an edge. This can happen at the start of a scene or the top of an *if or #option block.
	    $gvelt->type = PAGE;
	    $gvelt->label = $this->implicit_label;
	    //print_r($this);echo 'adf'; print_r($cselt);echo 'endadf';
	    $gvelt->addedge('', $this->implicit_label, $cselt->label);
	    //$this->implicit_label .= $this->num_unreachableQ++;
	    array_push($this->gvblock, $gvelt);
	} elseif($cselt->type == CHOICE) {
	    $gvelt->type = PAGE;
	    $gvelt->label = $this->implicit_label.'CHOICE';
	    $gvelt->text = 'Orphan *choice';
	    $this->proc_choice($block, $gvelt, $cselt);
	} elseif($cselt->type == cIF) {
	    $gvelt->type = COND;
	    $gvelt->label = $this->implicit_label.preg_replace('/\s+/','',$cselt->expr);;
	    $gvelt->text = $cselt->expr;
	    $this->proc_if($block, $gvelt, $cselt);
	} elseif($cselt->type == FINISH) {
	    $gvelt->type = FINISH;
	    $gvelt->label = $gvelt->type;
	    $gvelt->show_label = true;
	    array_push($this->gvblock, $gvelt); // Should check and not do it if there's already one
	} elseif($cselt->type == ENDING) {
	    $gvelt->type = ENDING;
	    $gvelt->label = 'END';
	    $gvelt->show_label = true;
	    array_push($this->gvblock, $gvelt); // Should check and not do it if there's already one
	} elseif($cselt->type == GOTO_SCENE) {
	    $gvelt->type = GOTO_SCENE;
	    $gvelt->label = 'GOTO SCENE '.$cselt->label;
	    $gvelt->text = $gvelt->type."\n".$cselt->label;
	    $gvelt->show_label = false;
	    array_push($this->gvblock, $gvelt); // Should check and not do it if there's already one
	} else {
	    print_r($cselt);
	    die('Unknown type (or else or elseif without an if) found in $cselt in function proc_cselt. Should never happen.');
	}
    }
    
    private function get_rest_of_page(&$block, $gvelt) {
	// Called if proc_cselt() determines the current $gv_elt is a PAGE. A PAGE can be composed of many STORYTEXTs, variable SETs (or creation), etc. UNKNOWNs are skipped. A cGOTO, cIF, FINISH, ENDING, GOTO_SCENE, LABEL, or CHOICE terminates it (but may require processing by another function before pushing $gvelt onto $gvblock).
	while($cselt = array_shift($block)) {
	    //echo 'get_rest_of_page - type: '.$cselt->type."<BR>\n";
	    //print_r($cselt);
	    if($cselt->type == STORYTEXT) {
		$gvelt->text .= $cselt->text;
	    } elseif($cselt->type == UNKNOWN || $cselt->type == COMMENT) {
		// Ignore the unknown cs command or comment. 
	    } elseif($cselt->type == LABEL) {
		$gvelt->addedge('', $gvelt->label, $cselt->label);
		array_push($this->gvblock, $gvelt);
		array_unshift($block, $cselt);
		return;
	    } elseif($cselt->type == cGOTO) {
		$gvelt->addedge('', $gvelt->label, $cselt->label);
		array_push($this->gvblock, $gvelt);
		return;
	    } elseif($cselt->type == CHOICE) {
		$this->proc_choice($block, $gvelt, $cselt);
		array_push($this->gvblock, $gvelt);
		return;
	    } elseif(in_array($cselt->type, array(SET, CREATE, TEMP))) {
		$gvelt->addset($cselt->type, $cselt->var, $cselt->expr);
	    } elseif($cselt->type == cIF) {
		$if_label = $gvelt->label.'IF'.preg_replace('/\s+/','',$cselt->expr);
		$this->implicit_label = $gvelt->label.'IF';
		$gvelt->addedge('', $gvelt->label, $if_label);
		array_push($this->gvblock, $gvelt);
		array_unshift($block, $cselt);
		return;
	    } elseif(in_array($cselt->type, array(FINISH, ENDING, GOTO_SCENE))) {
		//print_r($block);
		$child_label = $cselt->type;
		if($cselt->type == GOTO_SCENE) $child_label .= $cselt->label;
		$gvelt->addedge('', $gvelt->label, $child_label);
		array_push($this->gvblock, $gvelt);
		array_unshift($block, $cselt);
		//print_r($block);
		return;
	    } else die('Unknown type found in $cselt in function get_rest_of_page. Should never happen.');
	}
	// Hit end of $block array. Push the rest of what we got.
	array_push($this->gvblock, $gvelt);
    }
    
    private function proc_choice(&$block, $gvelt, $cselt) {
	$option_num = 0; // Append and increment this to make a unique implicit label for each option block. $gvelt->label must be set already.
	foreach($cselt->options as $option) {
	    $this->implicit_label = $gvelt->label.'OPTION'.$option_num++;
	    //print_r($option);
	    if($option->block->block[0]->type == LABEL) {
		// If the very first element in the option block is a label, use that as the label. 
		$label = $option->block->block[0]->label;
	    } elseif($option->block->block[0]->type == cGOTO) {
		// If the very first element in the option block is a goto, use that as the label. 
		$label = $option->block->block[0]->label;
	    } else {
		// If neither is the case, create an implicit one based off $this->implicit_label;
		$label = $this->implicit_label;
	    }
	    if(!is_array($option->block->block)) die('proc_choice(): Empty choice option block is not allowed.');
	    $gvelt->addedge($option->text, $gvelt->label, $label);
	    //if($label == 'games') print_r($option->block);
	    $this->makecsgv($option->block->block, $this->implicit_label);
	    
	}
	// After a *choice, need to set implicit_label to something. Usually will be a *label, unless *choice fell out. If fell out (or is unreachable), make an implicit label based on the last option. I think just appending 'FALLOUT' is fine. If it's a label, it'll be changed when proc_cselt() is called.
	$this->implicit_label .= 'FALLOUT';
        // Check for case where final option block falls out into following code.
	if(!in_array($option->block->block[count($option->block->block)-1]->type, array(cGOTO, GOTO_SCENE, FINISH, ENDING))) {
	    // Fallout detected. Make an edge going to the next cselt. Next cselt is $block[0]
	    // Get the last $gvelt made. It will be the last thing in the last option block.
	    $last_one = &$this->gvblock[count($this->gvblock)-1];

	    // If it has no edges, that means it fell out, so give in an edge.
	    if(count($last_one->edges) == 0) {
		//print_r($last_one);
		if($block[0]) {
		// There is a next cselt. If it's a label, use that instead of the one with FALLOUT appended
		    if($block[0]->type == LABEL) {
			$this->implicit_label = $block[0]->label;
			$last_one->addedge('', $last_one->label, $this->implicit_label);
		    } else {
			$last_one->addedge('', $last_one->label, $this->implicit_label);
		    }
		} else {
		    // There is no next cselt, so it's the end. This is equivalent to a *finish.
		    $last_one->addedge('', $last_one->label, 'FINISH');
		}
		//print_r($last_one);
	    } else die('proc_choices() checks for fall out case in two ways. One way was true while the other false. How can this happen?');
	}
    }
    
    private function proc_if(&$block, $gvelt, $cselt) {
	$if_blocks = 0; // Append and increment this to make a unique implicit label for each if block (i.e. the if block itself, elseif, and else blocks if any). $gvelt->label must be set already.
	$base_label = $this->implicit_label; // Will be: prevlabelIF
	
	// Append $gvelt's label with the if expr with spaces removed. It will now be: prevlabelIFexpr1
	$gvelt->label = $base_label.preg_replace('/\s+/','',$cselt->expr);
	$this->implicit_label = $gvelt->label;
	
	// Add $gvelt to the $gv array. 
	array_push($this->gvblock, $gvelt);
	$this->last_if = $gvelt;
	
	// Change $gvelt->show_label to false
	$gvelt->show_label = false;
	
	// Set $block_label to block's label if it has one. Otherwise make one based on implicit label and if_blocks;
	//print_r($cselt);
	if($cselt->block === null) die('proc_if(): null instead of object of class block found.');
	if($cselt->block->block === null) die('proc_if(): null instead of array found. Was expecting a block (an array of cs elts)');
	$block_label = $this->get_block_label($cselt->block->block);
	if(!$block_label) $block_label = $this->implicit_label.'TRUE';
	
	// Proc the if block
	$this->implicit_label = $block_label;
	$this->makecsgv($cselt->block->block);
	
	// Add edge from expr to the if block
	$gvelt->addedge('true', $gvelt->label, $block_label);

	// Get next elseif's if any
	$this->proc_elseifs($block, $gvelt, $base_label, $if_blocks);
	// Need to addedge but where and how? Maybe here or maybe in a recursive proc_elseif()?     
	    
	if($block[0]->type == cELSE) {
	    $this->proc_else($block, $base_label, $if_blocks);
	} 
	
	// Put on last edge, the false branch of final if or elseif
	$this->last_false_edge($block, $if_blocks);
	
	// Fallout handler
	$this->fallout($block);
    }
    
    private function fallout(&$block) {
	$last_one = &$this->gvblock[count($this->gvblock)-1];
	
	// If it has no edges, that means it fell out, so give in an edge.
	if(count($last_one->edges) == 0 && !in_array($last_one->type, array('FINISH', 'ENDING', 'GOTO_SCENE'))) {
	    //print_r($last_one);
	    if($block[0]) {
	    // There is a next cselt. If it's a label, use that instead of the one with FALLOUT appended
		if($block[0]->type == LABEL) {
		    $this->implicit_label = $block[0]->label;
		} 
		
		$last_one->addedge('', $last_one->label, $this->implicit_label);
	    } else {
		// There is no next cselt, so it's the end. This is equivalent to a *finish.
		$last_one->addedge('', $last_one->label, 'FINISH');
	    }
	    //print_r($last_one);
	} 
    }
    
    private function last_false_edge($block, &$if_blocks) {
	$block_label = $this->get_block_label($block);
	if(!$block_label) $block_label = $this->implicit_label.$if_blocks++;

	$this->implicit_label = $block_label;
	
	// Add edge from previous elseif (or the beginning if, if no elseifs)
	$this->last_if->addedge('false', $this->last_if->label, $block_label); 
    }
    
    private function proc_elseifs(&$block, $prev_cond, $base_label, &$if_blocks) {
	// Creates an elseif object from the first elt in $block. Calls $this->makecsgv() on the subblock.
	$cselt = array_shift($block);
	if($cselt === null) {
	    // Hit end of $block.
	    return;
	}elseif($cselt->type != cELSEIF) {
	    // No more elseif's. Put last elt found back on the stack and finish the recursion.
	    array_unshift($block, $cselt);
	    return;
	} 
	
	// Found an elseif, so make a new gvcs for it.	    
	$new_gvelt = new gvcs;
	// Add $new_gvelt to the gvblock array. Can we do this and still modify $new_gvelt? If not, fix.
	array_push($this->gvblock, $new_gvelt);
	
	// Fill in its properties
	$new_gvelt->type = COND;
	$new_gvelt->label = $base_label.preg_replace('/\s+/','',$cselt->expr);
	$new_gvelt->show_label = false;
	$new_gvelt->text = $cselt->expr;
	
	// Set implicit label
	$this->implicit_label = $new_gvelt->label;
	
	// Add edge from previous elseif (or the beginning if, if this is the first elseif)
	$prev_cond->addedge('false', $prev_cond->label, $new_gvelt->label);
	
	// Set $block_label to block's label if it has one. Otherwise make one based on implicit label and if_blocks;
	$block_label = $this->get_block_label($cselt->block->block);
	if(!$block_label) $block_label = $this->implicit_label.$if_blocks++;

	// Proc the if block
	$this->implicit_label = $block_label;
	$this->makecsgv($cselt->block->block);

	// Add edge from expr to the if block
	$new_gvelt->addedge('true', $new_gvelt->label, $block_label);

	// Set $this->last_if to the label of $new_gvelt. When done, it will really be the last elseif.
	$this->last_if = $new_gvelt;
	
	// Recursively call proc_elseifs() to get rest of elseifs
	$this->proc_elseifs($block, $new_gvelt, $base_label, $if_blocks);
    }

    private function proc_else(&$block, $base_label, &$if_blocks) {
	// Creates an else object from the first elt in $block. Calls $this->makecsgv() on the subblock.
	$cselt = array_shift($block);
	if($cselt === null) {
	    // Hit end of $block.
	    return;
	}elseif($cselt->type != cELSE) {
	    // No else. Put last elt found back on the stack.
	    die('proc_else() called on a non-else. Should never happen, cuz code checks before calling.');
	    array_unshift($block, $cselt);
	    return;
	} 
	
	// Set $block_label to block's label if it has one. Otherwise make one based on implicit label and if_blocks;
	
	$block_label = $this->get_block_label($cselt->block->block);
	if(!$block_label) $block_label = $this->implicit_label.$if_blocks++;

	// Add edge from previous elseif (or the beginning if, if no elseifs)
	//$this->last_if->addedge('false', $this->last_if->label, $block_label); trying to do this in proc_if() instead to do it for cases when there's no else
	
	// Proc the if block
	$this->implicit_label = $block_label;
	$this->makecsgv($cselt->block->block);
    }

    private function get_block_label($block) {
	if(!is_array($block)) die('get_block_label() called on non-array.');
	if($block[0]->type == LABEL) {
	    // If the very first element in the option block is a label, use that as the label. 
	    $label = $block[0]->label;
	    return $label;
	} elseif($block[0]->type == cGOTO) {
	    // If the very first element in the option block is a goto, use that as the label. 
	    $label = $block[0]->label;
	    return $label;
	} else {
	    // If neither is the case, block doesn't have a label
	    return false;
	}
    }
    
    public function makeGV() {
	$ret = "digraph g {\n";
	$parent = 'START';

	$ret .= "\t".$this->convertcs2gv($this->gvblock, $parent)."\n";

	$ret .= '}';
	return $ret;
    }

    private function convertcs2gv($block, $parent) {
	if(!$block) return;
	$ret = '';
	foreach ($block as $cs) {
	    //echo $cs->type."<BR>\n";
	    if($cs->type == PAGE) {
		$ret .= $this->page2gv($cs);
	    } elseif($cs->type == COND) {
		$ret .= $this->cond2gv($cs);
	    } elseif($cs->type == FINISH) {
		$ret .= $this->finish2gv($cs);
	    } else {
		die('Unknown type in cs2gv(). Should never happen.');
	    }
	}

	return $ret;
    }
    
	public function makeD3json() {
		$ret = "var data = [\n";
		$parent = 'START';
		
		$ret .= $this->cs2D3json($this->gvblock, $parent);

		$ret .= ']';
		return $ret;
	}
		
	public function makeNestedjson() {
		return $this->cs2nestedarray($this->gvblock);
	}
    
	private function cs2D3json($block, $parent) {
		echo 'Entered cs2D3json 1';
		if(!$block) return;
		echo '2<P>';
		$ret = '';
		foreach ($block as $cs) {
			if($cs->type == PAGE) {
			$ret .= $this->page2D3json($cs);
			/*} elseif($cs->type == COND) {
			$ret .= $this->cond2D3json($cs);
			} elseif($cs->type == FINISH) {
			$ret .= $this->finish2D3json($cs);
			} else {
				die('Unknown type in cs2D3json(). Should never happen.');*/
			}
		}

		return $ret;
	}

	private function cs2nestedarray($block) {
		if(!$block) return;

		// Set $flatarray to all edges in the input CS code
		$flatarray = array();
		foreach($block as $cs) {
			if($cs->type == PAGE) {
				$this->page2array($flatarray, $cs);
			}
		}
print_r($flatarray);
		$ret = '';
		$this->flat2tree($ret, $flatarray, 0);	
		return $ret;
	}

	private function flat2tree(&$ret, $flatarray, $indentlevel) {
		/* Set $ret to tree json version of edges in $flatarray. $flatarray is edges. */

	// Make $datamap an assoc array where key is a label and value is that label's parent.
	$datamap = array();
	foreach($flatarray as $elt) {
		if(is_array($flatarray)) {
			foreach($elt as $child) {
				$datamap[$child] = $elt->parent;
			}
		} else {
			// Do nothing?
		}
	}

	// Create tree array
	$tree = array();
	foreach($datamap as $key => $elt) {
		$ret .= "{\n".'"name": "'.$key.'",'."\n";
		$ret .= '"parent": "'.$this->flat2tree($ret, $flatarray, $indentlevel+1);
	}

	$tabs = '';
	for($a = $indentlevel; $a>=0; $a--) {
			$tabs .= "\t";
		}

	if(is_array($flatarray)) {
		foreach($flatarray as $key => $elt) {
			if (empty($elt)) return '';

			echo 'k'; echo "RET: $ret --- ";
			$ret .= $tabs.'"name": "'.$key.'",'."\n";
			$ret .= $tabs.'"children": ['."\n";
			$this->flat2tree($ret, $flatarray[$key], $indentlevel++);
		}
	} else {
		echo 'Huh? Non-array $flatarray in flat2tree()';
		print_r($flatarray);
		//exit;
	}
	}
	
    private function page2gv($cs) {
	if($cs->use_html) {
	    $openquote = '<';
	    $closequote = '>';
	    $node_text = '<TABLE BORDER="0" CELLBORDER="1" CELLSPACING="0"><TR><TD>'."\n";
	} else {
	    $openquote = '"';
	    $closequote = '"';
	    $node_text = '';
	}
	$cs->set_text($cs->text);
	$node_text .= $cs->text;
	if($cs->use_html) {
	    $node_text .= '</TD></TR>';
	} else {
	    $node_text .= "\n";
	}
	    
	$node_text .= $this->get_sets($cs);
	$node_text .= $this->get_vars($cs);
	
	if($cs->use_html) {
	    $node_text .= '</TABLE>';
	}

	$ret = $cs->label.' [label='.$openquote.$node_text.$closequote;
	$ret .= ' shape="rectangle"';
	$ret .= "]\n";
	$ret .= $this->edges2gv($cs);
	
	return $ret;
    }
    
    private function page2D3json($cs) {
		echo 'Entered page2D3json<P>';
	$openquote = '"';
	$closequote = '"';
	$node_text = '';

	$node_text .= $cs->label;
	$node_text .= "\n";

	$ret = '';
	$ret .= $this->edges2D3json($cs);
	
	return $ret;
    }

	private function page2array(&$ret, $cs) {
		$newedges = $this->edges2array($cs);
		array_merge($ret, $newedges);
	}
    
    private function get_sets($cs) {
	if(count($cs->sets) == 0) {
	    return '';
	}
	if($cs->use_html) {
	    foreach($cs->sets as $set) {
		$port = $cs->label.$set->var.$set->opr;
		$ret .= "<TR><TD>Set $set->var to</TD><TD PORT=\"$port\">$set->opr</TD></TR>\n";
	    }
	} else {
	    foreach($cs->sets as $set) {
		$ret .= "\n$set->var: $set->opr\n";
	    }
	}
	return $ret;
    }
    
    private function get_vars($cs) {
	if(count($cs->vars) == 0) {
	    return '';
	}
	if($cs->use_html) {
	    foreach($cs->vars as $key => $varVal_array) {
		foreach($varVal_array as $varVal) {
		    //$port = $cs->label.$set->var.$set->opr;
		    $ret .= "<TR><TD>$key</TD><TD>$varVal->value</TD></TR>\n";
		}
	    }
	} else {
	    foreach($cs->vars as $var) {
		$ret .= "\n$key: $var->value\n";
		echo print_r($var, true)."<BR>\n";
	    }
	}
	return $ret;
    }
    
    private function edges2gv($cs) {
	$ret = '';
	foreach($cs->edges as $edge) {
	    $ret .= "\t".$edge->parent.' -> '.$edge->child;
	    //echo $edge->text."<BR>";
	    if($edge->text) {
		$taillabel = $cs->truncwrap($edge->text, $cs->linelen * $cs->page_rows, $cs->linelen);
		$ret .= ' [taillabel=<<font color="green" point-size="5">'.$taillabel.'</font>>]'; // labeldistance="0"
	    }
	    $ret .= "\n";
	}
	return $ret;
    }
    
    private function edges2D3json($cs) {
	$ret = '';
	echo 'Entered edges2D3json<P>';
	print_r($cs);
	foreach($cs->edges as $edge) {
	    $ret .= "\t".'{"parent": "'.$edge->parent.'", "child": "'.$edge->child.'"},';
	    $ret .= "\n";
		echo "edges2D3json ret = ".$ret."<BR>";
	}

	// Chop off trailing comma
	$ret = rtrim(trim($ret), ',');

	return $ret;
    }

	private function edges2array($cs) {
		// Returns an array of all edges in $cs.
		$ret = array();
		foreach($cs->edges as $edge) {
			if($edge) {
				array_push($ret,  $edge);
			}
		}

		return $ret;
	}	

    private function cond2gv($cs) {
	$ret = $cs->label.' [label="';
	
	if($cs->show_label) {
	    $ret .= $cs->label.' ';
	}
	$ret .= trim($cs->text).'" ';
	$ret .= 'shape="diamond"';
	$ret .= ']'."\n";
	
	// Add the edges
	$ret .= $this->edges2gv($cs);
	
	return $ret;
    }
    
    private function finish2gv($cs) {
	$ret = $cs->label.' [label="';
	
	if($cs->show_label) {
	    $ret .= $cs->label.' ';
	}
	$ret .= $cs->text.'"';
	$ret .= ' shape="circle" width=.75 fixedsize=true';
	$ret .= ']';
	
	return $ret;
    }
    
    public function makeVars(&$gvelt, $path, $prev_vars) {
	// $gvblock is an array of objects of class gvcs. $path is the array representing the sequence of objects taken to get to the current one (for recursive traversal of entire tree). (Initially call with the empty array.) This function fills in $this->vars, which is an array of arrays, containing all possible values of every variable in the game and the path it took to get there. Dimension 1 has keys equal to the variable name and values equal to an array of classValue objects (dimension 2). 
	if(!$gvelt || !is_object($gvelt)) {
	    print_r($gvelt);
	    die('Bad $gvelt in makeVars()');
	}
	// if($gvelt->type != ) do we really need more error checking?
	
	// Set $labelkeys to an array mapping the label to its key in $this->gvblock if it doesn't already exist.
	if($this->labelkeys == array()) {
	    $this->labelkeys = $this->make_labelkeys($this->gvblock);
	}

	// Add all vars from before to this node
	// Must check for dupes here still
	$gvelt->vars = array_merge($gvelt->vars, $prev_vars);
	
	// Update $path for the following recursive call.
	$path[] = $gvelt->label;
	echo "Path: ".implode(' - ', $path)."<BR>\n";

	// Add all sets (*create, *temp, and *set) to $gvelt->vars
	foreach($gvelt->sets as $set) {
	    $this->addvar($set, $gvelt->vars, $path);
	}
	
	//echo "After addvar: ".print_r($gvelt->vars, true)."<BR>\n";
	
	// Recursively call makeVars() on each edge.
	foreach($gvelt->edges as $edge) {
	    //echo "The node about to go to: $edge->child<BR>\n";
	    //echo "The key of that node   : ".$this->labelkeys[$edge->child]."<BR>\n";
	    $label = $edge->child;
	    $key = $this->labelkeys[$label];
	    if(!$key) {
		// Label not in $gvblock. Do not follow.
	    } else {
	    $this->makeVars($this->gvblock[$key], $path, $gvelt->vars);
	    }
	}
    }
    
    private function addvar($set, &$vars, $path) {
	$objValue = new varVal;
	//dbug($this->matheval($set->var, $set->opr));
	if($vars[$set->var]) {
	    // Var to add is already in $vars
	    if(!is_array($vars[$set->var])) dbug(print_r($vars, true));
	    dbug("$set->var already here.");
	    $this->update_varVals($set, $vars, $path);
	    //$vars[$set->var]->value += $this->matheval($set->var, $set->opr);
	    //$vars[$set->var]->path = $path;
	    dbug(print_r($vars, true));
	    return;
	} else {
	    // Just add it. Var to add not yet in $vars.
	    dbug("$set->var not already here. Make new.");
	    $vars = array($set->var => array($objValue));
	}
	$objValue->value = $this->matheval($set->var, $set->opr);
	dbug("Set $set->var to $objValue->value");
	$objValue->path = $path;
	//echo "In addvar: ".print_r($vars, true)."<BR>\n";
    }
    
    private function update_varVals($set, &$vars, $path) {
	$varVal_array = &$vars[$set->var];
	$cur_gvelt_label = $path[count($path)-1];
	$adj = $this->matheval($set->var, $set->opr);
	
	//dbug("varvalarray: ".print_r($varVal_array, true));
	//dbug("curgveltlabel: $cur_gvelt_label");
	dbug(print_r($varVal_array, true));
	
	foreach($varVal_array as &$varVal) {
	    dbug("Adjusting $set->var: '$varVal->value' asdf '$adj'");
	    $varVal->value += $adj;
	    $varVal->path[] = $cur_gvelt_label;
	}
	dbug(print_r($varVal_array, true));
    }
    
    private function make_labelkeys($gvblock) {
	// This function would be unnecessary if I'd thought ahead and made the keys to $gvblock the label of the node, but oh well. Return an array where the key is the label and the value is the key of the corresponding elt in $gvblock. Used by makeVars() to find the next node without searching $gvblock each time. 
	$ret = array();
	
	foreach($gvblock as $key => $elt) {
	    $ret[$elt->label] = $key;
	    //echo "Setting '$elt->label' to '$key'<BR>\n";
	}
	//print_r($ret);
	return $ret;
    }
    
    private function matheval($var, $equation) 
  { 
	// Only supports "*set x %opr y", not "*set x x %opr y" or anything more complicated. If first token after x is not fairmath, then regular matheval should work. $equation is the "opr y" part, or can be "x opr y" if not fairmath. $var is the variable to set.
	if($this->is_fairmath($equation)) {
	    $equation = $this->conv_fairmath($var, $equation);
	} else {
	    $equation = $this->fix_equation($var, $equation);
	}
    $equation = preg_replace("/[^0-9+\-.*\/()%]/","",$equation); 
    // fix percentage calcul when percentage value < 10 
    $equation = preg_replace("/([+-])([0-9]{1})(%)/","*(1\$1.0\$2)",$equation); 
    // calc percentage 
    $equation = preg_replace("/([+-])([0-9]+)(%)/","*(1\$1.\$2)",$equation); 
    // you could use str_replace on this next line 
    // if you really, really want to fine-tune this equation 
    $equation = preg_replace("/([0-9]+)(%)/",".\$1",$equation); 
    if ( $equation == "" ) 
    { 
      $return = 0; 
    } 
    else 
    { 
	dbug("equation: $equation");
      eval("\$return=" . $equation . ";" ); 
    } 
    return $return; 
  }
  
  private function is_fairmath($eq) {
      $first2 = substr(trim($eq), 0, 2);
      if($first2 == '%+' || $first2 == '%-') {
	  if(is_numeric(trim(substr($eq, 2)))) {
	      return true;
	  }
      }
      return false;
  }
  
  private function conv_fairmath($var, $eq) {
      $operand1 = $var;
      $operator = substr(trim($eq), 0, 2);
      $operand2 = trim(substr($eq, 2));
      
      if($operator == '%+') {
	  return "($operand1 + ($operand1 + (100-$operand1)*($operand2/100))";
      }
      else {
	  return "($operand1 - ($operand1 - $operand1*($operand2/100))";
      }
  }
  
  private function fix_equation($var, $eq) {
      if($this->starts_with_operator($eq)) {
	  // Add $var on
	  return $var.$eq;
      }
      return $eq;
  }
  
  private function starts_with_operator($eq) {
      return preg_match('/^[\+\-\*\/\&\%\^].*/', $eq);
  }
}

function dbug($str) {
    echo "<pre>|$str|</pre><br>\n";
}
/* Fair Addition: (x %+ y) = (x + (100-x)*(y/100))
   Fair Substraction: (x %- y) = (x - x*(y/100))   

To see how Dan does it: scene.js, search for function evaluateExpr and function set.

 * *set x %+y => replace x with var
 *                */
?>
