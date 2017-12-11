<?php

/**
 * This is a PHP implementation of the Sudoku puzzle solving approach
 * described by Peter Norvig here: http://norvig.com/sudoku.html
 *
 * Note that comments, tests, and notes have been added with the goal
 * of making this PHP version as easy to understand as the original.
 *
 * @author Peter Wolfenden <wolfen@gmail.com>
 */

/**
 * The point of encapsulating debug output like this is to make it easy
 * to turn off (e.g via command line switch) if needed.
 *
 * @param string $msg
 * @return void
 */
function pdebug($msg) {
  print $msg; // comment out this line to suppress debug messages
}

/**
 * Return the cross product (via string concatenation) of the specified
 * arrays of strings.
 *
 * @param array $A
 * @param array $B
 * @return array
 */
function cross(array $A, array $B) {
  $out = array();
  foreach ($A as $a) {
    foreach ($B as $b) {
      $out[] = $a . $b;
    }
  }
  return $out;
}

/**
 * Represent nonzero digits as an associative array, so we can efficiently
 * check whether or not a given character is or is not a nonzero digit.
 *
 * @param void
 * @return array of char => (boolean) true
 */
function digits() {
  $digits = array();
  foreach (['1','2','3','4','5','6','7','8','9'] as $d) {
    $digits[$d] = true;
  }
  return $digits;
}

/**
 * NOTES:
 * - These values are specific to the 9x9 Sudoku board format.
 * - Since these values never change after initial assignment, we use
 *   globals to store them.
 * - It would be cleaner to encapsulate these values in an object, but
 *   I wanted in this PHP version to retain some visual similarity to
 *   to the original Python.
 */
$ROWS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
$COLS = array_keys(digits()); // '1', '2', ...
$SQUARES = cross($ROWS, $COLS); // A1, A2, ... A9, B1, B2, ... I9
// $UNITLIST, $UNITS, and $PEERS are defined next:

/**
 * Build a list of all "units"; A "unit" is a row, column, or
 * one of the nine 3x3 squares comprising the full Sudoku board.
 * Each returned unit is represented as an array of elements, e.g.
 * ['G7','G8','G9','H7','H8','H9','I7','I8','I9']
 *
 * @return array
 */
function build_unit_list() {
  global $ROWS, $COLS;

  $unitlist = array();
  foreach ($COLS as $c) {
    $unitlist[] = cross($ROWS, array($c));
  }
  foreach ($ROWS as $r) {
    $unitlist[] = cross(array($r), $COLS);
  }
  // NOTE: These hardcoded triplets should either be replaced by
  // something derived from $ROWS and $COLS, or everything should
  // be derived from a single parameter (e.g. 3).
  foreach([['A','B','C'],['D','E','F'],['G','H','I']] as $rows) {
    foreach([['1','2','3'],['4','5','6'],['7','8','9']] as $cols) {
      $unitlist[] = cross($rows, $cols);
    }
  }
  return $unitlist;
}
$UNITLIST = build_unit_list();

/**
 * Build an associative array which for each element $s of $SQUARES
 * has an associated list of all units in $UNITLIST containing $s.
 *
 * @return array
 */
function build_units() {
  global $SQUARES, $UNITLIST;

  $units = array();
  foreach ($SQUARES as $s) {
    if (!isset($units[$s])) {
      $units[$s] = array();
    }
    foreach ($UNITLIST as $u) {
      if (in_array($s, $u)) {
        $units[$s][] = $u;
      }
    }
  }
  return $units;
}
$UNITS = build_units();

/**
 * Build an associative array which for each square $k (a key in the
 * associative array $UNITS) has the set of all other squares (besides
 * $k) contained in $UNITS[$k]. These are the "peers" of square $k, i.e.
 * all the squares whose values may be affected by the value assigned
 * to square $k.
 * We represent "sets" as associative arrays of key => boolean pairs,
 * where each element has exactly one key. This gives us an easy way
 * to eliminate duplicate entries and makes testing for membership
 * an O(1) operation.
 *
 * @return array
 */
function build_peers() {
  global $UNITS;

  $peers = array();
  foreach ($UNITS as $k => $units_for_k) {
    $p = array();
    foreach ($units_for_k as $u) {
      foreach ($u as $square) {
        if ($square != $k) {
          $p[$square] = true;
        }
      }
    }
    $peers[$k] = $p;
  }
  return $peers;
}
$PEERS = build_peers();

/**
 * Verify that all data structures associated with a 9x9 Sudoku
 * board have been properly initialized.
 *
 * @return void
 */
