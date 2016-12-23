<?php
/*
  Scenario 5:
  
  1- Alice writes "foo" into field F1
  2- Bob writes "bar" into field F2
  3- Carl writes "baz" into field F1
  
  This is illustrated by generating the sequence of resulting PDFs
  for each step. Each PDF is also checked for validity at each
  step.
*/

// Load libs
require_once("lib/fdf.inc.php");
require_once("lib/peer.inc.php");
require_once("lib/peer-pdf.inc.php");

// Create original sequence
$filename = "Form.pdf";

// Modify
modify_and_stamp("Form.pdf", "Alice", array("F1" => "foo"), "Scenario-5.1.pdf");
echo "Checking\n";
check_all("Scenario-5.1.pdf");

modify_and_stamp("Scenario-5.1.pdf", "Bob", array("F2" => "bar"), "Scenario-5.2.pdf");
echo "Checking\n";
check_all("Scenario-5.2.pdf");

modify_and_stamp("Scenario-5.2.pdf", "Carl", array("F1" => "baz"), "Scenario-5.3.pdf");
echo "Checking\n";
check_all("Scenario-5.3.pdf");

// Check a tampered pdf
check_all("Scenario-5.3-tampered.pdf");
/* :wrap=none:folding=explicit: */
?>