<?php
/*
  Scenario 4:
  
  1- Alice writes "foo" into field F1
  2- Bob writes "bar" into field F2
  3- Carl writes "baz" into field F1
  4- We tamper the sequence by having Bob overwrite the second
     element by a new one where he writes "brr" with corresponding
     digest
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
$pas_t = new PeerActionSequence();
$pas_t->sequence[] = $pas->sequence[0];
$pas_t->appendTo("Bob", new FieldWriteAction("F2", "brr"));
$pas_t->sequence[] = $pas->sequence[2];

// Check
$ret_val = $pas_t->check();

// Verdict?
if ($ret_val > 0)
{
  echo "The peer-action sequence is invalid at position $ret_val\n";
}

/* :wrap=none:folding=explicit: */
?>