function test_setup() {
  global $SQUARES, $UNITLIST, $UNITS, $PEERS;

  assert(count($SQUARES) == 81);
  assert(count($UNITLIST) == 27);

  foreach ($SQUARES as $s) {
    assert(count($UNITS[$s]) == 3);
    assert(count($PEERS[$s]) == 20);
  }

  assert($UNITS['C2'] == [
    ['A2','B2','C2','D2','E2','F2','G2','H2','I2'],
    ['C1','C2','C3','C4','C5','C6','C7','C8','C9'],
    ['A1','A2','A3','B1','B2','B3','C1','C2','C3']
  ]);
  assert(array_keys($PEERS['C2']) == [
    'A2','B2','D2','E2','F2','G2','H2','I2',
    'C1','C3','C4','C5','C6','C7','C8','C9',
    'A1','A3','B1','B3']
  );

  pdebug("Initialization tests pass.\n");
}
test_setup();

/**
 * Convert specified "grid" string into an associative array of
 * square => char pairs, where "square" is a string indicating
 * board position (e.g. "A1") and "char" is a digit or a period
 * (with '0' or '.' indicating an empty/unassigned square).
 *
 * @param string $grid
 * @return array of string => char
 */
function grid_values($grid) {
  global $SQUARES;

  if (!preg_match('/[0-9\.]{81}/', $grid)) {
    throw new Exception("Received string must consist of exactly 81 nonzero digits");
  }
  return array_combine($SQUARES, str_split($grid));
}

/**
 * Convert specified "grid" string into an associative array of
 * square => possible pairs, where "possible" values are represented
 * as an associative array of value => boolean pairs. These boolean
 * values are all initially true and are set to false as possible
 * values are eliminated during processing.
 *
 * @param string $grid
 * @return $values or false if a contradiction is detected
 */
function parse_grid($grid) {
  global $SQUARES, $UNITS;

  $values = array();
  $D = digits();

  // Initialize an associative array of possible values for each square.
  // NOTE: For simple arrays (no objects) this acts like a "deep copy".
  foreach ($SQUARES as $s) {
      $values[$s] = $D;
  }

  foreach (grid_values($grid) as $s => $v) {
    if (isset($D[$v]) && $D[$v] && !assign($values, $s, $v)) {
      return false; // cannot assign value $v to square $s, so fail!
    }
  }

  return $values;
}

/**
 * Eliminate all the other values (except $d) from $values[$s] and propagate.
 * Note the explicit "pass by value" designation on the $values parameter
 * is required to override the PHP "copy on write" default behavior.
 *
 * @param array $values maps square position (index) to a map of value => (boolean) true
 * @param string $s is the position (index) of a square
 * @param char $d is the value to assign to position $s
 * @return (modified) $values or false if a contradiction is detected
 */
function assign(array &$values, $s, $d) {
  global $PEERS;

  if (!isset($values[$s][$d]) || ($values[$s][$d] == false)) {
    return false; // cannot assign
  }

  foreach ($values[$s] as $v => $dummy) {
    if (($v != $d) && !eliminate($values, $s, $v)) {
      return false; // assignment would cause contradiction
    }
  }

  // Eliminate $d from possible values for every "peer" of $s:
  foreach ($PEERS[$s] as $s2) {
    if (!eliminate($values, $s2, $v)) {
      return false; // assignment would cause contradiction
    }
  }

  return $values;
}

/**
 * Given the available square values specified in $values, which of the
 * square indices specified in $u could take the value specified in $d?
 *
 * @param array $values maps square position (index) to a map of value => (boolean) true
 * @param array $u is a set of positions (index values) to check
 * @param char $d is the value to look for
 * @return array of positions (index values) from $u where $values can take value $d
 */
function squares_compatible_with_value(array $values, array $u, $d) {
  $compat = array();
  foreach ($u as $s) {
    if (isset($values[$s][$d]) && $values[$s][$d]) {
      $compat[] = $s;
    }
  }
  return $compat;
}

/**
 * Return an array of all the keys in the passed associative array which
 * have an associated "truthy" value.
 *
 * @param array $set 
 * @return array
 */
function true_value_keys(array $set) {
  $tv = array();
  foreach ($set as $k => $v) {
    if ($v) {
      $tv[] = $k;
    }
  }
  return $tv;
}

/**
 * Eliminate $d from $values[$s], and propagate via two rules:
 *
 * RULE 1: If the number of possible values for square $s is reduced to one,
 *         then eliminate this value from all the "peers" of $s.
 * RULE 2: If a unit $u (containing $s) is reduced to having only one square
 *         which can accept value $d, then assign $d to that square.
 *
 * NOTES:
 * - Although the job of this function is to *eliminate* $d from position $s,
 *   each call to eliminate() may lead to a cascade of calls to assign() and
 *   further calls to eliminate().
 * - The explicit "pass by value" designation on the $values parameter is
 *   required to override the PHP "copy on write" default behavior.
 *
 * @param array $values maps square position (index) to a map of value => (boolean) true
 * @param string $s is the position (index) of a square
 * @param char $d is the value to exclude from consideration from position $s
 * @return (modified) $values or false if a contradiction is detected
 */
