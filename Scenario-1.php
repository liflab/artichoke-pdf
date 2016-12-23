<?php
/*
  Scenario 1:
  
  1- Alice writes "foo" into field F1
  2- Bob writes "bar" into field F2
  3- Carl writes "baz" into field F1
  4- We tamper the string of the sequence by
     making Carl the agent of action 2
*/

// Load libs
require_once("lib/fdf.inc.php");
require_once("lib/peer.inc.php");
require_once("lib/peer-pdf.inc.php");

// Create original sequence
$pas = new PeerActionSequence();
$pas->appendTo("Alice", new FieldWriteAction("F1", "foo"));
$pas->appendTo("Bob", new FieldWriteAction("F2", "bar"));
$pas->appendTo("Carl", new FieldWriteAction("F1", "baz"));
$pas_string_original = $pas->toString();

// Tamper
$pas_string_tampered = str_replace("(Bob", "(Carl", $pas_string_original);

// Reconstruct from tampered string
$pas_t = PeerActionSequence::fromString($pas_string_tampered);

// Check
$ret_val = $pas_t->check();

// Verdict?
if ($ret_val > 0)
{
  echo "The peer-action sequence is invalid at position $ret_val\n";
}

/* :wrap=none:folding=explicit: */
?>