function eliminate(array &$values, $s, $d) {
  global $UNITS, $PEERS;

  if (!isset($values[$s][$d]) || ($values[$s][$d] == false)) {
    return $values; // already eliminated
  }
  $values[$s][$d] = false;

  // Apply "RULE 1" (see above).
  $tv = true_value_keys($values[$s]);
  if (count($tv) == 0) {
    return false; // removed last value for $s (impossible)
  } else if (count($tv) == 1) {
    $d2 = $tv[0];
    foreach (array_keys($PEERS[$s]) as $p) {
      if (!eliminate($values, $p, $d2)) {
        return false; // cannot eliminate $d2 from all peers of $s (fail)
      }
    }
  }

  // Apply "RULE 2" (see above):
  foreach ($UNITS[$s] as $u) {
    $dplaces = squares_compatible_with_value($values, $u, $d);
    if (count($dplaces) == 0) {
      return false; // no place for $d in $u (impossible)
    } else if (count($dplaces) == 1) {
      if (!assign($values, $dplaces[0], $d)) {
        return false; // $d must go in $dplaces[0], but cannot (impossible)
      }
    }
  }

  return $values;
}

/**
 * Return a version of the passed string $s with spaces prepended and
 * appended (in more or less equal numbers) so as to bring the total
 * length up to the specified $width (which is assumed to be at least
 * the length of $s).
 *
 * @param string $s
 * @param int $width
 * @return string
 */
function center($s, $width) {
  $lpad = floor(($width - strlen($s))/2);
  $rpad = $width - ($lpad + strlen($s));
  return str_repeat(' ', $lpad) . $s . str_repeat(' ', $rpad);
}

/**
 * Figure out the number of "possible" values for each key in the specified
 * associative array, and return the maximum over all keys in $values.
 *
 * @param array $values
 * @return integer
 */
function max_field_width(array $values) {
  $L = 0;
  foreach ($values as $k => $v) {
    $l = implode("", true_value_keys($values[$k]));
    if (strlen($l) > $L) { $L = strlen($l); }
  }

  return $L;
}

/**
 * Display these values as a single line.
 *
 * @param array $values maps square position (index) to a map of value => (boolean) true
 * @param boolean $to_string if true then return string, else (default) write to STDOUT
 * @return void|string
 */
function display_line(array $values, $to_string=false) {
  global $ROWS, $COLS;

  $out = '';
  for ($i=0; $i < count($ROWS); $i++) {
    for ($j=0; $j < count($COLS); $j++) {
      $s = $ROWS[$i] . $COLS[$j];
      $v = true_value_keys($values[$s]);
      if (count($v) == 1) {
        $out .= $v[0];
      } else if (count($v) == 10) {
        $out .= '.';
      } else {
        $out .= '?';
      }
    }
  }
  if ($to_string) {
    return $out;
  }
  print "$out\n";
}

/**
 * Display these values as a 2-D grid.
 *
 * @param array $values maps square position (index) to a map of value => (boolean) true
 * @param boolean $to_string if true then return string, else (default) write to STDOUT
 * @return void|string
 */
function display_grid(array $values, $to_string=false) {
  global $ROWS, $COLS;

  $out = '';
  $NEWLINE = "\n";
  $H_SEPARATOR = '|';
  $width = 1 + max_field_width($values);
  $v_separator = implode('+', array_fill(0, 3, str_repeat('-', 3 * $width)));

  for ($i=0; $i < count($ROWS); $i++) {
    if (($i > 0) && ($i % 3) == 0) {
      if ($to_string) {
        $out .= $v_separator . "\n";
      } else {
        print $v_separator . "\n";
      }
    }
    for ($j=0; $j < count($COLS); $j++) {
      $s = $ROWS[$i] . $COLS[$j];
      $v = implode("", true_value_keys($values[$s]));

      if (($j > 0) && ($j % 3) == 0) {
        if ($to_string) {
          $out .= $H_SEPARATOR;
        } else {
          print $H_SEPARATOR;
        }
      }
      if ($to_string) {
        $out .= center($v, $width);
      } else {
        print center($v, $width);
      }
    }
    if ($to_string) {
      $out .= $NEWLINE;
    } else {
      print $NEWLINE;
    }
  }

  if ($to_string) {
    return $out;
  }
}

/**
 * Verify that the display_grid() and parse_grid() methods work as expected:
 *
 * @return void
 */
function test_parse_grid_and_display() {
  global $SQUARES, $PEERS;

  $g1 = '00302060090030500100180640000810290070000000800670820000260950080020300900501030.';
  assert(display_line(parse_grid($g1), true) == '483921657967345821251876493548132976729564138136798245372689514814253769695417382');
  $model = "4 8 3 |9 2 1 |6 5 7 
9 6 7 |3 4 5 |8 2 1 
2 5 1 |8 7 6 |4 9 3 
------+------+------
5 4 8 |1 3 2 |9 7 6 
7 2 9 |5 6 4 |1 3 8 
1 3 6 |7 9 8 |2 4 5 
------+------+------
3 7 2 |6 8 9 |5 1 4 
8 1 4 |2 5 3 |7 6 9 
6 9 5 |4 1 7 |3 8 2 
";
  assert(display_grid(parse_grid($g1), true) == $model);

  $g2 = '4.....8.5.3..........7......2.....6.....8.4......1.......6.3.7.5..2.....1.4......';
  $model2 = "   4      1679   12679  |  139     2369    269   |   8      1239     5    
 26789     3    1256789 | 14589   24569   245689 | 12679    1249   124679 
  2689   15689   125689 |   7     234569  245689 | 12369   12349   123469 
------------------------+------------------------+------------------------
  3789     2     15789  |  3459   34579    4579  | 13579     6     13789  
  3679   15679   15679  |  359      8     25679  |   4     12359   12379  
 36789     4     56789  |  359      1     25679  | 23579   23589   23789  
------------------------+------------------------+------------------------
  289      89     289   |   6      459      3    |  1259     7     12489  
   5      6789     3    |   2      479      1    |   69     489     4689  
   1      6789     4    |  589     579     5789  | 23569   23589   23689  
";
  assert(display_grid(parse_grid($g2), true) == $model2);
  assert(display_line(parse_grid($g2), true) == '4?????8?5?3??????????7??????2?????6?????8?4???4??1???????6?3?7?5?32?1???1?4??????');

  pdebug("Tests for parse_grid(), display_line(), and display_grid() pass.\n");
}
test_parse_grid_and_display($PEERS, $UNITS);

/**
 * Using depth-first search and propagation, try all possible values.
 *
 * @param array $values maps square position (index) to a map of value => (boolean) true
 * @return (modified) $values or false if contradiction (or no solution) found
 */
function search($values) {
  global $SQUARES;

  if ($values == false) {
    return false; // something failed
  }

  $solved = true;
  $unsolved_min_square = null;
  $unsolved_min_choices = null;

  foreach ($SQUARES as $s) {
    $tvc = count(true_value_keys($values[$s]));
    if ($tvc > 1) {
      $solved = false;
      if (($unsolved_min_choices == null) || ($tvc < $unsolved_min_choices)) {
        $unsolved_min_choices = $tvc;
        $unsolved_min_square = $s;
      }
    } else if ($tvc < 1) {
      return false; // something failed
    }
  }
  if ($solved) {
    return $values;
  }

  // Try filling the unfilled square with fewest possible choices:
  foreach ($values[$unsolved_min_square] as $v => $possible) {
    if ($possible) {
      $values2 = $values; // acts like a "deep copy" for simple arrays (no objects)
      if (assign($values2, $unsolved_min_square, $v)) {
        $r = search($values2);
        if ($r != false) {
          return $r;
        }
      }
    }
  }

  // None of the "possible" values for $unsolved_min_square worked:
  return false;
}

/**
 * Using depth-first search and propagation, try all possible values.
 *
 * @param string $grid
 * @return $values or false if contradiction (or no solution) found
 */
function solve($grid) {
  return search(parse_grid($grid));
}

/**
 * Verify that the search() and solve() methods work as expected:
 *
 * @return void
 */
function test_search_and_solve() {
  global $ROWS, $COLS, $PEERS;

  $g = '4.....8.5.3..........7......2.....6.....8.4......1.......6.3.7.5..2.....1.4......';
  $s = solve($g);
  assert($s != false);
  assert(display_line($s, true) == '417369825632158947958724316825437169791586432346912758289643571573291684164875293');
  pdebug("Test for solve() and search() passes.\n");
}
test_search_and_solve();

/** Main **/
$g = '1.....7.9.4...72..8.........7..1..6.3.......5.6..4..2.........8..53...7.7.2....46';
print "Example from http://norvig.com/top95.txt:  $g\n";
$s = solve($g, $PEERS);
print "Solution: " . display_line($s, true) . "\n";

